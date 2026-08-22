<?php

namespace App\Platform\Contracts;

/**
 * Optional contract for apps that need custom cleanup on uninstall
 * (e.g. dropping their own tables).
 */
interface Uninstallable
{
    public function uninstall(): void;
}
