<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class Audit
{
    public static function log(
        string $action,
        ?object $model = null,
        ?array $oldValues = null,
        ?array $newValues = null
    ): void {
        try {
            // -----------------------------
            // Resolve entity info
            // -----------------------------
            $entity = 'system';
            $entityId = null;
            $auditableType = 'system';

            if ($model) {
                $entity = class_basename($model);
                $entityId = $model->id ?? null;
                $auditableType = get_class($model);
            }

            // -----------------------------
            // Safe context (works in CLI)
            // -----------------------------
            $userId = Auth::check() ? Auth::id() : null;
            $ip = app()->runningInConsole() ? null : Request::ip();
            $userAgent = app()->runningInConsole() ? null : (string) Request::userAgent();

            // -----------------------------
            // Create audit record
            // -----------------------------
            AuditLog::create([
                'user_id' => $userId,

                // core fields (DB-required)
                'action' => $action,
                'entity' => $entity,
                'entity_id' => $entityId,

                // unified metadata (recommended to read from here)
                'meta' => [
                    'old' => $oldValues,
                    'new' => $newValues,
                ],

                // compatibility / future-proofing
                'auditable_type' => $auditableType,
                'auditable_id' => $entityId,
                'old_values' => $oldValues,
                'new_values' => $newValues,

                // request context
                'ip' => $ip,
                'user_agent' => $userAgent,
            ]);
        } catch (\Throwable $e) {
            // NEVER break app flow — but DO log the failure
            Log::warning('Audit log failed', [
                'action' => $action,
                'entity' => $entity ?? null,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
