<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Branch;
use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ReportController extends Controller
{
    private function shopId(): int
    {
        $user = auth('api')->user();
        return $user->shop_id ?? $user->branch->shop_id;
    }

    private function branchId(Request $request): int
    {
        $user = auth('api')->user();
        $id   = (int) ($request->branchId ?? $user->branch_id ?? 0);
        if (!$id) abort(422, 'A branch must be selected.');
        return $id;
    }

    /**
     * Revenue a booking contributes:
     *   - Active/completed → the full agreed price (what the customer owes)
     *   - Cancelled        → advance paid minus refund given (what the shop actually kept)
     */
    private function revenueFor(Booking $b): float
    {
        if ($b->status === 'CANCELLED') {
            return max(0.0, (float) $b->total_advance_payment - (float) ($b->refund_amount ?? 0));
        }
        return (float) $b->total_agreed_price;
    }

    /**
     * Cash collected from a booking:
     *   - Active/completed → total_advance_payment (partial or full payment made)
     *   - Cancelled        → same as revenueFor (net kept after refund)
     */
    private function collectedFor(Booking $b): float
    {
        if ($b->status === 'CANCELLED') {
            return max(0.0, (float) $b->total_advance_payment - (float) ($b->refund_amount ?? 0));
        }
        return (float) $b->total_advance_payment;
    }

    /**
     * Balance still owed on a booking:
     *   - Active/completed → agreed price minus what has been paid
     *   - Cancelled        → always 0 (booking is closed, nothing more to collect)
     */
    private function outstandingFor(Booking $b): float
    {
        if ($b->status === 'CANCELLED') return 0.0;
        return max(0.0, (float) $b->total_agreed_price - (float) $b->total_advance_payment);
    }

    /**
     * Base query that includes all bookings relevant for revenue:
     *   - All non-cancelled bookings
     *   - Cancelled bookings where the shop kept something (advance > refund)
     */
    private function revenueQuery(int $shopId)
    {
        return Booking::where('shop_id', $shopId)
            ->where('booking_type', '!=', 'MAINTENANCE')
            ->where(function ($q) {
                $q->where('status', '!=', 'CANCELLED')
                  ->orWhere(function ($q2) {
                      $q2->where('status', 'CANCELLED')
                         ->whereRaw('total_advance_payment > COALESCE(refund_amount, 0)');
                  });
            });
    }

    // ── Outstanding balances ──────────────────────────────────────────

    public function outstandingBalances(Request $request)
    {
        $branchId = $this->branchId($request);
        $query = Booking::where('shop_id', $this->shopId())
            ->where('status', '!=', 'CANCELLED')
            ->whereRaw('total_agreed_price > total_advance_payment')
            ->where('branch_id', $branchId);
        return response()->json($query->get()->map(fn($b) => [
            'bookingId'           => $b->id,
            'invoiceNumber'       => $b->invoice_number,
            'customerName'        => $b->first_name . ' ' . $b->last_name,
            'phoneNumber'         => $b->phone_number,
            'bookingDate'         => $b->booking_date,
            'totalAgreedPrice'    => (float) $b->total_agreed_price,
            'totalAdvancePayment' => (float) $b->total_advance_payment,
            'balanceDue'          => (float) ($b->total_agreed_price - $b->total_advance_payment),
        ]));
    }

    // ── Daily revenue ─────────────────────────────────────────────────

    public function dailyRevenue(Request $request)
    {
        $request->validate(['date' => 'nullable|date']);
        $branchId = $this->branchId($request);
        $date     = $request->date ?? today()->toDateString();
        $query    = $this->revenueQuery($this->shopId())
            ->where('booking_date', $date)
            ->where('branch_id', $branchId);
        $bookings = $query->get();

        return response()->json([
            'date'             => $date,
            'totalRevenue'     => round($bookings->sum(fn($b) => $this->revenueFor($b)), 2),
            'totalCollected'   => round($bookings->sum(fn($b) => $this->collectedFor($b)), 2),
            'totalOutstanding' => round($bookings->sum(fn($b) => $this->outstandingFor($b)), 2),
            'totalBookings'    => $bookings->where('status', '!=', 'CANCELLED')->count(),
        ]);
    }

    // ── Monthly revenue ───────────────────────────────────────────────

    public function monthlyRevenue(Request $request)
    {
        $request->validate(['year' => 'required|integer', 'month' => 'required|integer']);
        $branchId = $this->branchId($request);
        $query = $this->revenueQuery($this->shopId())
            ->whereYear('booking_date', $request->year)
            ->whereMonth('booking_date', $request->month)
            ->where('branch_id', $branchId);
        $bookings = $query->get();

        return response()->json([
            'year'             => $request->year,
            'month'            => $request->month,
            'totalRevenue'     => round($bookings->sum(fn($b) => $this->revenueFor($b)), 2),
            'totalCollected'   => round($bookings->sum(fn($b) => $this->collectedFor($b)), 2),
            'totalOutstanding' => round($bookings->sum(fn($b) => $this->outstandingFor($b)), 2),
            'totalBookings'    => $bookings->where('status', '!=', 'CANCELLED')->count(),
        ]);
    }

    // ── Revenue trend ─────────────────────────────────────────────────

    public function revenueTrend(Request $request)
    {
        $request->validate(['months' => 'nullable|integer|min:1|max:24']);
        $months   = (int) ($request->months ?? 6);
        $shopId   = $this->shopId();
        $branchId = $this->branchId($request);

        $key = "rpt:trend:{$shopId}:{$branchId}:{$months}";
        $results = Cache::remember($key, 300, function () use ($months, $shopId, $branchId) {
            $endDate   = Carbon::now()->endOfMonth()->toDateString();
            $startDate = Carbon::now()->subMonths($months - 1)->startOfMonth()->toDateString();

            $bookings = $this->revenueQuery($shopId)
                ->where('branch_id', $branchId)
                ->whereBetween('booking_date', [$startDate, $endDate])
                ->get();

            $grouped = $bookings->groupBy(fn($b) => Carbon::parse($b->booking_date)->format('Y-m'));

            $out = [];
            for ($i = $months - 1; $i >= 0; $i--) {
                $date = Carbon::now()->subMonths($i);
                $mb   = $grouped->get($date->format('Y-m'), collect());
                $out[] = [
                    'period'           => $date->format('Y-m'),
                    'label'            => $date->format('M Y'),
                    'totalRevenue'     => round($mb->sum(fn($b) => $this->revenueFor($b)), 2),
                    'totalCollected'   => round($mb->sum(fn($b) => $this->collectedFor($b)), 2),
                    'totalOutstanding' => round($mb->sum(fn($b) => $this->outstandingFor($b)), 2),
                    'totalBookings'    => $mb->where('status', '!=', 'CANCELLED')->count(),
                ];
            }
            return $out;
        });

        return response()->json($results);
    }

    // ── Revenue by item ───────────────────────────────────────────────

    public function itemRevenue(Request $request)
    {
        $shopId   = $this->shopId();
        $branchId = $this->branchId($request);

        $key = "rpt:byitem:{$shopId}:{$branchId}";
        $result = Cache::remember($key, 300, function () use ($shopId, $branchId) {
            $items = Item::where('branch_id', $branchId)
                ->whereHas('branch', fn($q) => $q->where('shop_id', $shopId))
                ->get(['id', 'unique_code', 'name']);

            if ($items->isEmpty()) return [];

            $itemIds = $items->pluck('id');

            $bookingItems = BookingItem::whereIn('item_id', $itemIds)
                ->whereHas('booking', function ($q) {
                    $q->where('status', '!=', 'CANCELLED')
                      ->orWhere(fn($q2) => $q2->where('status', 'CANCELLED')
                          ->whereRaw('total_advance_payment > COALESCE(refund_amount, 0)'));
                })
                ->with('booking:id,status,total_agreed_price,total_advance_payment,refund_amount')
                ->get(['id', 'item_id', 'booking_id']);

            $byItem = $bookingItems->groupBy('item_id');

            return $items->map(function ($item) use ($byItem) {
                $bis = $byItem->get($item->id, collect());
                return [
                    'uniqueCode'    => $item->unique_code,
                    'itemName'      => $item->name,
                    'totalBookings' => $bis->filter(fn($bi) => $bi->booking && $bi->booking->status !== 'CANCELLED')->count(),
                    'totalRevenue'  => round($bis->sum(fn($bi) => $bi->booking ? $this->revenueFor($bi->booking) : 0), 2),
                ];
            })->values()->all();
        });

        return response()->json($result);
    }

    // ── Branch comparison ─────────────────────────────────────────────
    // SHOP_ADMIN only — intentionally cross-branch for management overview.

    public function branchComparison(Request $request)
    {
        $request->validate(['year' => 'required|integer', 'month' => 'required|integer']);
        $shopId   = $this->shopId();
        $branches = Branch::where('shop_id', $shopId)->get();

        $rows = $branches->map(function ($branch) use ($request) {
            $bookings = $this->revenueQuery($branch->shop_id ?? $this->shopId())
                ->where('branch_id', $branch->id)
                ->whereYear('booking_date', $request->year)
                ->whereMonth('booking_date', $request->month)
                ->get();

            return [
                'branchId'         => $branch->id,
                'branchName'       => $branch->name,
                'totalBookings'    => $bookings->where('status', '!=', 'CANCELLED')->count(),
                'totalRevenue'     => round($bookings->sum(fn($b) => $this->revenueFor($b)), 2),
                'totalCollected'   => round($bookings->sum(fn($b) => $this->collectedFor($b)), 2),
                'totalOutstanding' => round($bookings->sum(fn($b) => $this->outstandingFor($b)), 2),
            ];
        });

        $total = $this->revenueQuery($shopId)
            ->whereYear('booking_date', $request->year)
            ->whereMonth('booking_date', $request->month)
            ->get();

        return response()->json([
            'rows'  => $rows,
            'total' => [
                'branchId'         => null,
                'branchName'       => 'Total',
                'totalBookings'    => $total->where('status', '!=', 'CANCELLED')->count(),
                'totalRevenue'     => round($total->sum(fn($b) => $this->revenueFor($b)), 2),
                'totalCollected'   => round($total->sum(fn($b) => $this->collectedFor($b)), 2),
                'totalOutstanding' => round($total->sum(fn($b) => $this->outstandingFor($b)), 2),
            ],
        ]);
    }

    // ── Receivables aging ─────────────────────────────────────────────

    public function receivablesAging(Request $request)
    {
        $branchId = $this->branchId($request);
        // Only non-cancelled bookings have receivables (cancelled are closed)
        $query = Booking::where('shop_id', $this->shopId())
            ->where('status', '!=', 'CANCELLED')
            ->whereRaw('total_agreed_price > total_advance_payment')
            ->where('branch_id', $branchId);

        $bookings = $query->get();
        $today    = now()->startOfDay();

        $buckets = [
            '0-30'  => ['label' => '0–30 days',  'min' => 0,  'max' => 30,  'count' => 0, 'total' => 0.0],
            '31-60' => ['label' => '31–60 days', 'min' => 31, 'max' => 60,  'count' => 0, 'total' => 0.0],
            '61-90' => ['label' => '61–90 days', 'min' => 61, 'max' => 90,  'count' => 0, 'total' => 0.0],
            '90+'   => ['label' => '90+ days',   'min' => 91, 'max' => null, 'count' => 0, 'total' => 0.0],
        ];

        foreach ($bookings as $b) {
            $age     = (int) $today->diffInDays(Carbon::parse($b->booking_date));
            $balance = (float) ($b->total_agreed_price - $b->total_advance_payment);
            if ($age <= 30)     { $buckets['0-30']['count']++;  $buckets['0-30']['total']  += $balance; }
            elseif ($age <= 60) { $buckets['31-60']['count']++; $buckets['31-60']['total'] += $balance; }
            elseif ($age <= 90) { $buckets['61-90']['count']++; $buckets['61-90']['total'] += $balance; }
            else                { $buckets['90+']['count']++;   $buckets['90+']['total']   += $balance; }
        }

        return response()->json([
            'buckets' => array_values($buckets),
            'total'   => [
                'count' => $bookings->count(),
                'total' => round((float) ($bookings->sum('total_agreed_price') - $bookings->sum('total_advance_payment')), 2),
            ],
        ]);
    }

    // ── Year-over-year ────────────────────────────────────────────────

    public function yearOverYear(Request $request)
    {
        $request->validate(['year1' => 'required|integer', 'year2' => 'required|integer']);
        $shopId   = $this->shopId();
        $branchId = $this->branchId($request);
        $result   = [];

        for ($month = 1; $month <= 12; $month++) {
            $q1 = $this->revenueQuery($shopId)->whereYear('booking_date', $request->year1)->whereMonth('booking_date', $month)->where('branch_id', $branchId);
            $q2 = $this->revenueQuery($shopId)->whereYear('booking_date', $request->year2)->whereMonth('booking_date', $month)->where('branch_id', $branchId);
            $b1 = $q1->get();
            $b2 = $q2->get();
            $result[] = [
                'month'         => $month,
                'year1Revenue'  => round($b1->sum(fn($b) => $this->revenueFor($b)), 2),
                'year2Revenue'  => round($b2->sum(fn($b) => $this->revenueFor($b)), 2),
                'year1Bookings' => $b1->where('status', '!=', 'CANCELLED')->count(),
                'year2Bookings' => $b2->where('status', '!=', 'CANCELLED')->count(),
            ];
        }

        return response()->json($result);
    }

    // ── Inventory utilization ─────────────────────────────────────────

    public function inventoryUtilization(Request $request)
    {
        $request->validate(['dateFrom' => 'required|date', 'dateTo' => 'required|date']);
        $shopId    = $this->shopId();
        $branchId  = $this->branchId($request);
        $dateFrom  = Carbon::parse($request->dateFrom)->startOfDay();
        $dateTo    = Carbon::parse($request->dateTo)->endOfDay();
        $totalDays = $dateFrom->diffInDays($dateTo) + 1;

        $items = Item::whereHas('branch', fn($q) => $q->where('shop_id', $shopId))
            ->where('branch_id', $branchId)
            ->get();

        $result = $items->map(function ($item) use ($dateFrom, $dateTo, $totalDays) {
            $bookingItems = BookingItem::where('item_id', $item->id)
                ->whereHas('booking', function ($q) use ($dateFrom, $dateTo) {
                    $q->where('status', '!=', 'CANCELLED')
                      ->where('booking_type', '!=', 'MAINTENANCE')
                      ->whereDate('booking_date', '<=', $dateTo)
                      ->whereDate('return_date',  '>=', $dateFrom);
                })
                ->with('booking')
                ->get();

            $bookedDays = $bookingItems->sum(function ($bi) use ($dateFrom, $dateTo) {
                $start = max(Carbon::parse($bi->booking->booking_date), $dateFrom);
                $end   = min(Carbon::parse($bi->booking->return_date),  $dateTo);
                return max(0, $start->diffInDays($end) + 1);
            });

            $availableDays  = $totalDays * $item->quantity;
            $utilizationPct = $availableDays > 0 ? round($bookedDays / $availableDays * 100, 1) : 0;

            return [
                'itemId'         => $item->id,
                'uniqueCode'     => $item->unique_code,
                'itemName'       => $item->name,
                'quantity'       => $item->quantity,
                'totalBookings'  => $bookingItems->count(),
                'bookedDays'     => $bookedDays,
                'availableDays'  => $availableDays,
                'utilizationPct' => $utilizationPct,
            ];
        })->sortByDesc('utilizationPct')->values();

        return response()->json($result);
    }

    // ── Deposit summary ───────────────────────────────────────────────

    public function depositSummary(Request $request)
    {
        $request->validate([
            'status'    => 'nullable|string|in:HELD,RETURNED,PARTIAL_KEEP,FULL_KEEP,EXCESS_DAMAGE',
            'startDate' => 'nullable|date',
            'endDate'   => 'nullable|date',
            'branchId'  => 'nullable|integer',
        ]);

        $branchId = $this->branchId($request);

        // Include bookings with a deposit OR bookings with excess damage (zero-deposit customers)
        $query = Booking::where('shop_id', $this->shopId())
            ->where(function ($q) {
                $q->where('security_deposit', '>', 0)
                  ->orWhere('excess_damage_charge', '>', 0);
            })
            ->where('branch_id', $branchId)
            ->with('branch');

        if ($request->startDate) $query->whereDate('booking_date', '>=', $request->startDate);
        if ($request->endDate)   $query->whereDate('booking_date', '<=', $request->endDate);

        $bookings = $query->orderByDesc('created_at')->get();

        $rows = $bookings->map(function ($b) {
            $deposit   = (float) $b->security_deposit;
            $deduction = (float) ($b->deposit_deduction ?? 0);
            $excess    = (float) ($b->excess_damage_charge ?? 0);
            $returned  = max(0, $deposit - $deduction);

            if ($excess > 0) {
                $depositStatus = 'EXCESS_DAMAGE';
            } elseif (!$b->security_deposit_returned) {
                $depositStatus = 'HELD';
            } elseif ($deduction >= $deposit && $deposit > 0) {
                $depositStatus = 'FULL_KEEP';
            } elseif ($deduction > 0) {
                $depositStatus = 'PARTIAL_KEEP';
            } else {
                $depositStatus = 'RETURNED';
            }

            return [
                'bookingId'              => $b->id,
                'invoiceNumber'          => $b->invoice_number,
                'customerName'           => $b->first_name . ' ' . $b->last_name,
                'phoneNumber'            => $b->phone_number,
                'bookingDate'            => $b->booking_date?->toDateString(),
                'returnDate'             => $b->return_date?->toDateString(),
                'bookingStatus'          => $b->status,
                'depositStatus'          => $depositStatus,
                'securityDeposit'        => $deposit,
                'depositDeduction'       => $deduction,
                'depositDeductionReason' => $b->deposit_deduction_reason,
                'excessDamageCharge'     => $excess,
                'depositReturned'        => $returned,
                'branchName'             => $b->branch?->name ?? '—',
            ];
        });

        if ($request->status) {
            $rows = $rows->filter(fn($r) => $r['depositStatus'] === $request->status)->values();
        }

        $summary = [
            'totalHeld'          => (float) $bookings->filter(fn($b) => ($b->excess_damage_charge ?? 0) == 0 && !$b->security_deposit_returned)->sum('security_deposit'),
            'totalReturnedClean' => (float) $bookings->filter(fn($b) => $b->security_deposit_returned && ($b->deposit_deduction ?? 0) == 0)->sum('security_deposit'),
            'totalKeptDamage'    => (float) $bookings
                ->filter(fn($b) => in_array($b->status, ['RETURNED', 'CANCELLED']))
                ->sum(fn($b) => (float) ($b->deposit_deduction ?? 0) + (float) ($b->excess_damage_charge ?? 0)),
            'totalExcessDamage'  => (float) $bookings
                ->filter(fn($b) => in_array($b->status, ['RETURNED', 'CANCELLED']))
                ->sum('excess_damage_charge'),
        ];

        return response()->json(['summary' => $summary, 'rows' => $rows]);
    }
}
