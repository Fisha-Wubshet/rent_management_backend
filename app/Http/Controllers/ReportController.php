<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Branch;
use App\Models\Item;
use Illuminate\Http\Request;
use Carbon\Carbon;

class ReportController extends Controller
{
    private function shopId(): int
    {
        $user = auth('api')->user();
        return $user->shop_id ?? $user->branch->shop_id;
    }

    public function outstandingBalances(Request $request)
    {
        $query = Booking::where('shop_id', $this->shopId())
            ->where('status', '!=', 'CANCELLED')
            ->whereRaw('total_agreed_price > total_advance_payment');
        if ($request->branchId) $query->where('branch_id', $request->branchId);
        return response()->json($query->get()->map(fn($b) => [
            'bookingId'          => $b->id,
            'invoiceNumber'      => $b->invoice_number,
            'customerName'       => $b->first_name . ' ' . $b->last_name,
            'phoneNumber'        => $b->phone_number,
            'bookingDate'        => $b->booking_date,
            'totalAgreedPrice'   => (float) $b->total_agreed_price,
            'totalAdvancePayment'=> (float) $b->total_advance_payment,
            'balanceDue'         => (float) ($b->total_agreed_price - $b->total_advance_payment),
        ]));
    }

    public function dailyRevenue(Request $request)
    {
        $request->validate(['date' => 'nullable|date']);
        $date  = $request->date ?? today()->toDateString();
        $query = Booking::where('shop_id', $this->shopId())
            ->where('booking_date', $date)
            ->where('status', '!=', 'CANCELLED');
        if ($request->branchId) $query->where('branch_id', $request->branchId);
        $bookings = $query->get();
        return response()->json([
            'date'             => $date,
            'totalRevenue'     => (float) $bookings->sum('total_agreed_price'),
            'totalOutstanding' => (float) ($bookings->sum('total_agreed_price') - $bookings->sum('total_advance_payment')),
            'totalBookings'    => $bookings->count(),
        ]);
    }

    public function monthlyRevenue(Request $request)
    {
        $request->validate(['year' => 'required|integer', 'month' => 'required|integer']);
        $query = Booking::where('shop_id', $this->shopId())
            ->whereYear('booking_date', $request->year)
            ->whereMonth('booking_date', $request->month)
            ->where('status', '!=', 'CANCELLED');
        if ($request->branchId) $query->where('branch_id', $request->branchId);
        $bookings = $query->get();
        return response()->json([
            'year'             => $request->year,
            'month'            => $request->month,
            'totalRevenue'     => (float) $bookings->sum('total_agreed_price'),
            'totalOutstanding' => (float) ($bookings->sum('total_agreed_price') - $bookings->sum('total_advance_payment')),
            'totalBookings' => $bookings->count(),
        ]);
    }

