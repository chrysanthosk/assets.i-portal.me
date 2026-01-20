<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AuditLogsController extends Controller
{
    public function index(Request $request)
    {
        // Filters
        $q        = trim((string) $request->get('q', ''));
        $userId   = $request->filled('user_id') ? (int) $request->get('user_id') : null;
        $entity   = trim((string) $request->get('entity', ''));
        $action   = trim((string) $request->get('action', ''));
        $dateFrom = trim((string) $request->get('date_from', ''));
        $dateTo   = trim((string) $request->get('date_to', ''));

        // For dropdowns
        $users = User::orderBy('name')->get(['id','name','email']);

        // Distinct entities/actions (keep it light)
        $entities = AuditLog::query()
            ->select('entity')
            ->whereNotNull('entity')
            ->where('entity', '!=', '')
            ->distinct()
            ->orderBy('entity')
            ->limit(200)
            ->pluck('entity');

        $actions = AuditLog::query()
            ->select('action')
            ->whereNotNull('action')
            ->where('action', '!=', '')
            ->distinct()
            ->orderBy('action')
            ->limit(400)
            ->pluck('action');

        // Query
        $logs = AuditLog::query()
            ->with('user:id,name,email')
            ->when($userId, fn($qq) => $qq->where('user_id', $userId))
            ->when($entity !== '', fn($qq) => $qq->where('entity', $entity))
            ->when($action !== '', fn($qq) => $qq->where('action', $action))
            ->when($q !== '', function ($qq) use ($q) {
                $qq->where(function ($w) use ($q) {
                    $w->where('action', 'like', "%{$q}%")
                        ->orWhere('entity', 'like', "%{$q}%")
                        ->orWhere('entity_id', 'like', "%{$q}%")
                        ->orWhere('auditable_type', 'like', "%{$q}%")
                        ->orWhere('ip', 'like', "%{$q}%")
                        ->orWhere('user_agent', 'like', "%{$q}%")
                        ->orWhere('meta', 'like', "%{$q}%")
                        ->orWhere('old_values', 'like', "%{$q}%")
                        ->orWhere('new_values', 'like', "%{$q}%");
                });
            })
            ->when($dateFrom !== '' || $dateTo !== '', function ($qq) use ($dateFrom, $dateTo) {
                $from = null;
                $to = null;

                try {
                    if ($dateFrom !== '') {
                        $from = Carbon::parse($dateFrom)->startOfDay();
                    }
                } catch (\Throwable $e) {
                    $from = null;
                }

                try {
                    if ($dateTo !== '') {
                        $to = Carbon::parse($dateTo)->endOfDay();
                    }
                } catch (\Throwable $e) {
                    $to = null;
                }

                if ($from && $to) {
                    $qq->whereBetween('created_at', [$from, $to]);
                } elseif ($from) {
                    $qq->where('created_at', '>=', $from);
                } elseif ($to) {
                    $qq->where('created_at', '<=', $to);
                }
            })
            ->orderBy('id', 'desc')
            ->paginate(25)
            ->withQueryString();

        return view('audit.index', compact(
            'logs',
            'users',
            'entities',
            'actions',
            'q',
            'userId',
            'entity',
            'action',
            'dateFrom',
            'dateTo'
        ));
    }
}
