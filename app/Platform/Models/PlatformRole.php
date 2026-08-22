<?php

namespace App\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PlatformRole extends Model
{
    protected $fillable = ['name', 'slug', 'is_super_admin'];

    protected $casts = ['is_super_admin' => 'boolean'];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            PlatformAppPermission::class,
            'platform_role_permissions',
            'platform_role_id',
            'platform_app_permission_id'
        );
    }

    public function hasPermission(string $name): bool
    {
        return $this->permissions->contains(fn ($p) => $p->name === $name);
    }

    public function syncPermissionIds(array $ids): void
    {
        $this->permissions()->sync($ids);
    }
}
