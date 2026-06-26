<?php

namespace App\Support;

use App\Models\FxRate;
use App\Models\PortalSetting;

/**
 * Lightweight currency conversion to a configurable base currency.
 *
 * A rate of 0.92 for "USD" means 1 USD = 0.92 base units. The base currency
 * always has an implicit rate of 1. Currencies without a configured rate are
 * returned unconverted (and reported via unknownCurrencies()).
 */
class Fx
{
    private static ?string $base = null;

    private static ?array $rates = null;

    private static array $unknown = [];

    public static function base(): string
    {
        if (self::$base === null) {
            self::$base = strtoupper((string) (PortalSetting::where('key', 'base_currency')->value('value') ?: 'EUR'));
        }

        return self::$base;
    }

    private static function rates(): array
    {
        if (self::$rates === null) {
            self::$rates = [];
            foreach (FxRate::all() as $rate) {
                self::$rates[strtoupper($rate->currency)] = (float) $rate->rate_to_base;
            }
            self::$rates[self::base()] = 1.0;
        }

        return self::$rates;
    }

    /**
     * Convert an amount in the given currency to the base currency.
     */
    public static function toBase(float $amount, ?string $currency): float
    {
        $currency = strtoupper((string) ($currency ?: self::base()));
        $rates = self::rates();

        if (! array_key_exists($currency, $rates)) {
            self::$unknown[$currency] = true;

            return $amount; // unconverted
        }

        return $amount * $rates[$currency];
    }

    /**
     * Currencies encountered during conversion that had no configured rate.
     */
    public static function unknownCurrencies(): array
    {
        return array_keys(self::$unknown);
    }

    /**
     * Reset memoised state (used in tests).
     */
    public static function flush(): void
    {
        self::$base = null;
        self::$rates = null;
        self::$unknown = [];
    }
}
