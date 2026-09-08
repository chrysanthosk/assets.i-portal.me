<?php

namespace App\Support;

use App\Models\Asset;
use App\Models\AssetExpense;
use App\Models\AssetRental;
use App\Models\FxRate;
use App\Models\PortalSetting;
use App\Models\RentalPayment;
use Illuminate\Support\Facades\Cache;

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
            self::$base = strtoupper((string) (PortalSetting::get('base_currency') ?: 'EUR'));
        }

        return self::$base;
    }

    /**
     * True when any money in the system is in a currency other than the base,
     * so the Currencies & FX page is worth showing even in simple mode. Cached
     * briefly; the FX settings page clears it on save.
     */
    public static function multiCurrencyInUse(): bool
    {
        return (bool) Cache::remember('fx.multi_currency', 300, function () {
            $base = self::base();

            return RentalPayment::query()->where('currency', '!=', $base)->exists()
                || AssetExpense::query()->where('currency', '!=', $base)->exists()
                || AssetRental::query()->where('currency', '!=', $base)->exists();
        });
    }

    /**
     * Currencies offered in forms: base, every currency with a rate, anything
     * already used by assets/agreements/payments/expenses, and common defaults.
     *
     * @return array<int, string>
     */
    public static function currencies(): array
    {
        $list = array_merge(
            [self::base()],
            array_keys(self::rates()),
            Asset::query()->distinct()->pluck('currency')->all(),
            AssetRental::query()->distinct()->pluck('currency')->all(),
            ['EUR', 'USD', 'GBP', 'AED'],
        );
        $list = array_values(array_unique(array_filter(array_map(fn ($c) => strtoupper(trim((string) $c)), $list))));
        usort($list, fn ($a, $b) => ($a === self::base() ? 0 : 1) <=> ($b === self::base() ? 0 : 1) ?: strcmp($a, $b));

        return $list;
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
        Cache::forget('fx.multi_currency');
    }
}
