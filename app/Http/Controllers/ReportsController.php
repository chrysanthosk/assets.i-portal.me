<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetExpense;
use App\Models\RentalPayment;
use App\Support\Fx;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportsController extends Controller
{
    public function index(Request $request)
    {
        $year = $request->integer('year') ?: (int) now()->year;
        $report = $this->buildReport($year);

        $years = RentalPayment::query()->selectRaw('MIN(due_date) as first')->value('first');
        $firstYear = $years ? (int) substr((string) $years, 0, 4) : (int) now()->year;

        return view('reports.index', array_merge($report, [
            'year' => $year,
            'years' => range(max($firstYear, (int) now()->year - 10), (int) now()->year + 1),
            'base' => Fx::base(),
            'multiCurrency' => $this->usesMultipleCurrencies(),
        ]));
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        $year = $request->integer('year') ?: (int) now()->year;
        $report = $this->buildReport($year);
        $base = Fx::base();

        $filename = "pnl-{$year}-{$base}.csv";

        return response()->streamDownload(function () use ($report, $base) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Asset', "Income ({$base})", "Expenses ({$base})", "Net ({$base})"]);

            foreach ($report['rows'] as $row) {
                fputcsv($out, [
                    $row['asset'],
                    number_format($row['income'], 2, '.', ''),
                    number_format($row['expenses'], 2, '.', ''),
                    number_format($row['net'], 2, '.', ''),
                ]);
            }

            fputcsv($out, [
                'TOTAL',
                number_format($report['totals']['income'], 2, '.', ''),
                number_format($report['totals']['expenses'], 2, '.', ''),
                number_format($report['totals']['net'], 2, '.', ''),
            ]);

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** Whether any money in the system is in a currency other than the base. */
    private function usesMultipleCurrencies(): bool
    {
        $base = Fx::base();

        return RentalPayment::query()->where('currency', '!=', $base)->exists()
            || AssetExpense::query()->where('currency', '!=', $base)->exists();
    }

    /**
     * Per-asset realized income (paid payments) minus expenses for the year,
     * all converted to the base currency.
     *
     * @return array{rows: array<int, array{asset:string,income:float,expenses:float,net:float}>, totals: array{income:float,expenses:float,net:float}, unknownCurrencies: array<int,string>}
     */
    private function buildReport(int $year): array
    {
        $assets = Asset::orderBy('name')->get();

        $rows = [];
        foreach ($assets as $asset) {
            $rows[$asset->id] = ['asset' => $asset->name, 'income' => 0.0, 'expenses' => 0.0, 'net' => 0.0];
        }

        $income = RentalPayment::query()
            ->where('status', 'paid')
            ->whereYear('paid_date', $year)
            ->selectRaw('asset_id, currency, SUM(amount) as total')
            ->groupBy('asset_id', 'currency')
            ->get();

        $bucket = function (int $assetId) use (&$rows) {
            if (! isset($rows[$assetId])) {
                $rows[$assetId] = ['asset' => 'Deleted property #'.$assetId, 'income' => 0.0, 'expenses' => 0.0, 'net' => 0.0];
            }

            return $assetId;
        };
        foreach ($income as $r) {
            $rows[$bucket((int) $r->asset_id)]['income'] += Fx::toBase((float) $r->total, $r->currency);
        }

        $expenses = AssetExpense::query()
            ->whereYear('spent_on', $year)
            ->selectRaw('asset_id, currency, SUM(amount) as total')
            ->groupBy('asset_id', 'currency')
            ->get();

        foreach ($expenses as $r) {
            $rows[$bucket((int) $r->asset_id)]['expenses'] += Fx::toBase((float) $r->total, $r->currency);
        }

        // Month-by-month (base currency) for the year table
        $months = array_fill(1, 12, ['income' => 0.0, 'expenses' => 0.0]);
        foreach (RentalPayment::query()->where('status', 'paid')->whereYear('paid_date', $year)
            ->selectRaw('paid_date, currency, amount')->get() as $r) {
            $m = (int) $r->paid_date->format('n');
            $months[$m]['income'] += Fx::toBase((float) $r->amount, $r->currency);
        }
        foreach (AssetExpense::query()->whereYear('spent_on', $year)->selectRaw('spent_on, currency, amount')->get() as $r) {
            $m = (int) $r->spent_on->format('n');
            $months[$m]['expenses'] += Fx::toBase((float) $r->amount, $r->currency);
        }

        // Rent collection: what was due this year vs what actually came in
        $expected = 0.0;
        foreach (RentalPayment::query()->whereYear('due_date', $year)->selectRaw('currency, SUM(amount) as total')
            ->groupBy('currency')->get() as $r) {
            $expected += Fx::toBase((float) $r->total, $r->currency);
        }
        $collected = 0.0;
        foreach (RentalPayment::query()->where('status', 'paid')->whereYear('due_date', $year)
            ->selectRaw('currency, SUM(amount) as total')->groupBy('currency')->get() as $r) {
            $collected += Fx::toBase((float) $r->total, $r->currency);
        }

        $totals = ['income' => 0.0, 'expenses' => 0.0, 'net' => 0.0];
        foreach ($rows as &$row) {
            $row['net'] = $row['income'] - $row['expenses'];
            $totals['income'] += $row['income'];
            $totals['expenses'] += $row['expenses'];
            $totals['net'] += $row['net'];
        }
        unset($row);

        return [
            'rows' => array_values($rows),
            'totals' => $totals,
            'months' => $months,
            'collection' => [
                'expected' => $expected,
                'collected' => $collected,
                'rate' => $expected > 0 ? round($collected / $expected * 100) : null,
            ],
            'unknownCurrencies' => Fx::unknownCurrencies(),
        ];
    }
}
