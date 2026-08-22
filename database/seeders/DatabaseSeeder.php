<?php

namespace Database\Seeders;

use App\Models\User;
use App\Platform\Models\PlatformRole;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(PlatformRoleSeeder::class);

        $adminRole = PlatformRole::where('slug', 'admin')->first();

        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => 'password',
                'platform_role_id' => $adminRole?->id,
                'is_active' => true,
            ],
        );
    }
}
