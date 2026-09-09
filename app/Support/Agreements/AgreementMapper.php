<?php

namespace App\Support\Agreements;

use App\Models\Asset;
use App\Models\Tenant;
use Carbon\Carbon;

/**
 * Turns extracted contract terms into a prefilled agreement form.
 */
class AgreementMapper
{
    /**
     * @param  array<string, mixed>  $c
     * @return array<string, mixed>
     */
    public static function toRentalAttributes(array $c): array
    {
        $installments = [];
        foreach ($c['installments'] ?? [] as $i) {
            if (empty($i['date'])) {
                continue;
            }
            try {
                $d = Carbon::parse($i['date']);
            } catch (\Throwable $e) {
                continue;
            }
            $installments[] = ['day' => (int) $d->format('j'), 'month' => (int) $d->format('n'), 'amount' => (float) ($i['amount'] ?? 0),
                'label' => $i['label'] ?? ($i['percent'] ? $i['percent'].' % of annual' : null)];
        }
        $schedule = ($c['schedule_type'] ?? null) === 'installments' || ($installments && empty($c['monthly_amount'])) ? 'installments' : 'monthly';

        $notes = array_filter([
            ! empty($c['deposit_amount']) ? 'Deposit: '.($c['currency'] ?? '').' '.number_format((float) $c['deposit_amount'], 2) : null,
            ! empty($c['commission_terms']) ? 'Commission: '.$c['commission_terms'] : null,
            ($c['amounts_exclude_vat'] ?? null) === 'yes' ? 'Amounts exclude VAT.' : null,
            ! empty($c['obligations']) ? 'Obligations: '.$c['obligations'] : null,
            ! empty($c['notes']) ? $c['notes'] : null,
        ]);

        return [
            'asset_id' => self::guessAssetId($c),
            'tenant_id' => self::guessTenantId($c['counterparty'] ?? null),
            'tenant_name' => $c['counterparty'] ?? null,
            'tenant_email' => $c['counterparty_email'] ?? null,
            'tenant_phone' => $c['counterparty_phone'] ?? null,
            'tenant_id_number' => $c['counterparty_id_number'] ?? null,
            'agreement_start_date' => self::date($c['contract_start'] ?? null),
            'agreement_end_date' => self::date($c['contract_end'] ?? null),
            'rent_type' => ($c['counterparty_type'] ?? null) === 'management_company' ? 'Other' : 'Long-term',
            'is_active' => 1,
            'payment_schedule' => $schedule,
            'paid_in_arrears' => 0,
            'due_day' => ! empty($c['payment_due_day']) ? max(1, min(28, (int) $c['payment_due_day'])) : null,
            'amount' => $schedule === 'monthly' ? ($c['monthly_amount'] ?? 0) : ($c['annual_amount'] ?? array_sum(array_column($installments, 'amount'))),
            'installments' => $installments,
            'currency' => strtoupper((string) ($c['currency'] ?? 'EUR')) ?: 'EUR',
            'channel' => ($c['counterparty_type'] ?? null) === 'management_company' ? $c['counterparty'] : null,
            'notes' => $notes ? implode("\n", $notes) : null,
        ];
    }

    /** @param array<string, mixed> $c */
    public static function guessAssetId(array $c): ?int
    {
        $assets = Asset::query()->get(['id', 'name', 'address']);
        if ($assets->isEmpty()) {
            return null;
        }
        $hay = fn ($a) => mb_strtolower($a->name.' '.$a->address);

        foreach (array_filter([$c['property_reference'] ?? null, $c['property_address'] ?? null]) as $n) {
            foreach (preg_split('/[\s,\/()-]+/', (string) $n) as $word) {
                $w = mb_strtolower(trim($word));
                if (mb_strlen($w) < 3 || is_numeric($w)) {
                    continue;
                }
                $hit = $assets->first(fn ($a) => str_contains($hay($a), $w));
                if ($hit) {
                    return $hit->id;
                }
            }
            if (preg_match('/\b(\d{1,4})\b/', (string) $n, $m)) {
                $hit = $assets->first(fn ($a) => str_contains($a->name, $m[1]));
                if ($hit) {
                    return $hit->id;
                }
            }
        }

        return null;
    }

    public static function guessTenantId(?string $name): ?int
    {
        if (! $name) {
            return null;
        }

        return Tenant::query()->where('name', 'like', '%'.trim($name).'%')->value('id');
    }

    public static function date(?string $v): ?string
    {
        if (! $v) {
            return null;
        }
        try {
            return Carbon::parse($v)->toDateString();
        } catch (\Throwable $e) {
            return null;
        }
    }
}
