<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetRental;
use Illuminate\Http\Request;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $hour = (int) now()->format('H');
        $greeting =
            $hour < 12 ? 'Good morning' :
            ($hour < 18 ? 'Good afternoon' : 'Good evening');

        // Assets widgets
        $totalAssets = Asset::count();
        $totalAssetsValue = (float) Asset::query()->sum('purchase_price');

        // Current period (the month you are reporting income for)
        $year = (int) now()->year;
        $month = (int) now()->month;

        // Period window (month start/end)
        $periodStart = Carbon::create($year, $month, 1)->startOfDay();
        $periodEnd = (clone $periodStart)->endOfMonth()->endOfDay();

        // -----------------------------
        // Rental income by PERIOD (year+month) - all records for that period
        // -----------------------------
        $monthlyIncome = (float) AssetRental::query()
            ->where('year', $year)
            ->where('month', $month)
            ->sum('amount');

        $monthlyIncomeByCurrency = AssetRental::query()
            ->selectRaw('currency, SUM(amount) as total')
            ->where('year', $year)
            ->where('month', $month)
            ->groupBy('currency')
            ->orderBy('currency')
            ->get();

        // -----------------------------
        // Active agreement logic (recommended)
        // Active for the PERIOD (not "today"):
        // uses your model scope which checks:
        // - agreement overlaps the month
        // - is_active = true
        // -----------------------------
        $activeAgreementsCount = AssetRental::query()
            ->where('year', $year)
            ->where('month', $month)
            ->activeForPeriod($year, $month)
            ->count();

        $monthlyIncomeActiveOnly = (float) AssetRental::query()
            ->where('year', $year)
            ->where('month', $month)
            ->activeForPeriod($year, $month)
            ->sum('amount');

        $monthlyIncomeActiveByCurrency = AssetRental::query()
            ->selectRaw('currency, SUM(amount) as total')
            ->where('year', $year)
            ->where('month', $month)
            ->activeForPeriod($year, $month)
            ->groupBy('currency')
            ->orderBy('currency')
            ->get();

        // -----------------------------
        // Occupied/Vacant widgets (based on assets.status)
        // -----------------------------
        $occupiedCount = Asset::query()
            ->whereRaw('LOWER(status) = ?', ['occupied'])
            ->count();

        $vacantCount = Asset::query()
            ->whereRaw('LOWER(status) = ?', ['vacant'])
            ->count();

        $otherStatusCount = max(0, $totalAssets - $occupiedCount - $vacantCount);

        return view('dashboard', [
            'user' => $user,
            'greeting' => $greeting,

            'totalAssets' => $totalAssets,
            'totalAssetsValue' => $totalAssetsValue,

            'currentYear' => $year,
            'currentMonth' => $month,

            // Period window if you want to display/debug it later
            'periodStart' => $periodStart->toDateString(),
            'periodEnd' => $periodEnd->toDateString(),

            // Period-based totals (all records for that year+month)
            'monthlyIncome' => $monthlyIncome,
            'monthlyIncomeByCurrency' => $monthlyIncomeByCurrency,

            // Active-for-period totals (agreement overlaps month AND is_active = true)
            'activeAgreementsCount' => $activeAgreementsCount,
            'monthlyIncomeActiveOnly' => $monthlyIncomeActiveOnly,
            'monthlyIncomeActiveByCurrency' => $monthlyIncomeActiveByCurrency,

            // Asset status widgets
            'occupiedCount' => $occupiedCount,
            'vacantCount' => $vacantCount,
            'otherStatusCount' => $otherStatusCount,
        ]);
    }
}
