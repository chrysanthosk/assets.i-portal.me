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

        // Current reporting period (current month)
        $year = (int) now()->year;
        $month = (int) now()->month;

        $periodStart = Carbon::create($year, $month, 1)->startOfDay();
        $periodEnd = (clone $periodStart)->endOfMonth()->endOfDay();

        // ---------------------------------------------------------
        // Monthly income based on AGREEMENT PERIOD overlap
        // - is_active = true
        // - agreement_start_date <= periodEnd
        // - agreement_end_date is null OR agreement_end_date >= periodStart
        // This matches: 2025-10-24 -> 2026-10-24 counts for Jan 2026.
        // ---------------------------------------------------------
        $activeAgreementBase = AssetRental::query()
            ->where('is_active', true)
            ->whereNotNull('agreement_start_date')
            ->whereDate('agreement_start_date', '<=', $periodEnd->toDateString())
            ->where(function ($q) use ($periodStart) {
                $q->whereNull('agreement_end_date')
                    ->orWhereDate('agreement_end_date', '>=', $periodStart->toDateString());
            });

        $monthlyIncome = (float) (clone $activeAgreementBase)->sum('amount');

        $monthlyIncomeByCurrency = (clone $activeAgreementBase)
            ->selectRaw('currency, SUM(amount) as total')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get();

        $activeAgreementsCount = (clone $activeAgreementBase)->count();

        // ---------------------------------------------------------
        // Occupied/Vacant mapping based on your real-world statuses
        // Example status: "Rented (long-term)" => Occupied
        // ---------------------------------------------------------
        $occupiedCount = Asset::query()
            ->whereRaw('LOWER(status) LIKE ?', ['%rented%'])
            ->orWhereRaw('LOWER(status) LIKE ?', ['%occupied%'])
            ->count();

        $vacantCount = Asset::query()
            ->whereRaw('LOWER(status) LIKE ?', ['%vacant%'])
            ->orWhereRaw('LOWER(status) LIKE ?', ['%available%'])
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
