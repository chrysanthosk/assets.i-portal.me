<?php

namespace App\Support\Deeds;

/**
 * JSON schema for what we pull out of a title deed. Written for the Cyprus
 * Department of Lands and Surveys "Κτηματική Σελίδα" (unit / plot sheet) but
 * generic enough for other registries. Every field is nullable: unknown = null.
 */
class DeedSchema
{
    /** Fields that hold numbers (returned as strings by the model, converted in normalize()). */
    public const NUMERIC = [
        'parking_spaces', 'storage_rooms', 'enclosed_area_sqm', 'covered_veranda_sqm', 'uncovered_veranda_sqm',
        'plot_area_sqm', 'common_property_share_pct',
    ];

    public const INTEGER = ['parking_spaces', 'storage_rooms'];

    /**
     * Anthropic's structured-output compiler allows at most 16 union-typed
     * (nullable) fields, so every scalar here is a plain string: "" = unknown,
     * numbers as plain digits. normalize() turns them back into null/numbers.
     *
     * @return array<string, mixed>
     */
    public static function schema(): array
    {
        $str = fn (string $desc = '') => ['type' => 'string', 'description' => trim($desc.' Empty string if not on the document.')];
        $num = fn (string $desc = '') => ['type' => 'string', 'description' => trim($desc.' Plain number as digits (e.g. "81" or "2.97"); empty string if not on the document.')];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [
                'document_type', 'registry', 'country', 'registration_number', 'district', 'municipality_community', 'parish',
                'locality', 'street_address', 'building_name', 'unit_number', 'floor',
                'sheet', 'plan', 'section', 'plot', 'registration_date', 'file_number', 'issue_date',
                'owners', 'property_type', 'property_description', 'parking_spaces', 'storage_rooms',
                'enclosed_area_sqm', 'covered_veranda_sqm', 'uncovered_veranda_sqm', 'plot_area_sqm',
                'common_property_share_pct', 'valuations', 'rights_and_encumbrances', 'notes',
                'source_language', 'warnings',
            ],
            'properties' => [
                'document_type' => $str('e.g. "Unit sheet (Κτηματική Σελίδα Μονάδας)", "Title deed", "Plot sheet".'),
                'registry' => $str('Issuing registry, e.g. "Cyprus Department of Lands and Surveys", "DIFC Real Property Register", "Dubai Land Department".'),
                'country' => $str('Country the property is in, in English, e.g. "Cyprus", "United Arab Emirates".'),
                'registration_number' => $str('Registration number (Αριθμός Εγγραφής), e.g. "0/8443".'),
                'district' => $str('District name in English, e.g. "Paphos".'),
                'municipality_community' => $str('Municipality / community name in English or transliterated.'),
                'parish' => $str(),
                'locality' => $str('Τοποθεσία.'),
                'street_address' => $str('Street name (and number if present), transliterated to Latin characters.'),
                'building_name' => $str('Όνομα Οικοδομής, keep as written.'),
                'unit_number' => $str('Door / unit number (Αρ. Θύρας).'),
                'floor' => $str('Floor of the unit, e.g. "1st floor", "ground floor".'),
                'sheet' => $str('Φύλλο.'),
                'plan' => $str('Σχέδιο.'),
                'section' => $str('Τμήμα.'),
                'plot' => $str('Τεμάχιο, e.g. "ΕΠΙ 1868".'),
                'registration_date' => $str('Ημερομηνία Εγγραφής as YYYY-MM-DD.'),
                'file_number' => $str('Αριθμός Φακέλου.'),
                'issue_date' => $str('Ημερομηνία Έκδοσης as YYYY-MM-DD.'),
                'owners' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['name', 'address', 'share', 'share_pct', 'id_number'],
                        'properties' => [
                            'name' => $str('Owner name as written (keep original script).'),
                            'address' => $str(),
                            'share' => $str('Μερίδιο as written, e.g. "ΟΛΟ", "1/2".'),
                            'share_pct' => $num('Share as a percentage: ΟΛΟ = 100, 1/2 = 50.'),
                            'id_number' => $str('Διακριτικός Αριθμός.'),
                        ],
                    ],
                ],
                'property_type' => [
                    'type' => 'string',
                    'enum' => ['apartment', 'house', 'maisonette', 'land', 'commercial', 'office', 'other', 'unknown'],
                ],
                'property_description' => $str('Περιγραφή Ακίνητης Ιδιοκτησίας translated to English, one paragraph, including parking/storage rights.'),
                'parking_spaces' => $num('Count of ΧΩΡΟΣ ΣΤΑΘΜΕΥΣΗΣ entries.'),
                'storage_rooms' => $num('Count of ΑΠΟΘΗΚΗ entries.'),
                'enclosed_area_sqm' => $num('Κλειστός χώρος in m².'),
                'covered_veranda_sqm' => $num('Καλυμμένες βεράντες in m².'),
                'uncovered_veranda_sqm' => $num('Ακάλυπτες βεράντες in m².'),
                'plot_area_sqm' => $num('Έκταση τεμαχίου in m² for land / houses.'),
                'common_property_share_pct' => $num('Μερίδιο στην κοινόκτητη ιδιοκτησία, e.g. 2.97.'),
                'valuations' => [
                    'type' => 'array',
                    'description' => 'Αξία Γεν. Εκτίμησης entries',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['date', 'amount', 'currency'],
                        'properties' => [
                            'date' => $str('YYYY-MM-DD.'),
                            'amount' => $num(),
                            'currency' => $str('ISO code, e.g. EUR.'),
                        ],
                    ],
                ],
                'rights_and_encumbrances' => $str('Δικαιώματα / Δουλείες and Κοινά Δικαιώματα Χρήσης, translated.'),
                'notes' => $str('Σημειώσεις, translated.'),
                'source_language' => $str('ISO code of the document language, e.g. "el".'),
                'warnings' => [
                    'type' => 'array',
                    'description' => 'Anything unreadable, ambiguous, or guessed',
                    'items' => ['type' => 'string'],
                ],
            ],
        ];
    }

    /**
     * Convert the all-string model output into typed values: "" → null,
     * numeric strings → int/float, property_type "unknown" → null.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public static function normalize(array $raw): array
    {
        $out = [];
        foreach ($raw as $key => $value) {
            $out[$key] = match (true) {
                $key === 'owners' && is_array($value) => array_values(array_map(
                    fn ($o) => is_array($o) ? self::normalizeScalars($o, ['share_pct'], []) : $o, $value
                )),
                $key === 'valuations' && is_array($value) => array_values(array_map(
                    fn ($v) => is_array($v) ? self::normalizeScalars($v, ['amount'], []) : $v, $value
                )),
                $key === 'warnings' && is_array($value) => array_values(array_filter(array_map('strval', $value), fn ($w) => trim($w) !== '')),
                $key === 'property_type' => ($value === '' || $value === 'unknown') ? null : $value,
                default => self::scalar($key, $value, self::NUMERIC, self::INTEGER),
            };
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $numeric
     * @param  array<int, string>  $integer
     * @return array<string, mixed>
     */
    private static function normalizeScalars(array $row, array $numeric, array $integer): array
    {
        foreach ($row as $k => $v) {
            $row[$k] = self::scalar($k, $v, $numeric, $integer);
        }

        return $row;
    }

    /**
     * @param  array<int, string>  $numeric
     * @param  array<int, string>  $integer
     */
    private static function scalar(string $key, mixed $value, array $numeric, array $integer): mixed
    {
        if (is_string($value)) {
            $value = trim($value);
            if ($value === '') {
                return null;
            }
        }
        if ($value === null || ! in_array($key, $numeric, true)) {
            return $value;
        }

        $clean = is_string($value) ? str_replace([',', ' '], ['.', ''], preg_replace('/[^\d.,-]/', '', $value) ?? '') : $value;
        if (! is_numeric($clean)) {
            return null;
        }

        return in_array($key, $integer, true) ? (int) $clean : (float) $clean;
    }
}