    public function revenueTrend(Request $request)
    {
        $request->validate(['months' => 'nullable|integer|min:1|max:24']);
        $months = (int) ($request->months ?? 6);
        $shopId = $this->shopId();
        $results = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $q = Booking::where('shop_id', $shopId)
                ->whereYear('booking_date', $date->year)
                ->whereMonth('booking_date', $date->month)
                ->where('status', '!=', 'CANCELLED');
            if ($request->branchId) $q->where('branch_id', $request->branchId);
            $bookings = $q->get();
            $results[] = [
                'period'           => $date->format('Y-m'),
                'label'            => $date->format('M Y'),
                'totalRevenue'     => (float) $bookings->sum('total_agreed_price'),
                'totalCollected'   => (float) $bookings->sum('total_advance_payment'),
                'totalOutstanding' => (float) ($bookings->sum('total_agreed_price') - $bookings->sum('total_advance_payment')),
                'totalBookings'    => $bookings->count(),
            ];
        }
        return response()->json($results);
    }

    public function itemRevenue(Request $request)
    {
        $shopId = $this->shopId();
        $items = Item::whereHas('branch', fn($q) => $q->where('shop_id', $shopId))
            ->when($request->branchId, fn($q) => $q->where('branch_id', $request->branchId))
            ->get();

        $result = $items->map(function ($item) {
            $bookingItems = BookingItem::where('item_id', $item->id)
                ->whereHas('booking', fn($q) => $q->where('status', '!=', 'CANCELLED'))
                ->with('booking')
                ->get();
            return [
                'uniqueCode'    => $item->unique_code,
                'itemName'      => $item->name,
                'totalBookings' => $bookingItems->count(),
                'totalRevenue'  => (float) $bookingItems->sum(fn($bi) => $bi->booking->total_agreed_price ?? 0),
            ];
        });

        return response()->json($result);
    }

    public function branchComparison(Request $request)
    {
        $request->validate(['year' => 'required|integer', 'month' => 'required|integer']);
        $shopId   = $this->shopId();
        $branches = Branch::where('shop_id', $shopId)->get();

        $rows = $branches->map(function ($branch) use ($request) {
            $bookings = Booking::where('branch_id', $branch->id)
                ->whereYear('booking_date', $request->year)
                ->whereMonth('booking_date', $request->month)
                ->where('status', '!=', 'CANCELLED')
                ->get();
            return [
                'branchId'        => $branch->id,
                'branchName'      => $branch->name,
                'totalBookings'   => $bookings->count(),
                'totalRevenue'    => (float) $bookings->sum('total_agreed_price'),
                'totalCollected'  => (float) $bookings->sum('total_advance_payment'),
                'totalOutstanding'=> (float) ($bookings->sum('total_agreed_price') - $bookings->sum('total_advance_payment')),
            ];
        });

        $total = Booking::where('shop_id', $shopId)
            ->whereYear('booking_date', $request->year)
            ->whereMonth('booking_date', $request->month)
            ->where('status', '!=', 'CANCELLED')
            ->get();

        return response()->json([
            'rows'  => $rows,
            'total' => [
                'branchId'        => null,
                'branchName'      => 'Total',
                'totalBookings'   => $total->count(),
                'totalRevenue'    => (float) $total->sum('total_agreed_price'),
                'totalCollected'  => (float) $total->sum('total_advance_payment'),
                'totalOutstanding'=> (float) ($total->sum('total_agreed_price') - $total->sum('total_advance_payment')),
            ],
        ]);
    }

    public function receivablesAging(Request $request)
    {
        $query = Booking::where('shop_id', $this->shopId())
            ->where('status', '!=', 'CANCELLED')
            ->whereRaw('total_agreed_price > total_advance_payment');
        if ($request->branchId) $query->where('branch_id', $request->branchId);

        $bookings = $query->get();
        $today    = now()->startOfDay();

        $buckets = [
            '0-30'  => ['label' => '0–30 days',  'min' => 0,  'max' => 30,  'count' => 0, 'total' => 0.0],
            '31-60' => ['label' => '31–60 days', 'min' => 31, 'max' => 60,  'count' => 0, 'total' => 0.0],
            '61-90' => ['label' => '61–90 days', 'min' => 61, 'max' => 90,  'count' => 0, 'total' => 0.0],
            '90+'   => ['label' => '90+ days',   'min' => 91, 'max' => null, 'count' => 0, 'total' => 0.0],
        ];

        foreach ($bookings as $b) {
            $age     = (int) $today->diffInDays(\Carbon\Carbon::parse($b->booking_date));
            $balance = (float) ($b->total_agreed_price - $b->total_advance_payment);
            if ($age <= 30)      { $buckets['0-30']['count']++;  $buckets['0-30']['total']  += $balance; }
            elseif ($age <= 60)  { $buckets['31-60']['count']++; $buckets['31-60']['total'] += $balance; }
            elseif ($age <= 90)  { $buckets['61-90']['count']++; $buckets['61-90']['total'] += $balance; }
            else                 { $buckets['90+']['count']++;   $buckets['90+']['total']   += $balance; }
        }

        return response()->json([
            'buckets' => array_values($buckets),
            'total'   => [
                'count' => $bookings->count(),
                'total' => (float) ($bookings->sum('total_agreed_price') - $bookings->sum('total_advance_payment')),
            ],
        ]);
    }

    public function yearOverYear(Request $request)
    {
        $request->validate(['year1' => 'required|integer', 'year2' => 'required|integer']);
        $shopId = $this->shopId();
        $result = [];

        for ($month = 1; $month <= 12; $month++) {
            $q1 = Booking::where('shop_id', $shopId)
                ->whereYear('booking_date', $request->year1)
                ->whereMonth('booking_date', $month)
                ->where('status', '!=', 'CANCELLED');
            $q2 = Booking::where('shop_id', $shopId)
                ->whereYear('booking_date', $request->year2)
                ->whereMonth('booking_date', $month)
                ->where('status', '!=', 'CANCELLED');
            if ($request->branchId) { $q1->where('branch_id', $request->branchId); $q2->where('branch_id', $request->branchId); }
            $b1 = $q1->get();
            $b2 = $q2->get();
            $result[] = [
                'month'          => $month,
                'year1Revenue'   => (float) $b1->sum('total_agreed_price'),
                'year2Revenue'   => (float) $b2->sum('total_agreed_price'),
                'year1Bookings'  => $b1->count(),
                'year2Bookings'  => $b2->count(),
            ];
        }
        return response()->json($result);
    }

    public function inventoryUtilization(Request $request)
    {
        $request->validate(['dateFrom' => 'required|date', 'dateTo' => 'required|date']);
        $shopId     = $this->shopId();
        $dateFrom   = \Carbon\Carbon::parse($request->dateFrom)->startOfDay();
        $dateTo     = \Carbon\Carbon::parse($request->dateTo)->endOfDay();
        $totalDays  = $dateFrom->diffInDays($dateTo) + 1;

        $items = Item::whereHas('branch', fn($q) => $q->where('shop_id', $shopId))
            ->when($request->branchId, fn($q) => $q->where('branch_id', $request->branchId))
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
                $start = max(\Carbon\Carbon::parse($bi->booking->booking_date), $dateFrom);
                $end   = min(\Carbon\Carbon::parse($bi->booking->return_date),  $dateTo);
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
}
