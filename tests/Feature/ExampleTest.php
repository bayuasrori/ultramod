<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * The root URL redirects to the platform dashboard.
     */
    public function test_the_application_redirects_to_the_platform_dashboard(): void
    {
        $this->get('/')->assertRedirect(route('platform.index'));
    }
}
