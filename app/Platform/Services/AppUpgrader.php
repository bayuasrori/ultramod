<?php

namespace App\Platform\Services;

use App\Platform\Contracts\UpgradeStep;
use App\Platform\Events\AppUpdated;
use App\Platform\Exceptions\AppNotFoundException;
use App\Platform\Exceptions\InvalidManifestException;
use App\Platform\Exceptions\UpgradeFailedException;
use App\Platform\Manifests\AppManifest;
use App\Platform\Models\PlatformApp;
use App\Platform\Models\PlatformAppUpgrade;
use App\Platform\Upgrades\AppUpgradeItem;
use App\Platform\Upgrades\UpgradeContext;
use App\Platform\Upgrades\UpgradePlan;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Throwable;

/**
 * The one-click upgrade pipeline.
 *
 * `plan()` is a read-only dry run: it resolves what would happen, including
 * apps that have to come along because they depend on the one being upgraded,
 * and collects the reasons an upgrade must not start. `execute()` runs that
 * plan under a lock, one app at a time, in dependency order.
 *
 * Per app the order is: `pre` steps (oldest version first) → schema
 * migrations → `post` steps → permission sync → version bump. Pre steps see
 * the old schema, post steps see the new one.
 */
class AppUpgrader
{
    public const LOCK_KEY = 'platform:upgrade';

    public function __construct(
        protected AppManager $manager,
        protected AuditLogger $audit,
        protected Container $container,
    ) {}

    /**
     * Plan and run in one go. Returns the executed plan.
     */
    public function upgrade(string|array $appIds, bool $force = false): UpgradePlan
    {
        return $this->execute($this->plan($appIds, $force));
    }

    /**
     * Every installed app whose manifest offers a newer version.
     */
    public function planOutdated(): UpgradePlan
    {
        $outdated = PlatformApp::whereIn('status', [PlatformApp::STATUS_INSTALLED, PlatformApp::STATUS_ENABLED])
            ->get()
            ->filter(fn (PlatformApp $app) => $app->hasUpgrade())
            ->pluck('app_id')
            ->all();

        return $this->plan($outdated);
    }

    /**
     * Resolve what an upgrade would do. Never writes.
     */
    public function plan(string|array $appIds, bool $force = false): UpgradePlan
    {
        $plan = new UpgradePlan;
        $requested = array_values(array_unique((array) $appIds));

        if ($requested === []) {
            $plan->warn('No app is outdated.');

            return $plan;
        }

        $queue = $requested;
        $seen = [];
        $dependencies = [];

        while ($queue !== []) {
            $appId = array_shift($queue);

            if (isset($seen[$appId])) {
                continue;
            }
            $seen[$appId] = true;

            $isRequested = in_array($appId, $requested, true);

            try {
                $app = $this->manager->find($appId);
                $manifest = $this->manager->manifestFor($appId);
            } catch (AppNotFoundException|InvalidManifestException $e) {
                $plan->block($e->getMessage());

                continue;
            }

            if (! $app->isLive()) {
                $plan->block("App [{$app->name}] must be installed before it can be upgraded.");

                continue;
            }

            $from = $app->version;
            $to = $manifest->version;

            if (version_compare($to, $from, '<=') && ! ($force && $isRequested)) {
                if ($isRequested) {
                    $plan->warn("App [{$app->name}] is already at version {$from}.");
                }

                // Nothing to do for this app, but its dependents may still be
                // affected by an app already in the plan; that is checked below.
                continue;
            }

            if (! $manifest->supportsPlatform((string) config('platform.version'))) {
                $plan->block(
                    "App [{$app->name}] {$to} requires platform {$manifest->platformConstraint}, ".
                    'but this platform is '.config('platform.version').'.'
                );

                continue;
            }

            $min = $manifest->minUpgradableFrom();

            if ($min !== null && version_compare($from, $min, '<')) {
                $plan->block(
                    "App [{$app->name}] can only be upgraded from {$min} or newer; {$from} is installed."
                );

                continue;
            }

            $dependencies[$appId] = array_keys($manifest->requires);

            $permissions = $this->manager->permissionDiff($appId);

            $plan->addItem(new AppUpgradeItem(
                app: $app,
                fromVersion: $from,
                toVersion: $to,
                reason: $isRequested ? AppUpgradeItem::REASON_REQUESTED : AppUpgradeItem::REASON_DEPENDENCY,
                pendingMigrations: $this->pendingMigrations($appId),
                steps: $this->stepsFor($appId, $from, $to, $force),
                permissionsAdded: $permissions['added'],
                permissionsRemoved: $permissions['removed'],
                warnings: [],
                maintenance: $manifest->upgradeNeedsMaintenance(),
            ));

            // Anything that depends on this app either has to move with it or
            // the whole upgrade is refused — a dependent left behind on an
            // incompatible version is exactly the breakage this prevents.
            foreach ($this->manager->dependentsOf($appId) as $dependent) {
                try {
                    $dependentManifest = $this->manager->manifestFor($dependent->app_id);
                } catch (AppNotFoundException|InvalidManifestException) {
                    continue;
                }

                $constraint = (string) $dependentManifest->requires[$appId];

                if (! AppManifest::satisfies($to, $constraint)) {
                    $plan->block(
                        "[{$dependent->name}] {$dependentManifest->version} requires {$app->name} {$constraint}, ".
                        "so {$app->name} cannot move to {$to}. Update {$dependent->name} first."
                    );

                    continue;
                }

                if (version_compare($dependentManifest->version, $dependent->version, '>')) {
                    $queue[] = $dependent->app_id;
                }
            }
        }

        $this->validateDependencies($plan);
        $plan->sortTopologically($dependencies);

        if ($plan->isEmpty() && ! $plan->isBlocked()) {
            $plan->warn('Nothing to upgrade.');
        }

        if ($plan->requiresMaintenance()) {
            $plan->warn('The site will be put into maintenance mode while this upgrade runs.');
        }

        return $plan;
    }

