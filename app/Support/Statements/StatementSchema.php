<?php

namespace App\Support\Statements;

/**
 * What we pull out of a monthly statement from a property manager (Airbnb /
 * short-let operators, letting agents). All strings, "" = unknown — see
 * DeedSchema for why.
 */
class StatementSchema
{
    public const NUMERIC = ['gross_income', 'management_fee', 'expenses', 'net_payable'];

    /** @return array<string, mixed> */
    public static function schema(): array
    {
        $str = fn (string $d = '') => ['type' => 'string', 'description' => trim($d.' Empty string if not on the document.')];
        $num = fn (string $d = '') => ['type' => 'string', 'description' => trim($d.' Plain number as digits (e.g. "4119.65"); empty string if not present.')];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['management_company', 'property_reference', 'owner_name', 'period_start', 'period_end', 'statement_date',
                'currency', 'gross_income', 'management_fee', 'expenses', 'net_payable', 'notes', 'warnings'],
            'properties' => [
                'management_company' => $str('Name of the property manager / agent issuing the statement.'),
                'property_reference' => $str('Property name or reference as printed, e.g. "CHRYSANTHOS_1BR_DIFC_PT_2307".'),
                'owner_name' => $str(),
                'period_start' => $str('First day of the period covered, YYYY-MM-DD.'),
                'period_end' => $str('Last day of the period covered, YYYY-MM-DD.'),
                'statement_date' => $str('Date the statement was issued, YYYY-MM-DD.'),
                'currency' => $str('ISO code, e.g. AED, EUR.'),
                'gross_income' => $num('Total rental / accommodation income before fees (e.g. "Accommodation" total).'),
                'management_fee' => $num('Total management / commission fees including any tax on them.'),
                'expenses' => $num('Other expenses deducted (cleaning, repairs, supplies) excluding the management fee.'),
                'net_payable' => $num('Amount actually payable to the owner / host after all deductions.'),
                'notes' => $str('Anything notable: adjustments, contributions, one-off items.'),
                'warnings' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Anything unreadable, ambiguous, or guessed'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public static function normalize(array $raw): array
    {
        $out = [];
        foreach ($raw as $k => $v) {
            if ($k === 'warnings') {
                $out[$k] = is_array($v) ? array_values(array_filter(array_map('strval', $v), fn ($w) => trim($w) !== '')) : [];

                continue;
            }
            if (is_string($v)) {
                $v = trim($v);
                if ($v === '') {
                    $out[$k] = null;

                    continue;
                }
            }
            if (in_array($k, self::NUMERIC, true)) {
                $clean = is_string($v) ? str_replace(',', '', preg_replace('/[^\d.,-]/', '', $v) ?? '') : $v;
                $out[$k] = is_numeric($clean) ? round((float) $clean, 2) : null;

                continue;
            }
            $out[$k] = $v;
        }

        return $out;
    }
}
