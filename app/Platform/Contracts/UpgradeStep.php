<?php

namespace App\Platform\Contracts;

use App\Platform\Upgrades\UpgradeContext;

/**
 * A data migration bound to one version of one app. Steps live in
 * `apps/{id}/upgrades/{version}/PreUpgrade.php` and `PostUpgrade.php`;
 * `pre` steps run before the app's schema migrations, `post` steps after.
 *
 * Steps are resolved through the container, so they may type-hint their
 * dependencies in the constructor.
 */
interface UpgradeStep
{
    public function run(UpgradeContext $context): void;
}