    /**
     * Every planned app must still find its own requirements satisfied once
     * the plan has been applied.
     */
    protected function validateDependencies(UpgradePlan $plan): void
    {
        foreach ($plan->items as $item) {
            try {
                $manifest = $this->manager->manifestFor($item->appId());
            } catch (AppNotFoundException|InvalidManifestException) {
                continue;
            }

            foreach ($manifest->requires as $requiredId => $constraint) {
                $required = PlatformApp::where('app_id', $requiredId)->first();

                if ($required === null || ! $required->isLive()) {
                    $plan->block(
                        "[{$item->app->name}] {$item->toVersion} requires app [{$requiredId}], which is not installed."
                    );

                    continue;
                }

                $effective = $plan->itemFor($requiredId)?->toVersion ?? $required->version;

                if (! AppManifest::satisfies($effective, (string) $constraint)) {
                    $plan->block(
                        "[{$item->app->name}] {$item->toVersion} requires {$requiredId} {$constraint}, ".
                        "but {$effective} would be installed."
                    );
                }
            }
        }
    }

    /**
     * Migration files of the app that have not run yet.
     *
     * @return array<int, string>
     */
    public function pendingMigrations(string $appId): array
    {
        if (! $this->manager->hasMigrations($appId)) {
            return [];
        }

        $ran = DB::table('migrations')->pluck('migration')->all();
        $pending = [];

        foreach (glob(base_path($this->manager->migrationsPath($appId)).'/*.php') ?: [] as $file) {
            $name = basename($file, '.php');

            if (! in_array($name, $ran, true)) {
                $pending[] = $name;
            }
        }

        sort($pending);

        return $pending;
    }

