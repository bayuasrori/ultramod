<?php

namespace Tests;

use App\Models\User;
use App\Platform\Models\PlatformRole;
use Database\Seeders\PlatformRoleSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Authenticate as a super-admin user. Creates the role and user if they
     * do not exist yet (platform tables must already be migrated).
     */
    protected function actingAsAdmin(): void
    {
        $this->seed(PlatformRoleSeeder::class);

        $user = User::firstOrCreate(
            ['email' => 'test-admin@example.com'],
            [
                'name' => 'Test Admin',
                'password' => 'password',
                'platform_role_id' => PlatformRole::where('slug', 'admin')->value('id'),
                'is_active' => true,
            ],
        );

        $this->actingAs($user);
    }
}
