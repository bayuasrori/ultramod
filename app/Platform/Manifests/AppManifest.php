<?php

namespace App\Platform\Manifests;

use App\Platform\Exceptions\InvalidManifestException;

class AppManifest
{
    /**
     * Where an app sits in the navbar when its manifest does not say.
     * Explicitly ordered apps use lower numbers and come first.
     */
    public const DEFAULT_MENU_ORDER = 100;

    /**
     * @param  array<string>  $permissions
     * @param  array<string, string>  $requires
     * @param  array<string>  $extends
     * @param  array<string, mixed>  $upgrade
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly string $name,
        public readonly string $version,
        public readonly string $description,
        public readonly string $provider,
        public readonly string $platformConstraint,
        public readonly array $permissions = [],
        public readonly array $requires = [],
        public readonly array $extends = [],
        public readonly array $upgrade = [],
        public readonly int $menuOrder = self::DEFAULT_MENU_ORDER,
    ) {}

    public static function fromFile(string $path): self
    {
        if (! is_file($path)) {
            throw new InvalidManifestException("Manifest file [{$path}] does not exist.");
        }

        $data = json_decode((string) file_get_contents($path), true);

        if (! is_array($data)) {
            throw new InvalidManifestException("Manifest file [{$path}] is not valid JSON.");
        }

        return self::fromArray($data);
    }

    /**
     * @param  array<mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        foreach (['id', 'name', 'version', 'provider'] as $field) {
            if (empty($data[$field]) || ! is_string($data[$field])) {
                throw new InvalidManifestException("Manifest field [{$field}] is required.");
            }
        }

        $constraint = $data['requires']['platform'] ?? '*';

        $requires = $data['requires'] ?? [];
        if (isset($requires['platform'])) {
            unset($requires['platform']);
        }

        return new self(
            id: $data['id'],
            type: $data['type'] ?? 'application',
            name: $data['name'],
            version: $data['version'],
            description: $data['description'] ?? '',
            provider: $data['provider'],
            platformConstraint: $constraint,
            permissions: array_values(array_filter(
                $data['permissions'] ?? [],
                fn ($p) => is_string($p) && $p !== '',
            )),
            requires: $requires,
            extends: $data['extends'] ?? [],
            upgrade: is_array($data['upgrade'] ?? null) ? $data['upgrade'] : [],
            menuOrder: is_numeric($data['menu_order'] ?? null)
                ? (int) $data['menu_order']
                : self::DEFAULT_MENU_ORDER,
        );
    }

    public function directory(): string
    {
        return config('platform.apps_path').DIRECTORY_SEPARATOR.$this->id;
    }

    public function supportsPlatform(string $platformVersion): bool
    {
        return self::satisfies($platformVersion, $this->platformConstraint);
    }

    /**
     * Should this app be put into maintenance mode while it upgrades?
     */
    public function upgradeNeedsMaintenance(): bool
    {
        return (bool) ($this->upgrade['maintenance'] ?? false);
    }

    /**
     * Roll the app's migration batch back when an upgrade step fails. On by
     * default: a half-applied schema is worse than losing the new columns.
     */
    public function upgradeRollsBack(): bool
    {
        return (bool) ($this->upgrade['rollback_on_failure'] ?? true);
    }

    /**
     * Oldest installed version this release knows how to upgrade from.
     */
    public function minUpgradableFrom(): ?string
    {
        $value = $this->upgrade['min_upgradable_from'] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Minimal semver: supports "*", "^x.y[.z]", "~x.y[.z]", comparison
     * operators (">=1.2", "<2.0") and an exact version. Anything else is
     * treated as an exact match, which fails closed rather than open.
     */
    public static function satisfies(string $version, string $constraint): bool
    {
        $constraint = trim($constraint);

        if ($constraint === '' || $constraint === '*') {
            return true;
        }

        // Comma/space separated constraints must all hold.
        if (preg_match('/[,|]/', $constraint)) {
            foreach (preg_split('/\s*[,|]\s*/', $constraint) as $part) {
                if (! self::satisfies($version, $part)) {
                    return false;
                }
            }

            return true;
        }

        if (preg_match('/^\^(\d+)\.(\d+)(?:\.(\d+))?$/', $constraint, $m)) {
            $major = (int) $m[1];
            $upper = $major === 0
                ? ($major.'.'.((int) $m[2] + 1).'.0')
                : (($major + 1).'.0.0');

            return version_compare($version, self::pad($constraint, 1), '>=')
                && version_compare($version, $upper, '<');
        }

        if (preg_match('/^~(\d+)\.(\d+)(?:\.(\d+))?$/', $constraint, $m)) {
            $upper = isset($m[3])
                ? ($m[1].'.'.((int) $m[2] + 1).'.0')
                : (((int) $m[1] + 1).'.0.0');

            return version_compare($version, self::pad($constraint, 1), '>=')
                && version_compare($version, $upper, '<');
        }

        if (preg_match('/^(>=|<=|>|<|=)\s*(\d[\w.\-]*)$/', $constraint, $m)) {
            return version_compare($version, $m[2], $m[1] === '=' ? '==' : $m[1]);
        }

        return version_compare($version, $constraint, '==');
    }

    /**
     * Strip a leading constraint operator and normalise "1.2" to "1.2.0".
     */
    protected static function pad(string $constraint, int $offset = 0): string
    {
        $raw = substr($constraint, $offset);
        $parts = explode('.', $raw);

        while (count($parts) < 3) {
            $parts[] = '0';
        }

        return implode('.', $parts);
    }
}
