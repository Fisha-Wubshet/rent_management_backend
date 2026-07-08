<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index(Request $request)
    {
        $user   = auth('api')->user();
        $shopId = $user->shop_id ?? $user->branch->shop_id;

        $query = Payment::with('booking')
            ->where('shop_id', $shopId);

        $branchId = (int) ($request->branchId ?? $user->branch_id ?? 0);
        if (!$branchId) abort(422, 'A branch must be selected.');
        $query->where('branch_id', $branchId);
        if ($request->type)      $query->where('type', $request->type);
        if ($request->startDate) $query->whereDate('created_at', '>=', $request->startDate);
        if ($request->endDate)   $query->whereDate('created_at', '<=', $request->endDate);
        if ($request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('recorded_by_name', 'like', "%{$search}%")
                  ->orWhereHas('booking', fn($bq) =>
                      $bq->where('invoice_number', 'like', "%{$search}%")
                         ->orWhere('first_name',   'like', "%{$search}%")
                         ->orWhere('last_name',    'like', "%{$search}%")
                         ->orWhere('phone_number', 'like', "%{$search}%")
                  );
            });
        }

        // Compute summary over the full filtered set before paginating
        $inTypes  = ['ADVANCE', 'ADDITIONAL', 'SECURITY_DEPOSIT', 'DAMAGE_CHARGE'];
        $outTypes = ['DEPOSIT_RETURNED', 'REFUND'];

        $allAmounts = (clone $query)->get(['type', 'amount']);
        $totalIn    = $allAmounts->whereIn('type', $inTypes)->sum('amount');
        $totalOut   = $allAmounts->whereIn('type', $outTypes)->sum('amount');

        $paginated = $query->orderByDesc('created_at')->paginate($request->size ?? 20);

        $items = collect($paginated->items())->map(fn($p) => [
            'id'               => $p->id,
            'type'             => $p->type,
            'amount'           => (float) $p->amount,
            'recorded_by_name' => $p->recorded_by_name,
            'notes'            => $p->notes,
            'created_at'       => $p->created_at,
            'invoice_number'   => $p->booking?->invoice_number,
            'booking_id'       => $p->booking_id,
            'customer_name'    => trim(($p->booking?->first_name ?? '') . ' ' . ($p->booking?->last_name ?? '')),
            'phone_number'     => $p->booking?->phone_number,
        ]);

        return response()->json([
            'data'      => $items,
            'total'     => $paginated->total(),
            'last_page' => $paginated->lastPage(),
            'summary'   => [
                'total_in'  => (float) $totalIn,
                'total_out' => (float) $totalOut,
                'net'       => (float) ($totalIn - $totalOut),
            ],
        ]);
    }
}
