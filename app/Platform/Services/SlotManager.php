<?php

namespace App\Platform\Services;

use App\Platform\Contracts\ExtensionSlot;
use Illuminate\Support\Facades\View;

class SlotManager implements ExtensionSlot
{
    protected array $slots = [];

    public function register(string $slot, callable $callback): void
    {
        $this->slots[$slot][] = $callback;
    }

    public function render(string $slot, array $context = []): string
    {
        if (!isset($this->slots[$slot])) {
            return '';
        }

        $html = '';
        foreach ($this->slots[$slot] as $callback) {
            $html .= call_user_func($callback, $context);
        }

        return $html;
    }
}
