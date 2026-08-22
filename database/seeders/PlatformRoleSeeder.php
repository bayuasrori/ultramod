<?php

namespace Database\Seeders;

use App\Platform\Models\PlatformAppPermission;
use App\Platform\Models\PlatformRole;
use Illuminate\Database\Seeder;

class PlatformRoleSeeder extends Seeder
{
    public function run(): void
    {
        $admin = PlatformRole::firstOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Administrator', 'is_super_admin' => true],
        );

        PlatformRole::firstOrCreate(
            ['slug' => 'member'],
            ['name' => 'Member', 'is_super_admin' => false],
        );

        // Core permission catalogue entry so the platform's own permissions
        // flow through the same mechanism as app permissions.
        foreach (['platform.manage'] as $permission) {
            PlatformAppPermission::firstOrCreate(
                ['app_id' => 'platform', 'name' => $permission],
            );
        }

        $admin->syncPermissionIds(
            PlatformAppPermission::where('app_id', 'platform')->pluck('id')->all()
        );
    }
}
