<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    /**
     * Lightweight, unauthenticated health probe (database connectivity only).
     * Returns 200 when healthy, 503 when the database is unreachable.
     */
    public function __invoke(): JsonResponse
    {
        $databaseOk = true;

        try {
            DB::select('select 1');
        } catch (\Throwable $e) {
            $databaseOk = false;
        }

        return response()->json([
            'status' => $databaseOk ? 'ok' : 'degraded',
            'database' => $databaseOk,
            'time' => now()->toIso8601String(),
        ], $databaseOk ? 200 : 503);
    }
}
