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
        'app_id', 'name', 'description', 'icon', 'color', 'menu_order',
        'version', 'available_version', 'manifest_hash',
        'provider', 'status', 'installed_at', 'upgraded_at', 'last_upgrade_error',
    ];

    protected $casts = [
        'installed_at' => 'datetime',
        'upgraded_at' => 'datetime',
        'menu_order' => 'integer',
    ];

    /**
     * Tile colours for apps whose manifest does not pick one. The app id
     * decides which, so an app keeps the same colour forever without
     * anybody having to configure it.
     */
    public const PALETTE = [
        '#4f46e5', '#0891b2', '#059669', '#d97706',
        '#dc2626', '#7c3aed', '#db2777', '#0284c7',
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

    /**
     * What goes inside the launcher tile: the manifest's icon, or the app's
     * initials as a readable fallback.
     */
    public function iconLabel(): string
    {
        if ($this->icon !== null && $this->icon !== '') {
            return $this->icon;
        }

        $words = preg_split('/[\s\-_]+/', (string) $this->name) ?: [];
        $initials = '';

        foreach (array_slice(array_filter($words), 0, 2) as $word) {
            $initials .= mb_strtoupper(mb_substr($word, 0, 1));
        }

        return $initials !== '' ? $initials : mb_strtoupper(mb_substr($this->app_id, 0, 1));
    }

    public function tileColor(): string
    {
        if ($this->color !== null && preg_match('/^#[0-9a-fA-F]{6}$/', $this->color)) {
            return $this->color;
        }

        return self::PALETTE[crc32($this->app_id) % count(self::PALETTE)];
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
