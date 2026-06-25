<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditLogService
{
    public function log(string $entityType, ?int $entityId, string $action, string $performedBy, ?int $shopId = null, ?int $branchId = null, ?string $details = null, ?string $performedByName = null): void
    {
        AuditLog::create([
            'entity_type'       => $entityType,
            'entity_id'         => $entityId,
            'action'            => $action,
            'performed_by'      => $performedBy,
            'performed_by_name' => $performedByName,
            'shop_id'           => $shopId,
            'branch_id'         => $branchId,
            'details'           => $details,
            'timestamp'         => now(),
        ]);
    }
}
