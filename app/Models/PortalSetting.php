<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class PortalSetting extends Model
{
    protected $fillable = ['key', 'value'];

    private const CACHE_KEY = 'portal_settings.all';

    /** All settings as key => value, cached (every page reads the portal name). */
    public static function all_(): array
    {
        return Cache::remember(self::CACHE_KEY, 300, fn () => static::query()->pluck('value', 'key')->all());
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = self::all_()[$key] ?? null;

        return ($value === null || $value === '') ? $default : $value;
    }

    public static function set(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget(self::CACHE_KEY);
    }

    public static function name(): string
    {
        return self::get('portal_name', 'assets.i-portal.me');
    }
}
