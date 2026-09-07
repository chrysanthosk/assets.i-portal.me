<?php

namespace App\Support;

use App\Models\PortalSetting;

/**
 * Portal-wide UI switches.
 */
class Portal
{
    public const SETTING_ADVANCED = 'advanced_mode';

    private static ?bool $advanced = null;

    /**
     * Advanced mode shows the multi-user / bookkeeping modules (users, permission
     * sets, owner entities, tags, currencies & FX, audit log). Off by default: a
     * single owner managing their own properties never needs them.
     */
    public static function advanced(): bool
    {
        if (self::$advanced === null) {
            try {
                self::$advanced = PortalSetting::get(self::SETTING_ADVANCED, '0') === '1';
            } catch (\Throwable $e) {
                self::$advanced = false; // no DB yet
            }
        }

        return self::$advanced;
    }

    public static function setAdvanced(bool $on): void
    {
        PortalSetting::set(self::SETTING_ADVANCED, $on ? '1' : '0');
        self::$advanced = $on;
    }

    public static function flush(): void
    {
        self::$advanced = null;
    }
}
