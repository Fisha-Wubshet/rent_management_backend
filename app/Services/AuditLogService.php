<?php

namespace App\Services;

use App\Models\AuditLog;

class AuditLogService
{
    /**
     * Actions kept forever. Everything else is 'operational' and gets pruned
     * after 90 days (see App\Console\Commands\PruneAuditLogs).
     */
    private const CRITICAL_ACTIONS = [
        'LOGIN_FAILED',
        'PASSWORD_RESET',
        'PASSWORD_RESET_FAILED',
        'FIRST_LOGIN_PASSWORD_SET',
        'RECOVERY_CODE_REGENERATED',
        'RECOVERY_CODE_REGENERATE_FAILED',
        'PAYMENT_RECORDED',
        'DELETED',
        'CANCELLED',
        'BLACKLISTED',
        'UNBLACKLISTED',
        'STAFF_UPDATED',
    ];

    public function log(string $entityType, ?int $entityId, string $action, ?string $performedBy, ?int $shopId = null, ?int $branchId = null, ?string $details = null, ?string $performedByName = null): void
    {
        AuditLog::create([
            'entity_type'       => $entityType,
            'entity_id'         => $entityId,
            'action'            => $action,
            'retention'         => in_array($action, self::CRITICAL_ACTIONS, true) ? 'critical' : 'operational',
            'performed_by'      => $performedBy,
            'performed_by_name' => $performedByName,
            'shop_id'           => $shopId,
            'branch_id'         => $branchId,
            'details'           => $details,
            'timestamp'         => now(),
        ]);
    }
}
