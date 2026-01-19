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

        // Current period (month you are reporting income for)
        // (Optional override via ?year=2025&month=10 for testing)
        $year = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);

        $month = max(1, min(12, $month));
        $year = max(2000, min(2100, $year));

        // Period window (month start/end)
        $periodStart = Carbon::create($year, $month, 1)->startOfDay();
        $periodEnd = (clone $periodStart)->endOfMonth()->endOfDay();

        // -----------------------------
        // Rental income for PERIOD based on agreement overlap (NOT stored year/month)
        // -----------------------------
        $monthlyIncome = (float) AssetRental::query()
            ->activeForPeriod($year, $month)
            ->sum('amount');

        $monthlyIncomeByCurrency = AssetRental::query()
            ->activeForPeriod($year, $month)
            ->selectRaw('currency, SUM(amount) as total')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get();

        $activeAgreementsCount = (int) AssetRental::query()
            ->activeForPeriod($year, $month)
            ->count();

        // -----------------------------
        // Occupied/Vacant widgets (more tolerant)
        // -----------------------------
        $occupiedCount = Asset::query()
            ->whereRaw("LOWER(TRIM(COALESCE(status,''))) LIKE 'rented%'")
            ->orWhereRaw("LOWER(TRIM(COALESCE(status,''))) = 'occupied'")
            ->count();

        $vacantCount = Asset::query()
            ->whereRaw("LOWER(TRIM(COALESCE(status,''))) = 'vacant'")
            ->orWhereRaw("LOWER(TRIM(COALESCE(status,''))) LIKE 'vacant%'")
            ->orWhereRaw("LOWER(TRIM(COALESCE(status,''))) LIKE 'empty%'")
            ->orWhereRaw("LOWER(TRIM(COALESCE(status,''))) LIKE 'available%'")
            ->count();

        $otherStatusCount = max(0, $totalAssets - $occupiedCount - $vacantCount);

        return view('dashboard', [
            'user' => $user,
            'greeting' => $greeting,

            'totalAssets' => $totalAssets,
            'totalAssetsValue' => $totalAssetsValue,

            'currentYear' => $year,
            'currentMonth' => $month,

            'periodStart' => $periodStart->toDateString(),
            'periodEnd' => $periodEnd->toDateString(),

            'monthlyIncome' => $monthlyIncome,
            'monthlyIncomeByCurrency' => $monthlyIncomeByCurrency,
            'activeAgreementsCount' => $activeAgreementsCount,

            'occupiedCount' => $occupiedCount,
            'vacantCount' => $vacantCount,
            'otherStatusCount' => $otherStatusCount,
        ]);
    }
}
