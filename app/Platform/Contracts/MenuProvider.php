<?php

namespace App\Platform\Contracts;

interface MenuProvider
{
    /**
     * Menu entries contributed by this app to the platform navigation.
     *
     * @return array<int, array{label: string, route: string}>
     */
    public function menu(): array;
}
