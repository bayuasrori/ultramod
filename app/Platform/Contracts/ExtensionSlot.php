<?php

namespace App\Platform\Contracts;

interface ExtensionSlot
{
    public function render(string $slot, array $context = []): string;
    public function register(string $slot, callable $callback): void;
}
