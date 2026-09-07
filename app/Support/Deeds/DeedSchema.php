<?php

namespace App\Support\Deeds;

/**
 * JSON schema for what we pull out of a title deed. Written for the Cyprus
 * Department of Lands and Surveys "Κτηματική Σελίδα" (unit / plot sheet) but
 * generic enough for other registries. Every field is nullable: unknown = null.
 */
class DeedSchema
{
    /** @return array<string, mixed> */
    public static function schema(): array
    {
        $str = ['type' => ['string', 'null']];
        $num = ['type' => ['number', 'null']];
        $int = ['type' => ['integer', 'null']];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => [
                'document_type', 'registration_number', 'district', 'municipality_community', 'parish',
                'locality', 'street_address', 'building_name', 'unit_number', 'floor',
                'sheet', 'plan', 'section', 'plot', 'registration_date', 'file_number', 'issue_date',
                'owners', 'property_type', 'property_description', 'parking_spaces', 'storage_rooms',
                'enclosed_area_sqm', 'covered_veranda_sqm', 'uncovered_veranda_sqm', 'plot_area_sqm',
                'common_property_share_pct', 'valuations', 'rights_and_encumbrances', 'notes',
                'source_language', 'warnings',
            ],
            'properties' => [
                'document_type' => $str + ['description' => 'e.g. "Unit sheet (Κτηματική Σελίδα Μονάδας)", "Title deed", "Plot sheet"'],
                'registration_number' => $str + ['description' => 'Registration number (Αριθμός Εγγραφής), e.g. "0/8443"'],
                'district' => $str + ['description' => 'District name in English, e.g. "Paphos"'],
                'municipality_community' => $str + ['description' => 'Municipality / community name in English or transliterated'],
                'parish' => $str,
                'locality' => $str + ['description' => 'Τοποθεσία'],
                'street_address' => $str + ['description' => 'Street name (and number if present), transliterated to Latin characters'],
                'building_name' => $str + ['description' => 'Όνομα Οικοδομής, keep as written'],
                'unit_number' => $str + ['description' => 'Door / unit number (Αρ. Θύρας)'],
                'floor' => $str + ['description' => 'Floor of the unit, e.g. "1st floor", "ground floor"'],
                'sheet' => $str + ['description' => 'Φύλλο'],
                'plan' => $str + ['description' => 'Σχέδιο'],
                'section' => $str + ['description' => 'Τμήμα'],
                'plot' => $str + ['description' => 'Τεμάχιο, e.g. "ΕΠΙ 1868"'],
                'registration_date' => $str + ['description' => 'Ημερομηνία Εγγραφής as YYYY-MM-DD'],
                'file_number' => $str + ['description' => 'Αριθμός Φακέλου'],
                'issue_date' => $str + ['description' => 'Ημερομηνία Έκδοσης as YYYY-MM-DD'],
                'owners' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['name', 'address', 'share', 'share_pct', 'id_number'],
                        'properties' => [
                            'name' => $str + ['description' => 'Owner name as written (keep original script)'],
                            'address' => $str,
                            'share' => $str + ['description' => 'Μερίδιο as written, e.g. "ΟΛΟ", "1/2"'],
                            'share_pct' => $num + ['description' => 'Share as a percentage: ΟΛΟ = 100, 1/2 = 50'],
                            'id_number' => $str + ['description' => 'Διακριτικός Αριθμός'],
                        ],
                    ],
                ],
                'property_type' => ['type' => ['string', 'null'], 'enum' => ['apartment', 'house', 'maisonette', 'land', 'commercial', 'office', 'other', null]],
                'property_description' => $str + ['description' => 'Περιγραφή Ακίνητης Ιδιοκτησίας translated to English, one paragraph, including parking/storage rights'],
                'parking_spaces' => $int,
                'storage_rooms' => $int,
                'enclosed_area_sqm' => $num + ['description' => 'Κλειστός χώρος'],
                'covered_veranda_sqm' => $num + ['description' => 'Καλυμμένες βεράντες'],
                'uncovered_veranda_sqm' => $num + ['description' => 'Ακάλυπτες βεράντες'],
                'plot_area_sqm' => $num + ['description' => 'Έκταση τεμαχίου for land / houses'],
                'common_property_share_pct' => $num + ['description' => 'Μερίδιο στην κοινόκτητη ιδιοκτησία, e.g. 2.97'],
                'valuations' => [
                    'type' => 'array',
                    'description' => 'Αξία Γεν. Εκτίμησης entries',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['date', 'amount', 'currency'],
                        'properties' => [
                            'date' => $str + ['description' => 'YYYY-MM-DD'],
                            'amount' => $num,
                            'currency' => $str + ['description' => 'ISO code, e.g. EUR'],
                        ],
                    ],
                ],
                'rights_and_encumbrances' => $str + ['description' => 'Δικαιώματα / Δουλείες and Κοινά Δικαιώματα Χρήσης, translated'],
                'notes' => $str + ['description' => 'Σημειώσεις, translated'],
                'source_language' => $str,
                'warnings' => [
                    'type' => 'array',
                    'description' => 'Anything unreadable, ambiguous, or guessed',
                    'items' => ['type' => 'string'],
                ],
            ],
        ];
    }
}
