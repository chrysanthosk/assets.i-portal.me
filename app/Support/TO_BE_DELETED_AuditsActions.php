<?php

namespace App\Support;

use App\Models\AuditLog;

trait TOBEDELETEDAuditsActions
{
    protected function audit(string $action, string $entity, ?int $entityId = null, array $meta = []): void
    {
        try {
            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => $action,
                'entity' => $entity,
                'entity_id' => $entityId,
                'meta' => $meta ?: null,
                'ip' => request()->ip(),
                'user_agent' => substr((string)request()->userAgent(), 0, 255),
            ]);
        } catch (\Throwable $e) {
            // Never break UX because of audit logging
        }
    }
}
