<?php

namespace App\Support\Agreements;

/**
 * What we read from a rental / management contract. All strings ("" = unknown)
 * to stay within Anthropic's union-type limit; normalize() types them.
 */
class AgreementSchema
{
    /** @return array<string, mixed> */
    public static function schema(): array
    {
        $str = fn (string $d = '') => ['type' => 'string', 'description' => trim($d.' Empty string if not in the document.')];
        $num = fn (string $d = '') => ['type' => 'string', 'description' => trim($d.' Plain number as digits without thousands separators; empty string if absent.')];

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'required' => ['counterparty', 'counterparty_type', 'counterparty_email', 'counterparty_phone', 'counterparty_id_number', 'property_reference', 'property_address', 'contract_start', 'contract_end',
                'signed_on', 'auto_renews', 'currency', 'schedule_type', 'monthly_amount', 'annual_amount', 'amounts_exclude_vat',
                'installments', 'commission_terms', 'obligations', 'notes', 'warnings'],
            'properties' => [
                'counterparty' => $str('The other party: tenant name, or the management / holiday-lettings company.'),
                'counterparty_type' => ['type' => 'string', 'enum' => ['tenant', 'management_company', 'unknown']],
                'counterparty_email' => $str('Email of the counterparty (first one if several).'),
                'counterparty_phone' => $str('Phone of the counterparty (first one if several).'),
                'counterparty_id_number' => $str('ID / passport / company registration number of the counterparty.'),
                'property_reference' => $str('Property / unit name as written, e.g. "Prengos Villa 24".'),
                'property_address' => $str(),
                'contract_start' => $str('First day of the agreement period, YYYY-MM-DD.'),
                'contract_end' => $str('Last day of the agreement period, YYYY-MM-DD.'),
                'signed_on' => $str('Signature date, YYYY-MM-DD.'),
                'auto_renews' => ['type' => 'string', 'enum' => ['yes', 'no', 'unknown']],
                'currency' => $str('ISO code, e.g. EUR.'),
                'schedule_type' => ['type' => 'string', 'enum' => ['monthly', 'installments', 'unknown'],
                    'description' => 'monthly = a fixed rent every month; installments = a set of dated payments per contract year'],
                'monthly_amount' => $num('Monthly rent when schedule_type is monthly.'),
                'annual_amount' => $num('Total guaranteed amount per contract year (sum of the instalments), when schedule_type is installments.'),
                'amounts_exclude_vat' => ['type' => 'string', 'enum' => ['yes', 'no', 'unknown']],
                'installments' => [
                    'type' => 'array',
                    'description' => 'Payments of the FIRST contract year, in order. They repeat on the same day/month each year.',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'required' => ['date', 'amount', 'percent', 'label'],
                        'properties' => [
                            'date' => $str('YYYY-MM-DD of the payment in the first contract year.'),
                            'amount' => $num('Amount excluding VAT. "0" if variable (e.g. commission).'),
                            'percent' => $num('Percentage of the annual amount, if stated.'),
                            'label' => $str('Short description, e.g. "15 % of guarantee" or "Year-end commission".'),
                        ],
                    ],
                ],
                'commission_terms' => $str('Any revenue share / commission clause, in one sentence.'),
                'obligations' => $str('Key obligations for the owner (insurance, licences, maintenance), in a few sentences.'),
                'notes' => $str(),
                'warnings' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'Anything unreadable, ambiguous or guessed'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public static function normalize(array $raw): array
    {
        $num = fn ($v) => is_numeric($c = str_replace(',', '', preg_replace('/[^\d.,-]/', '', (string) $v) ?? '')) ? round((float) $c, 2) : null;
        $out = [];
        foreach ($raw as $k => $v) {
            $out[$k] = match (true) {
                $k === 'installments' && is_array($v) => array_values(array_map(fn ($i) => [
                    'date' => trim((string) ($i['date'] ?? '')) ?: null,
                    'amount' => $num($i['amount'] ?? ''),
                    'percent' => $num($i['percent'] ?? ''),
                    'label' => trim((string) ($i['label'] ?? '')) ?: null,
                ], $v)),
                $k === 'warnings' => is_array($v) ? array_values(array_filter(array_map('strval', $v), fn ($w) => trim($w) !== '')) : [],
                in_array($k, ['monthly_amount', 'annual_amount'], true) => $num($v),
                in_array($k, ['counterparty_type', 'auto_renews', 'schedule_type', 'amounts_exclude_vat'], true) => ($v === '' || $v === 'unknown') ? null : $v,
                is_string($v) => (trim($v) === '' ? null : trim($v)),
                default => $v,
            };
        }

        return $out;
    }
}