    /**
     * Upgrade steps between two versions, in execution order: every `pre`
     * step oldest-version-first, then every `post` step oldest-version-first.
     *
     * A jump from 1.0 to 1.3 runs the steps of 1.1, 1.2 and 1.3 — versions
     * are never skipped.
     *
     * @return array<int, array{version: string, phase: string, step: string, path: string}>
     */
    public function stepsFor(string $appId, string $from, string $to, bool $force = false): array
    {
        $root = $this->manager->appsPath().DIRECTORY_SEPARATOR.$appId.DIRECTORY_SEPARATOR.'upgrades';

        if (! is_dir($root)) {
            return [];
        }

        $versions = [];

        foreach (glob($root.'/*', GLOB_ONLYDIR) ?: [] as $dir) {
            $version = basename($dir);

            if (! preg_match('/^\d+(\.\d+)*$/', $version)) {
                continue;
            }

            if (version_compare($version, $to, '>')) {
                continue;
            }

            // A reapply replays the whole history up to the current version;
            // steps that already succeeded are skipped at execution time.
            if (! $force && version_compare($version, $from, '<=')) {
                continue;
            }

            $versions[] = $version;
        }

        usort($versions, 'version_compare');

        $pre = [];
        $post = [];

        foreach ($versions as $version) {
            foreach (['pre' => 'PreUpgrade', 'post' => 'PostUpgrade'] as $phase => $file) {
                $path = $root.DIRECTORY_SEPARATOR.$version.DIRECTORY_SEPARATOR.$file.'.php';

                if (! is_file($path)) {
                    continue;
                }

                $entry = [
                    'version' => $version,
                    'phase' => $phase,
                    'step' => "upgrades/{$version}/{$file}.php",
                    'path' => $path,
                ];

                $phase === 'pre' ? $pre[] = $entry : $post[] = $entry;
            }
        }

        return array_merge($pre, $post);
    }

    /**
     * Run a plan. Blocked or empty plans are refused rather than half-applied.
     */
    public function execute(UpgradePlan $plan): UpgradePlan
    {
        if ($plan->isBlocked()) {
            throw UpgradeFailedException::blocked($plan->blockers);
        }

        if ($plan->isEmpty()) {
            throw new UpgradeFailedException(
                $plan->warnings === [] ? 'Nothing to upgrade.' : implode(' ', $plan->warnings)
            );
        }

        $lock = Cache::lock(self::LOCK_KEY, 900);

        if (! $lock->get()) {
            throw new UpgradeFailedException('Another upgrade is already running. Try again in a moment.');
        }

        $maintenance = $plan->requiresMaintenance();

        try {
            if ($maintenance) {
                Artisan::call('down');
            }

            foreach ($plan->items as $item) {
                $this->upgradeApp($item);
            }
        } finally {
            if ($maintenance) {
                Artisan::call('up');
            }

            $this->clearCaches();
            $lock->release();
        }

        return $plan;
    }

    protected function upgradeApp(AppUpgradeItem $item): void
    {
        $app = $item->app;
        $appId = $item->appId();
        $manifest = $this->manager->manifestFor($appId);
        $batchBefore = $this->currentMigrationBatch();
        $ranMigrations = false;

        try {
            foreach ($item->steps as $step) {
                if ($step['phase'] === 'pre') {
                    $this->runStep($item, $step);
                }
            }

            if ($this->manager->hasMigrations($appId)) {
                $this->record(
                    $item,
                    PlatformAppUpgrade::STEP_MIGRATIONS,
                    PlatformAppUpgrade::PHASE_MIGRATIONS,
                    $item->toVersion,
                    // Laravel's own migrations table is the source of truth for
                    // what already ran, so this step is never skipped by the
                    // upgrade ledger. The flag is set before running so that a
                    // partially applied batch is still rolled back.
                    function () use ($appId, &$ranMigrations) {
                        $ranMigrations = true;

                        return $this->manager->runAppMigrations($appId);
                    },
                );
            }

            foreach ($item->steps as $step) {
                if ($step['phase'] === 'post') {
                    $this->runStep($item, $step);
                }
            }

            $permissions = $this->manager->syncPermissions($appId);

            $app->forceFill([
                'version' => $item->toVersion,
                'available_version' => $manifest->version,
                'name' => $manifest->name,
                'provider' => $manifest->provider,
                'manifest_hash' => $this->manager->manifestHash($appId),
                'upgraded_at' => now(),
                'last_upgrade_error' => null,
            ])->save();

            Event::dispatch(new AppUpdated($app, $item->fromVersion, $item->toVersion));

            $this->audit->log('app.upgraded', $app, [
                'from' => $item->fromVersion,
                'to' => $item->toVersion,
                'steps' => array_column($item->steps, 'step'),
                'permissions' => $permissions,
            ]);
        } catch (Throwable $e) {
            $app->forceFill(['last_upgrade_error' => $e->getMessage()])->save();

            if ($ranMigrations && $manifest->upgradeRollsBack()) {
                $this->rollbackMigrations($appId, $batchBefore);
            }

            $this->audit->log('app.upgrade_failed', $app, [
                'from' => $item->fromVersion,
                'to' => $item->toVersion,
                'error' => $e->getMessage(),
            ]);

            throw $e instanceof UpgradeFailedException
                ? $e
                : UpgradeFailedException::step($appId, $item->toVersion, $e->getMessage());
        }
    }

