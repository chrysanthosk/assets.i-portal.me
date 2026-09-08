<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetDocument;
use App\Models\AssetRental;
use App\Models\AuditLog;
use App\Models\RentalPayment;
use App\Support\Fx;
use Carbon\Carbon;
use Illuminate\Http\Request;

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
        // Purchase prices converted to the base currency (assets may be in EUR, AED, …)
        $totalAssetsValue = 0.0;
        foreach (Asset::query()->whereNotNull('purchase_price')->selectRaw('currency, SUM(purchase_price) as total')->groupBy('currency')->get() as $r) {
            $totalAssetsValue += Fx::toBase((float) $r->total, $r->currency);
        }

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

        $monthlyIncomeByCurrency = (clone $activeAgreementBase)
            ->selectRaw('currency, SUM(amount) as total')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get();

        // Total in the base currency
        $monthlyIncome = 0.0;
        foreach ($monthlyIncomeByCurrency as $r) {
            $monthlyIncome += Fx::toBase((float) $r->total, $r->currency);
        }

        $activeAgreementsCount = (clone $activeAgreementBase)->count();

        // ---------------------------------------------------------
        // Occupied/Vacant mapping based on your real-world statuses
        // Example status: "Rented (long-term)" => Occupied
        // ---------------------------------------------------------
        // Bucket every asset's status into occupied / vacant in a single pass
        // instead of issuing a separate COUNT query per bucket.
        $statusCounts = Asset::query()
            ->selectRaw(
                "SUM(CASE WHEN LOWER(status) LIKE '%rented%' OR LOWER(status) LIKE '%occupied%' THEN 1 ELSE 0 END) AS occupied,"
                ." SUM(CASE WHEN LOWER(status) LIKE '%vacant%' OR LOWER(status) LIKE '%available%' THEN 1 ELSE 0 END) AS vacant"
            )
            ->first();

        $occupiedCount = (int) ($statusCounts->occupied ?? 0);
        $vacantCount = (int) ($statusCounts->vacant ?? 0);
        $otherStatusCount = max(0, $totalAssets - $occupiedCount - $vacantCount);

        // Outstanding rental payments (arrears)
        $outstandingByCurrency = RentalPayment::query()
            ->where('status', '!=', RentalPayment::STATUS_PAID)
            ->selectRaw('currency, SUM(amount) as total')
            ->groupBy('currency')
            ->orderBy('currency')
            ->get();

        $overduePaymentsCount = RentalPayment::query()->overdue()->count();
        $unconfirmedPaymentsCount = RentalPayment::query()->awaitingConfirmation()->count();

        // Document expiry reminders
        $expiredDocsCount = AssetDocument::query()
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<', now()->toDateString())
            ->count();

        $expiringDocsCount = AssetDocument::query()
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '>=', now()->toDateString())
            ->whereDate('expires_at', '<=', now()->addDays(30)->toDateString())
            ->count();

        // Panels: what needs attention + recent activity
        $rentToConfirm = RentalPayment::query()->with(['asset', 'rental.tenant'])
            ->awaitingConfirmation()->orderBy('due_date')->limit(6)->get();
        $overdueRent = RentalPayment::query()->with(['asset', 'rental.tenant'])
            ->overdue()->orderBy('due_date')->limit(6)->get();
        $expiringDocs = AssetDocument::query()->with('asset')
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<=', now()->addDays(30)->toDateString())
            ->orderBy('expires_at')->limit(6)->get();
        $recentActivity = $user->can('manage_audit_logs')
            ? AuditLog::query()->with('user')->latest()->limit(8)->get()
            : collect();

        return view('dashboard', [
            'base' => Fx::base(),
            'unknownCurrencies' => Fx::unknownCurrencies(),
            'rentToConfirm' => $rentToConfirm,
            'overdueRent' => $overdueRent,
            'expiringDocs' => $expiringDocs,
            'recentActivity' => $recentActivity,
            'user' => $user,
            'greeting' => $greeting,

            'totalAssets' => $totalAssets,
            'totalAssetsValue' => $totalAssetsValue,

            'currentYear' => $year,
            'currentMonth' => $month,

            'monthlyIncome' => $monthlyIncome,
            'monthlyIncomeByCurrency' => $monthlyIncomeByCurrency,

            'activeAgreementsCount' => $activeAgreementsCount,

            'occupiedCount' => $occupiedCount,
            'vacantCount' => $vacantCount,
            'otherStatusCount' => $otherStatusCount,

            'outstandingByCurrency' => $outstandingByCurrency,
            'overduePaymentsCount' => $overduePaymentsCount,
            'unconfirmedPaymentsCount' => $unconfirmedPaymentsCount,

            'expiredDocsCount' => $expiredDocsCount,
            'expiringDocsCount' => $expiringDocsCount,
        ]);
    }
}
