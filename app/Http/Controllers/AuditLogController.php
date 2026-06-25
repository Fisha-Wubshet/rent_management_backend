<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    private function shopId(): int
    {
        $user = auth('api')->user();
        return $user->shop_id ?? $user->branch->shop_id;
    }

    private function branchConstraint(): ?int
    {
        $user = auth('api')->user();
        return $user->hasRole('ROLE_BRANCH_MANAGER') ? $user->branch_id : null;
    }

    private function applyFilters($query, Request $request): void
    {
        if ($request->action)      $query->where('action', $request->action);
        if ($request->performedBy) $query->where('performed_by', 'ilike', "%{$request->performedBy}%");
        if ($request->dateFrom)    $query->whereDate('timestamp', '>=', $request->dateFrom);
        if ($request->dateTo)      $query->whereDate('timestamp', '<=', $request->dateTo);
        if ($request->branchId)    $query->where('branch_id', $request->branchId);
        if ($request->entityType)  $query->where('entity_type', $request->entityType);
    }

    /** Build an email → full name map for all given logs (one query). */
    private function nameMap(iterable $logs): array
    {
        $emails = collect($logs)->pluck('performed_by')->unique()->filter()->values()->toArray();
        if (empty($emails)) return [];
        return User::whereIn('email', $emails)
            ->get(['email', 'first_name', 'last_name'])
            ->mapWithKeys(fn($u) => [
                $u->email => trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: $u->email,
            ])
            ->toArray();
    }

    /** Return the best display name for a log entry. */
    private function resolvedName(AuditLog $log, array $nameMap): string
    {
        if ($log->performed_by_name) return $log->performed_by_name;
        return $nameMap[$log->performed_by] ?? $log->performed_by;
    }

    /** Mutate a paginator's collection so every item has a resolved performed_by_name. */
    private function fillNames($paginator): mixed
    {
        $nameMap = $this->nameMap($paginator->items());
        $paginator->getCollection()->transform(function ($log) use ($nameMap) {
            $log->performed_by_name = $this->resolvedName($log, $nameMap);
            return $log;
        });
        return $paginator;
    }

    public function index(Request $request)
    {
        $query = AuditLog::where('shop_id', $this->shopId());
        if ($branch = $this->branchConstraint()) $query->where('branch_id', $branch);
        $this->applyFilters($query, $request);
        return response()->json($this->fillNames($query->orderByDesc('timestamp')->paginate($request->size ?? 25)));
    }

    public function all(Request $request)
    {
        $query = AuditLog::query();
        $this->applyFilters($query, $request);
        return response()->json($this->fillNames($query->orderByDesc('timestamp')->paginate($request->size ?? 25)));
    }

    public function mine(Request $request)
    {
        $user  = auth('api')->user();
        $query = AuditLog::where('performed_by', $user->email);
        $this->applyFilters($query, $request);
        return response()->json($this->fillNames($query->orderByDesc('timestamp')->paginate($request->size ?? 25)));
    }

    public function byEntity(Request $request, $type, $id)
    {
        $user  = auth('api')->user();
        $query = AuditLog::where('entity_type', $type)->where('entity_id', $id);
        if (!$user->hasRole('ROLE_SUPER_ADMIN')) {
            $shopId = $user->shop_id ?? $user->branch->shop_id;
            $query->where('shop_id', $shopId);
        }
        return response()->json($this->fillNames($query->orderByDesc('timestamp')->paginate($request->size ?? 20)));
    }

    public function todaySummary()
    {
        $query = AuditLog::where('shop_id', $this->shopId())
            ->whereDate('timestamp', today());
        if ($branch = $this->branchConstraint()) $query->where('branch_id', $branch);
        $logs    = $query->get();
        $nameMap = $this->nameMap($logs);

        return response()->json([
            'total'    => $logs->count(),
            'byAction' => $logs->countBy('action'),
            'byStaff'  => $logs->groupBy('performed_by')->map(fn($staffLogs) => [
                'performedBy'     => $staffLogs->first()->performed_by,
                'performedByName' => $this->resolvedName($staffLogs->first(), $nameMap),
                'total'           => $staffLogs->count(),
                'byAction'        => $staffLogs->countBy('action'),
            ])->values(),
        ]);
    }

    public function export(Request $request)
    {
        $request->validate(['dateFrom' => 'required|date', 'dateTo' => 'required|date']);
        $query = AuditLog::where('shop_id', $this->shopId());
        if ($branch = $this->branchConstraint()) $query->where('branch_id', $branch);
        $this->applyFilters($query, $request);
        $logs    = $query->orderByDesc('timestamp')->get();
        $nameMap = $this->nameMap($logs);

        $rows = ["Time,Action,Entity,Entity ID,Performed By,Details\n"];
        foreach ($logs as $log) {
            $rows[] = implode(',', [
                '"' . $log->timestamp->format('Y-m-d H:i:s') . '"',
                $log->action,
                $log->entity_type,
                $log->entity_id ?? '',
                '"' . str_replace('"', '""', $this->resolvedName($log, $nameMap)) . '"',
                '"' . str_replace('"', '""', $log->details ?? '') . '"',
            ]) . "\n";
        }

        return response(implode('', $rows), 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="audit-log-' . now()->format('Y-m-d') . '.csv"',
        ]);
    }
}
