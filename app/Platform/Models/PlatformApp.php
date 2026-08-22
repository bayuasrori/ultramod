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

    protected $fillable = ['app_id', 'name', 'version', 'provider', 'status', 'installed_at'];

    protected $casts = [
        'installed_at' => 'datetime',
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
}
