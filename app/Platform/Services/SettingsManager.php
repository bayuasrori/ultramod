<?php
namespace App\Platform\Services;

use App\Platform\Models\PlatformSetting;
use Illuminate\Support\Facades\Cache;

class SettingsManager
{
    public function get(string $key, mixed $default = null, ?string $appId = null): mixed
    {
        $cacheKey = $this->cacheKey($key, $appId);
        
        return Cache::rememberForever($cacheKey, function () use ($key, $default, $appId) {
            $setting = PlatformSetting::where('app_id', $appId)->where('key', $key)->first();
            return $setting ? $setting->value : $default;
        });
    }

    public function set(string $key, mixed $value, ?string $appId = null): void
    {
        PlatformSetting::updateOrCreate(
            ['app_id' => $appId, 'key' => $key],
            ['value' => $value]
        );
        
        Cache::forget($this->cacheKey($key, $appId));
    }
    
    protected function cacheKey(string $key, ?string $appId): string
    {
        return 'platform_setting_' . ($appId ?: 'global') . '_' . $key;
    }
}
