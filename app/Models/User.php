<?php

namespace App\Models;

use App\Platform\Models\PlatformAppPermission;
use App\Platform\Models\PlatformRole;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

#[Fillable(['name', 'email', 'password', 'platform_role_id', 'is_active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(PlatformRole::class, 'platform_role_id');
    }

    public function isSuperAdmin(): bool
    {
        return (bool) $this->role?->is_super_admin;
    }

    /**
     * Permission names granted to this user through their role.
     *
     * @return Collection<int, string>
     */
    public function permissionNames(): Collection
    {
        if ($this->isSuperAdmin()) {
            return PlatformAppPermission::query()->pluck('name');
        }

        return $this->role?->permissions()->pluck('name') ?? collect();
    }

    public function hasPermission(string $name): bool
    {
        return $this->permissionNames()->contains($name);
    }
}
