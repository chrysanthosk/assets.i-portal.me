<?php

namespace App\Support\Deeds;

use App\Models\AssetType;
use Carbon\Carbon;

/**
 * Turns extracted deed data into a prefilled asset form.
 */
class DeedMapper
{
    /** property_type → candidate AssetType names, first match wins */
    private const TYPE_CANDIDATES = [
        'apartment' => ['Apartment', 'Flat'],
        'house' => ['House', 'Villa'],
        'maisonette' => ['Mezonete', 'Maisonette', 'House'],
        'land' => ['Land', 'Plot', 'Field'],
        'commercial' => ['Commercial', 'Shop', 'Retail'],
        'office' => ['Office', 'Commercial'],
    ];

    /**
     * @param  array<string, mixed>  $deed
     * @return array<string, mixed> asset attributes (form field names)
     */
    public static function toAssetAttributes(array $deed): array
    {
        $owner = $deed['owners'][0] ?? null;

        return [
            'name' => self::suggestName($deed),
            'asset_type_id' => self::assetTypeId($deed['property_type'] ?? null),
            'address' => trim(implode(', ', array_filter([
                $deed['street_address'] ?? null,
                $deed['building_name'] ?? null,
                isset($deed['unit_number']) && $deed['unit_number'] !== null ? 'No. '.$deed['unit_number'] : null,
            ]))) ?: null,
            'city' => $deed['municipality_community'] ?? $deed['district'] ?? null,
            'country' => 'Cyprus',
            'currency' => $deed['valuations'][0]['currency'] ?? 'EUR',
            'ownership_percentage' => self::sharePct($owner),
            'title_deed' => 1,
            'title_deed_number' => $deed['registration_number'] ?? null,
            'title_deed_date' => self::date($deed['registration_date'] ?? null),
            'size_sqm' => $deed['enclosed_area_sqm'] ?? null,
            'land_sqm' => $deed['plot_area_sqm'] ?? null,
            'parking' => (int) (($deed['parking_spaces'] ?? 0) > 0),
            'status' => 'Vacant',
            'notes' => self::notes($deed),
        ];
    }

    /** @param array<string, mixed> $deed */
    public static function suggestName(array $deed): string
    {
        $building = $deed['building_name'] ?? null;
        $unit = $deed['unit_number'] ?? null;
        $type = ucfirst((string) ($deed['property_type'] ?? 'property'));

        if ($building) {
            return trim($building.($unit ? ' – No. '.$unit : ''));
        }

        $where = $deed['street_address'] ?? $deed['locality'] ?? $deed['municipality_community'] ?? null;

        return trim($type.($where ? ', '.$where : ''));
    }

    public static function assetTypeId(?string $propertyType): ?int
    {
        $types = AssetType::query()->orderBy('sort_order')->orderBy('name')->get(['id', 'name', 'is_active']);
        if ($types->isEmpty()) {
            return null;
        }

        foreach (self::TYPE_CANDIDATES[$propertyType] ?? [] as $candidate) {
            $match = $types->first(fn ($t) => strcasecmp($t->name, $candidate) === 0);
            if ($match) {
                return $match->id;
            }
        }

        return $types->firstWhere('is_active', true)?->id ?? $types->first()->id;
    }

    /** @param array<string, mixed>|null $owner */
    public static function sharePct(?array $owner): float
    {
        if (! $owner) {
            return 100;
        }
        if (isset($owner['share_pct']) && is_numeric($owner['share_pct'])) {
            return max(0, min(100, (float) $owner['share_pct']));
        }

        $share = trim((string) ($owner['share'] ?? ''));
        if ($share === '' || preg_match('/^(ΟΛΟ|OLO|WHOLE|ALL|1\/1)$/iu', $share)) {
            return 100;
        }
        if (preg_match('#^(\d+)\s*/\s*(\d+)$#', $share, $m) && (int) $m[2] > 0) {
            return round((int) $m[1] / (int) $m[2] * 100, 2);
        }
        if (preg_match('/^(\d+(?:[.,]\d+)?)\s*%$/', $share, $m)) {
            return (float) str_replace(',', '.', $m[1]);
        }

        return 100;
    }

    public static function date(?string $value): ?string
    {
        if (! $value) {
            return null;
        }
        try {
            return Carbon::parse($value)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /** @param array<string, mixed> $deed */
    private static function notes(array $deed): ?string
    {
        $lines = array_filter([
            $deed['property_description'] ?? null,
            isset($deed['rights_and_encumbrances']) && $deed['rights_and_encumbrances'] ? 'Rights: '.$deed['rights_and_encumbrances'] : null,
            isset($deed['notes']) && $deed['notes'] ? 'Deed notes: '.$deed['notes'] : null,
        ]);

        return $lines ? implode("\n\n", $lines) : null;
    }
}
