<?php

namespace App\Platform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformApp extends Model
{
    public const STATUS_DISCOVERED = 'discovered';

    public const STATUS_INSTALLED = 'installed';

    public const STATUS_ENABLED = 'enabled';

    public const STATUS_DISABLED = 'disabled';

    protected $table = 'platform_apps';

    protected $fillable = [
        'app_id', 'name', 'version', 'available_version', 'manifest_hash',
        'provider', 'status', 'installed_at', 'upgraded_at', 'last_upgrade_error',
    ];

    protected $casts = [
        'installed_at' => 'datetime',
        'upgraded_at' => 'datetime',
    ];

    /** @return array<int, string> */
    public static function statuses(): array
    {
        return [
            self::STATUS_DISCOVERED,
            self::STATUS_INSTALLED,
            self::STATUS_ENABLED,
            self::STATUS_DISABLED,
        ];
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(PlatformAppPermission::class, 'app_id', 'app_id');
    }

    public function upgrades(): HasMany
    {
        return $this->hasMany(PlatformAppUpgrade::class, 'app_id', 'app_id');
    }

    public function isLive(): bool
    {
        return in_array($this->status, [self::STATUS_INSTALLED, self::STATUS_ENABLED], true);
    }

    /**
     * The manifest on disk offers a newer version than the one that has
     * actually been migrated.
     */
    public function hasUpgrade(): bool
    {
        if (! $this->isLive() || $this->available_version === null) {
            return false;
        }

        return version_compare($this->available_version, $this->version, '>');
    }
}