    /**
     * @param  array{version: string, phase: string, step: string, path: string}  $step
     */
    protected function runStep(AppUpgradeItem $item, array $step): void
    {
        $done = PlatformAppUpgrade::where('app_id', $item->appId())
            ->where('to_version', $step['version'])
            ->where('step', $step['step'])
            ->where('status', PlatformAppUpgrade::STATUS_SUCCESS)
            ->exists();

        if ($done) {
            return;
        }

        $this->record($item, $step['step'], $step['phase'], $step['version'], function () use ($item, $step) {
            $instance = $this->resolveStep($step['path']);

            $context = new UpgradeContext(
                app: $item->app,
                fromVersion: $item->fromVersion,
                toVersion: $item->toVersion,
                stepVersion: $step['version'],
            );

            $instance->run($context);

            return implode(PHP_EOL, $context->messages());
        });
    }

    /**
     * Upgrade step files return their step the way migration files return a
     * migration, so they need no autoload entry and no `dump-autoload`.
     */
    protected function resolveStep(string $path): UpgradeStep
    {
        $resolved = require $path;

        if (is_string($resolved) && class_exists($resolved)) {
            $resolved = $this->container->make($resolved);
        }

        if (! $resolved instanceof UpgradeStep) {
            throw new UpgradeFailedException(
                "Upgrade step [{$path}] must return an instance of ".UpgradeStep::class.'.'
            );
        }

        return $resolved;
    }

    /**
     * Run a unit of work while recording it in the upgrade ledger.
     */
    protected function record(AppUpgradeItem $item, string $step, string $phase, string $version, callable $work): void
    {
        $row = PlatformAppUpgrade::updateOrCreate(
            ['app_id' => $item->appId(), 'to_version' => $version, 'step' => $step],
            [
                'from_version' => $item->fromVersion,
                'phase' => $phase,
                'status' => PlatformAppUpgrade::STATUS_RUNNING,
                'duration_ms' => null,
                'output' => null,
            ],
        );

        $startedAt = microtime(true);

        try {
            $output = $work();
        } catch (Throwable $e) {
            $row->update([
                'status' => PlatformAppUpgrade::STATUS_FAILED,
                'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
                'output' => $e->getMessage(),
            ]);

            throw $e;
        }

        $row->update([
            'status' => PlatformAppUpgrade::STATUS_SUCCESS,
            'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
            'output' => is_string($output) && $output !== '' ? $output : null,
        ]);
    }

    protected function currentMigrationBatch(): int
    {
        return (int) DB::table('migrations')->max('batch');
    }

    /**
     * Undo the migration batch this attempt created. A half-applied schema is
     * worse than losing the columns the failed upgrade introduced; apps that
     * disagree set `upgrade.rollback_on_failure` to false in their manifest.
     */
    protected function rollbackMigrations(string $appId, int $batchBefore): void
    {
        if ($this->currentMigrationBatch() <= $batchBefore) {
            return;
        }

        try {
            Artisan::call('migrate:rollback', [
                '--path' => $this->manager->migrationsPath($appId),
                '--force' => true,
            ]);
        } catch (Throwable) {
            // Rollback is best effort: the original failure is what the
            // administrator needs to see, not a secondary one.
        }
    }

    /**
     * New code is live only once the compiled caches are gone.
     */
    protected function clearCaches(): void
    {
        foreach (['config:clear', 'route:clear', 'view:clear', 'event:clear'] as $command) {
            try {
                Artisan::call($command);
            } catch (Throwable) {
                // Nothing cached, nothing to clear.
            }
        }
    }
}
