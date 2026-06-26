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

        return view('reports.index', array_merge($report, [
            'year' => $year,
            'base' => Fx::base(),
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

        foreach ($income as $r) {
            if (isset($rows[$r->asset_id])) {
                $rows[$r->asset_id]['income'] += Fx::toBase((float) $r->total, $r->currency);
            }
        }

        $expenses = AssetExpense::query()
            ->whereYear('spent_on', $year)
            ->selectRaw('asset_id, currency, SUM(amount) as total')
            ->groupBy('asset_id', 'currency')
            ->get();

        foreach ($expenses as $r) {
            if (isset($rows[$r->asset_id])) {
                $rows[$r->asset_id]['expenses'] += Fx::toBase((float) $r->total, $r->currency);
            }
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
            'unknownCurrencies' => Fx::unknownCurrencies(),
        ];
    }
}
