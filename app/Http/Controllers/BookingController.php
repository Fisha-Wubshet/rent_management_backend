<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingChangeLog;
use App\Models\Item;
use App\Models\ItemBlock;
use App\Services\BookingService;
use App\Services\AuditLogService;
use App\Services\PdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class BookingController extends Controller
{
    public function __construct(
        private BookingService $bookingService,
        private AuditLogService $audit,
        private PdfService $pdfService
    ) {}

    private function actorName(): string
    {
        $u = auth('api')->user();
        return trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: $u->email;
    }

    private function authorizedBooking(int $id): Booking
    {
        $booking = Booking::findOrFail($id);
        $user    = auth('api')->user();
        $shopId  = $user->shop_id ?? $user->branch->shop_id;
        if ((int) $booking->shop_id !== (int) $shopId) abort(403, 'Access denied.');
        return $booking;
    }

    private function authorizedBookingItem(int $itemId): BookingItem
    {
        $bi     = BookingItem::with('booking')->findOrFail($itemId);
        $user   = auth('api')->user();
        $shopId = $user->shop_id ?? $user->branch->shop_id;
        if ((int) $bi->booking->shop_id !== (int) $shopId) abort(403, 'Access denied.');
        return $bi;
    }

    public function createInvoice(Request $request)
    {
        $user = auth('api')->user();
        $dateRule = $user->hasRole('ROLE_STAFF') ? 'required|date|after_or_equal:today' : 'required|date';

        $data = $request->validate([
            'firstName'                => 'required|string',
            'lastName'                 => 'required|string',
            'phoneNumber'              => 'required|string',
            'altPhoneNumber'           => 'nullable|string',
            'bookingDate'              => $dateRule,
            'returnDate'               => 'required|date|after_or_equal:bookingDate',
            'customerId'               => 'nullable|integer',
            'totalAgreedPrice'         => 'required|numeric|min:0',
            'totalAdvancePayment'      => 'nullable|numeric|min:0',
            'securityDeposit'          => 'nullable|numeric|min:0',
            'itemIds'                  => 'required|array|min:1',
            'itemIds.*'                => 'required|integer',
            'bypassCleaningGapItemIds' => 'nullable|array',
            'bypassCleaningGapItemIds.*' => 'integer',
        ]);

        if (($data['totalAdvancePayment'] ?? 0) > $data['totalAgreedPrice']) {
            abort(400, 'Advance payment cannot exceed the total agreed price.');
        }

        $data['items'] = array_map(fn($id) => ['itemId' => $id], $data['itemIds']);

        $shopId   = $user->shop_id ?? $user->branch->shop_id;
        $branchId = (int) ($request->branchId ?? $user->branch_id ?? $user->branch?->id);
        $booking  = $this->bookingService->createInvoice($data, $shopId, $branchId, $user->email);
        return response()->json($booking, 201);
    }

    public function index(Request $request)
    {
        $user = auth('api')->user();
        $shopId = $user->shop_id ?? $user->branch->shop_id;
        $query = Booking::with('items.item', 'customer', 'branch')
            ->where('shop_id', $shopId)
            ->where('booking_type', '!=', 'MAINTENANCE');

        if ($user->branch_id) $query->where('branch_id', $user->branch_id);
        if ($request->branchId) $query->where('branch_id', $request->branchId);
        if ($request->status) $query->where('status', $request->status);
        if ($request->startDate)     $query->where('booking_date', '>=', $request->startDate);
        if ($request->endDate)       $query->where('booking_date', '<=', $request->endDate);
        if ($request->phone)         $query->where('phone_number', 'like', "%{$request->phone}%");
        if ($request->invoiceNumber) $query->where('invoice_number', 'like', "%{$request->invoiceNumber}%");
        if ($request->bookingType)   $query->where('booking_type', $request->bookingType);
        if ($request->dressCode)     $query->whereHas('items.item', fn($q) => $q->where('unique_code', $request->dressCode));

        return response()->json($query->orderByDesc('id')->paginate($request->size ?? 20));
    }

    public function show($id)
    {
        $booking = $this->authorizedBooking((int) $id);
        return response()->json($booking->load('items.item', 'customer', 'branch', 'changeLogs'));
    }

    public function updateStatus(Request $request, $id)
    {
        $data = $request->validate(['status' => 'required|in:CONFIRMED,PICKED_UP,RETURNED,CANCELLED']);
        $booking = $this->authorizedBooking((int) $id);
        $booking->update(['status' => $data['status']]);
        $user = auth('api')->user();
        $this->audit->log('Booking', $booking->id, 'STATUS_CHANGED', $user->email, $booking->shop_id, $booking->branch_id, "Status → {$data['status']}", $this->actorName());
        return response()->json($booking->load('items.item', 'customer', 'branch'));
    }

    public function payDue(Request $request, $id)
    {
        $data = $request->validate(['amount' => 'required|numeric|min:0']);
        $booking = $this->authorizedBooking((int) $id);
        if (in_array($booking->status, ['RETURNED', 'CANCELLED'])) {
            abort(400, 'Cannot record payment on a booking that is already ' . strtolower($booking->status) . '.');
        }
        $newTotal = (float) $booking->total_advance_payment + (float) $data['amount'];
        if ($newTotal > (float) $booking->total_agreed_price) {
            abort(400, 'Payment would exceed the total agreed price.');
        }
        $booking->increment('total_advance_payment', $data['amount']);
        $user = auth('api')->user();
        $this->audit->log('Booking', $booking->id, 'PAYMENT_RECORDED', $user->email, $booking->shop_id, $booking->branch_id, "Paid: {$data['amount']}", $this->actorName());
        return response()->json($booking->fresh()->load('items.item', 'customer', 'branch'));
    }

    public function pickup(Request $request, $id)
    {
        $data = $request->validate(['amount' => 'nullable|numeric|min:0', 'securityDeposit' => 'nullable|numeric|min:0']);
        $booking = $this->authorizedBooking((int) $id);
        if ($booking->status !== 'CONFIRMED') {
            abort(400, 'Only a CONFIRMED booking can be marked as picked up. Current status: ' . $booking->status . '.');
        }
        if ($data['amount'] ?? 0) $booking->increment('total_advance_payment', $data['amount']);
        if ($data['securityDeposit'] ?? 0) $booking->update(['security_deposit' => $data['securityDeposit']]);
        $booking->update(['status' => 'PICKED_UP']);
        $user = auth('api')->user();
        $this->audit->log('Booking', $booking->id, 'PICKED_UP', $user->email, $booking->shop_id, $booking->branch_id, null, $this->actorName());
        return response()->json($booking->fresh()->load('items.item', 'customer', 'branch'));
    }

    public function completeReturn(Request $request, $id)
    {
        $data = $request->validate([
            'amount'                 => 'nullable|numeric|min:0',
            'depositDeduction'       => 'nullable|numeric|min:0',
            'depositDeductionReason' => 'nullable|string|max:500',
        ]);

        $booking = $this->authorizedBooking((int) $id);
        if ($booking->status !== 'PICKED_UP') {
            abort(400, 'Only a PICKED_UP booking can be marked as returned. Current status: ' . $booking->status . '.');
        }
        if ($data['amount'] ?? 0) $booking->increment('total_advance_payment', $data['amount']);

        $bookingStart = $booking->booking_date instanceof \Carbon\Carbon
            ? $booking->booking_date
            : Carbon::parse($booking->booking_date);
        $updates = ['status' => 'RETURNED'];
        if (Carbon::today()->gte($bookingStart)) {
            $updates['return_date'] = Carbon::today()->toDateString();
        }

        if (($booking->security_deposit ?? 0) > 0) {
            $deduction = isset($data['depositDeduction'])
                ? min((float) $data['depositDeduction'], (float) $booking->security_deposit)
                : 0;
            $updates['security_deposit_returned']  = true;
            $updates['deposit_deduction']          = $deduction;
            $updates['deposit_deduction_reason']   = $data['depositDeductionReason'] ?? null;
        }

        $booking->update($updates);
        BookingItem::where('booking_id', $id)->update(['is_returned' => true]);
        $user = auth('api')->user();
        $depositNote = null;
        if (($booking->security_deposit ?? 0) > 0) {
            $deductionAmt = $updates['deposit_deduction'] ?? 0;
            $returnedAmt  = (float) $booking->security_deposit - (float) $deductionAmt;
            $depositNote  = "Deposit: {$deductionAmt} kept, {$returnedAmt} returned";
            if (!empty($data['depositDeductionReason'])) $depositNote .= " ({$data['depositDeductionReason']})";
        }
        $this->audit->log('Booking', $booking->id, 'RETURNED', $user->email, $booking->shop_id, $booking->branch_id, $depositNote, $this->actorName());
        return response()->json($booking->fresh()->load('items.item', 'customer', 'branch'));
    }

    public function releaseCleaning(Request $request, $id)
    {
        $data    = $request->validate([
            'releases'           => 'required|array|min:1',
            'releases.*.itemId'  => 'required|integer',
            'releases.*.count'   => 'required|integer|min:1',
        ]);
        $booking = $this->authorizedBooking((int) $id);
        if ($booking->status !== 'RETURNED') {
            abort(400, 'Cleaning gap can only be released for a RETURNED booking.');
        }

        $releasedSummary = [];
        foreach ($data['releases'] as $rel) {
            $ids = BookingItem::where('booking_id', $booking->id)
                ->where('item_id', $rel['itemId'])
                ->where('cleaning_gap_released', false)
                ->limit($rel['count'])
                ->pluck('id');
            BookingItem::whereIn('id', $ids)->update(['cleaning_gap_released' => true]);
            $name = \App\Models\Item::find($rel['itemId'])?->name ?? (string) $rel['itemId'];
            $releasedSummary[] = "{$rel['count']}× {$name}";
        }

        $user = auth('api')->user();
        $this->audit->log('Booking', $booking->id, 'CLEANING_RELEASED', $user->email, $booking->shop_id, $booking->branch_id, implode(', ', $releasedSummary), $this->actorName());
        return response()->json($booking->fresh()->load('items.item', 'customer', 'branch'));
    }

    public function cancel($id)
    {
        $booking = $this->authorizedBooking((int) $id);
        if (in_array($booking->status, ['RETURNED', 'CANCELLED'])) {
            abort(400, 'Booking is already ' . strtolower($booking->status) . ' and cannot be cancelled.');
        }
        $booking->update(['status' => 'CANCELLED']);
        $user = auth('api')->user();
        $this->audit->log('Booking', $booking->id, 'CANCELLED', $user->email, $booking->shop_id, $booking->branch_id, null, $this->actorName());
        return response()->json($booking->load('items.item', 'customer', 'branch'));
    }

    public function update(Request $request, $id)
    {
        $booking = $this->authorizedBooking((int) $id);
        $request->validate([
            'bookingDate'      => 'nullable|date',
            'returnDate'       => 'nullable|date',
            'totalAgreedPrice' => 'nullable|numeric|min:0',
            'totalAdvancePayment' => 'nullable|numeric|min:0',
            'depositDeduction' => 'nullable|numeric|min:0',
        ]);

        $existingBookingDate = $booking->booking_date instanceof \Carbon\Carbon
            ? $booking->booking_date->toDateString()
            : (string) $booking->booking_date;
        $existingReturnDate = $booking->return_date instanceof \Carbon\Carbon
            ? $booking->return_date->toDateString()
            : (string) $booking->return_date;

        $newBookingDate = $request->input('bookingDate') ?? $existingBookingDate;
        $newReturnDate  = $request->input('returnDate')  ?? $existingReturnDate;
        $datesChanged   = ($request->input('bookingDate') !== null && $request->input('bookingDate') !== $existingBookingDate)
                       || ($request->input('returnDate')  !== null && $request->input('returnDate')  !== $existingReturnDate);

        if ($datesChanged) {
            $itemIds = $booking->items->pluck('item_id')->toArray();
            if (!empty($itemIds)) {
                $this->bookingService->checkConflicts($itemIds, $newBookingDate, $newReturnDate, (int) $id);
            }
        }

        $fields = array_filter([
            'first_name'                => $request->input('firstName'),
            'last_name'                 => $request->input('lastName'),
            'phone_number'              => $request->input('phoneNumber'),
            'alt_phone_number'          => $request->input('altPhoneNumber'),
            'booking_date'              => $request->input('bookingDate'),
            'return_date'               => $request->input('returnDate'),
            'total_agreed_price'        => $request->input('totalAgreedPrice'),
            'total_advance_payment'     => $request->input('totalAdvancePayment'),
            'security_deposit'          => $request->input('securityDeposit'),
            'security_deposit_returned' => $request->input('securityDepositReturned'),
            'deposit_deduction'         => $request->input('depositDeduction'),
            'deposit_deduction_reason'  => $request->input('depositDeductionReason'),
        ], fn($v) => !is_null($v));
        $newAgreedPrice    = $request->input('totalAgreedPrice')    ?? $booking->total_agreed_price;
        $newAdvancePayment = $request->input('totalAdvancePayment') ?? $booking->total_advance_payment;
        if ($newBookingDate && $newReturnDate && $newBookingDate > $newReturnDate) {
            abort(400, 'Return date must be on or after the pickup date.');
        }
        if ((float) $newAdvancePayment > (float) $newAgreedPrice) {
            abort(400, 'Advance payment cannot exceed the total agreed price.');
        }
        $diffs = [];
        if (!is_null($request->input('totalAgreedPrice')) && $request->input('totalAgreedPrice') != $booking->total_agreed_price)
            $diffs[] = "Price: {$booking->total_agreed_price}→{$request->input('totalAgreedPrice')}";
        if (!is_null($request->input('bookingDate')) && $request->input('bookingDate') !== $existingBookingDate)
            $diffs[] = "Pickup: {$existingBookingDate}→{$request->input('bookingDate')}";
        if (!is_null($request->input('returnDate')) && $request->input('returnDate') !== $existingReturnDate)
            $diffs[] = "Return: {$existingReturnDate}→{$request->input('returnDate')}";
        if (!is_null($request->input('firstName')) && $request->input('firstName') !== $booking->first_name)
            $diffs[] = "Name changed";

        $booking->update($fields);
        $user = auth('api')->user();
        if ($diffs) {
            $this->audit->log('Booking', $booking->id, 'EDITED', $user->email, $booking->shop_id, $booking->branch_id, implode('; ', $diffs), $this->actorName());
        }
        return response()->json($booking->fresh()->load('items.item', 'customer', 'branch'));
    }

    public function dashboard(Request $request)
    {
        $request->validate(['pickupDate' => 'required|date', 'returnDate' => 'nullable|date', 'branchId' => 'nullable|integer', 'excludeBookingId' => 'nullable|integer']);
        $user             = auth('api')->user();
        $branchId         = $request->branchId ?? $user->branch_id;
        $pickup           = $request->pickupDate;
        $return           = $request->returnDate ?? $pickup;
        $excludeBookingId = $request->excludeBookingId;

        // Load items with category only; availability is computed per-item below
        // so that the cleaning-gap expansion is applied correctly per item.
        $items = Item::where('branch_id', $branchId)->with('category')->get();

        return response()->json($items->map(function ($item) use ($pickup, $return, $excludeBookingId) {
            // Expand the check window by 1 day on each side for items that need a cleaning gap.
            $checkStart = $item->has_cleaning_gap
                ? Carbon::parse($pickup)->subDay()->toDateString()
                : $pickup;
            $checkEnd = $item->has_cleaning_gap
                ? Carbon::parse($return)->addDay()->toDateString()
                : $return;

            $bookingItems = BookingItem::where('item_id', $item->id)
                ->whereHas('booking', function ($bq) use ($checkStart, $checkEnd, $excludeBookingId) {
                    $bq->where(function ($sq) {
                            $sq->whereNull('status')->orWhereNotIn('status', ['CANCELLED', 'RETURNED']);
                        })
                        ->whereDate('booking_date', '<=', $checkEnd)
                        ->where(function ($rq) use ($checkStart) {
                            $rq->whereNull('return_date')
                               ->orWhereDate('return_date', '>=', $checkStart);
                        });
                    if ($excludeBookingId) $bq->where('id', '!=', $excludeBookingId);
                })
                ->with('booking.customer')
                ->get();

            $blockedCount   = (int) ItemBlock::where('item_id', $item->id)
                ->where('start_date', '<=', $checkEnd)
                ->where('end_date',   '>=', $checkStart)
                ->sum('quantity');

            $bookedCount    = $bookingItems->count() + $blockedCount;
            $availableCount = max(0, $item->quantity - $bookedCount);

            // Compute cleaning units: RETURNED booking-items whose cleaning window overlaps the requested dates.
            $cleaningUnits = 0;
            if ($item->has_cleaning_gap) {
                try {
                    $cleaningUnits = BookingItem::where('item_id', $item->id)
                        ->where('cleaning_gap_released', false)
                        ->whereHas('booking', function ($q) use ($checkStart, $return) {
                            $q->where('status', 'RETURNED')
                              ->whereDate('return_date', '>=', $checkStart)
                              ->whereDate('return_date', '<=', $return);
                        })
                        ->count();
                } catch (\Exception $e) {
                    $cleaningUnits = 0;
                }
            }

            // For display purposes, cleaning units reduce the visible available count.
            $displayAvailable = max(0, $availableCount - $cleaningUnits);
            $status = $displayAvailable > 0 ? 'AVAILABLE'
                    : ($cleaningUnits   > 0 ? 'CLEANING' : 'BOOKED');

            $firstBooking = $bookingItems->first()?->booking;

            return [
                'itemId'          => $item->id,
                'unique_code'     => $item->unique_code,
                'itemName'        => $item->name,
                'minPrice'        => (float) $item->min_price,
                'category'        => $item->category?->name,
                'quantity'        => $item->quantity,
                'availableUnits'  => $displayAvailable,
                'bookedCount'     => $bookedCount,
                'cleaningUnits'   => $cleaningUnits,
                'has_cleaning_gap' => (bool) $item->has_cleaning_gap,
                'image_url'       => $item->image_url,
                'status'          => $status,
                'bookingDetails'  => $firstBooking ? [
                    'customerName'   => $firstBooking->first_name . ' ' . $firstBooking->last_name,
                    'phone_number'   => $firstBooking->phone_number,
                    'booking_date'   => $firstBooking->booking_date,
                    'return_date'    => $firstBooking->return_date,
                    'invoice_number' => $firstBooking->invoice_number,
                    'status'         => $firstBooking->status,
                ] : null,
            ];
        }));
    }

    public function todayPickups(Request $request)
    {
        $user = auth('api')->user();
        $shopId = $user->shop_id ?? $user->branch->shop_id;
        $query = Booking::with('items.item', 'customer')
            ->where('booking_date', today()->toDateString())
            ->where('status', 'CONFIRMED');
        if ($user->branch_id) $query->where('branch_id', $user->branch_id);
        else $query->where('shop_id', $shopId);
        return response()->json($query->get());
    }

    public function dueToday(Request $request)
    {
        $user = auth('api')->user();
        $shopId = $user->shop_id ?? $user->branch->shop_id;
        $query = Booking::with('items.item', 'customer')
            ->where('return_date', today()->toDateString())
            ->where('status', 'PICKED_UP');
        if ($user->branch_id) $query->where('branch_id', $user->branch_id);
        else $query->where('shop_id', $shopId);
        return response()->json($query->get());
    }

    public function overdue()
    {
        $user = auth('api')->user();
        $shopId = $user->shop_id ?? $user->branch->shop_id;
        $query = Booking::with('items.item', 'customer')
            ->where('return_date', '<', today()->toDateString())
            ->where('status', 'PICKED_UP');
        if ($user->branch_id) $query->where('branch_id', $user->branch_id);
        else $query->where('shop_id', $shopId);
        return response()->json($query->get());
    }

    public function setUnavailable(Request $request)
    {
        $data = $request->validate([
            'dressId'   => 'required|integer',
            'startDate' => 'required|date',
            'endDate'   => 'required|date|after_or_equal:startDate',
            'quantity'  => 'nullable|integer|min:1',
            'reason'    => 'nullable|string|max:255',
        ]);
        $user     = auth('api')->user();
        $shopId   = $user->shop_id ?? $user->branch->shop_id;
        $item     = Item::findOrFail($data['dressId']);
        $quantity = $data['quantity'] ?? 1;

        $checkStart = $item->has_cleaning_gap
            ? Carbon::parse($data['startDate'])->subDay()->toDateString()
            : $data['startDate'];
        $checkEnd = $item->has_cleaning_gap
            ? Carbon::parse($data['endDate'])->addDay()->toDateString()
            : $data['endDate'];

        $bookedByCustomers = BookingItem::where('item_id', $item->id)
            ->whereHas('booking', function ($q) use ($checkStart, $checkEnd) {
                $q->where(function ($sq) {
                        $sq->whereNull('status')->orWhereNotIn('status', ['CANCELLED', 'RETURNED']);
                    })
                    ->whereDate('booking_date', '<=', $checkEnd)
                    ->where(function ($rq) use ($checkStart) {
                        $rq->whereNull('return_date')
                           ->orWhereDate('return_date', '>=', $checkStart);
                    });
            })->count();

        $alreadyBlocked = (int) ItemBlock::where('item_id', $item->id)
            ->where('start_date', '<=', $checkEnd)
            ->where('end_date', '>=', $checkStart)
            ->sum('quantity');

        $available = $item->quantity - $bookedByCustomers - $alreadyBlocked;
        if ($quantity > $available) {
            abort(400, "Only {$available} unit(s) of '{$item->name}' available for that date range.");
        }

        ItemBlock::create([
            'item_id'    => $item->id,
            'branch_id'  => $user->branch_id ?? $item->branch_id,
            'shop_id'    => $shopId,
            'start_date' => $data['startDate'],
            'end_date'   => $data['endDate'],
            'quantity'   => $quantity,
            'reason'     => $data['reason'] ?? null,
            'created_by' => $user->email,
        ]);

        return response()->json(['message' => "Item blocked ({$quantity} unit(s))."]);
    }

    public function makeAvailable(Request $request)
    {
        $data = $request->validate(['dressId' => 'required|integer', 'startDate' => 'required|date', 'endDate' => 'required|date']);
        $user   = auth('api')->user();
        $shopId = $user->shop_id ?? $user->branch->shop_id;
        $item   = Item::whereHas('branch', fn($q) => $q->where('shop_id', $shopId))
            ->where('id', $data['dressId'])
            ->firstOrFail();
        ItemBlock::where('item_id', $item->id)
            ->where('start_date', $data['startDate'])
            ->where('end_date', $data['endDate'])
            ->delete();
        return response()->json(['message' => 'Item unblocked.']);
    }

    public function maintenanceBlocks(Request $request)
    {
        $data = $request->validate(['dressId' => 'required|integer']);
        $blocks = ItemBlock::where('item_id', $data['dressId'])
            ->orderBy('start_date')
            ->get(['id', 'start_date', 'end_date', 'quantity', 'reason', 'created_by']);
        return response()->json($blocks);
    }

    public function downloadInvoicePdf($id, Request $request)
    {
        $booking = Booking::with('items.item', 'branch.shop', 'customer')->findOrFail($id);
        $pdf = $this->pdfService->generateInvoice($booking, (bool)$request->withAck);
        return $pdf->download("invoice-{$booking->invoice_number}.pdf");
    }

    public function changeLogs($id)
    {
        $this->authorizedBooking((int) $id);
        $logs = BookingChangeLog::where('booking_id', $id)->orderByDesc('changed_at')->get();
        return response()->json($logs);
    }

    public function changeItems(Request $request, $id)
    {
        $changeUser = auth('api')->user();
        $newPickupRule = $changeUser->hasRole('ROLE_STAFF')
            ? 'required_with:newReturnDate|nullable|date|after_or_equal:today'
            : 'required_with:newReturnDate|nullable|date';

        $data = $request->validate([
            'dressIds'            => 'required|array',
            'newTotalAgreedPrice' => 'nullable|numeric',
            'newAdvancePaid'      => 'nullable|numeric|min:0',
            'newBookingDate'      => $newPickupRule,
            'newReturnDate'       => 'required_with:newBookingDate|nullable|date|after_or_equal:newBookingDate',
            'notes'               => 'nullable|string',
        ]);

        $booking = $this->authorizedBooking((int) $id)->load('items');
        $user    = $changeUser;

        $existingPickup = $booking->booking_date instanceof \Carbon\Carbon
            ? $booking->booking_date->toDateString()
            : (string) $booking->booking_date;
        $existingReturn = $booking->return_date instanceof \Carbon\Carbon
            ? $booking->return_date->toDateString()
            : (string) ($booking->return_date ?? $existingPickup);

        $bookingDate  = $data['newBookingDate'] ?? $existingPickup;
        $returnDate   = $data['newReturnDate']  ?? $existingReturn;
        $datesChanged = $bookingDate !== $existingPickup || $returnDate !== $existingReturn;

        $newTotalPrice     = $data['newTotalAgreedPrice'] ?? $booking->total_agreed_price;
        $newAdvancePayment = isset($data['newAdvancePaid']) ? (float) $data['newAdvancePaid'] : (float) $booking->total_advance_payment;

        if ($newAdvancePayment > (float) $newTotalPrice) {
            abort(400, 'Advance payment cannot exceed the new total agreed price.');
        }

        $result = DB::transaction(function () use ($data, $id, $booking, $user, $bookingDate, $returnDate, $datesChanged, $existingPickup, $existingReturn, $newTotalPrice, $newAdvancePayment) {
            // Lock all items in the new selection to prevent concurrent double-booking.
            $allItemIds = array_unique($data['dressIds']);
            Item::whereIn('id', $allItemIds)->lockForUpdate()->get();

            // If dates changed, every item in the new selection must be re-checked (with full qty, no dedup)
            if ($datesChanged) {
                $this->bookingService->checkConflicts($data['dressIds'], $bookingDate, $returnDate, $id);
            }

            // Compute add/remove diffs
            $currentItems  = BookingItem::where('booking_id', $id)->get();
            $currentCounts = [];
            foreach ($currentItems as $bi) {
                $currentCounts[$bi->item_id] = ($currentCounts[$bi->item_id] ?? 0) + 1;
            }
            $newCounts = array_count_values($data['dressIds']);
            $allIds    = array_unique(array_merge(array_keys($currentCounts), array_keys($newCounts)));

            $addedCounts   = [];
            $removedCounts = [];

            foreach ($allIds as $itemId) {
                $curr = $currentCounts[$itemId] ?? 0;
                $next = $newCounts[$itemId]     ?? 0;

                if ($next > $curr) {
                    if (!$datesChanged) {
                        // Dates unchanged: check only the additional units being added
                        $additionalUnits = array_fill(0, $next - $curr, $itemId);
                        $this->bookingService->checkConflicts($additionalUnits, $bookingDate, $returnDate, $id);
                    }
                    $name = Item::find($itemId)?->name ?? (string) $itemId;
                    for ($i = 0; $i < ($next - $curr); $i++) {
                        BookingItem::create(['booking_id' => $id, 'item_id' => $itemId]);
                    }
                    $addedCounts[$name] = ($addedCounts[$name] ?? 0) + ($next - $curr);
                } elseif ($curr > $next) {
                    $name    = Item::find($itemId)?->name ?? (string) $itemId;
                    $records = BookingItem::where('booking_id', $id)->where('item_id', $itemId)->take($curr - $next)->get();
                    foreach ($records as $bi) { $bi->delete(); }
                    $removedCounts[$name] = ($removedCounts[$name] ?? 0) + ($curr - $next);
                }
            }

            $formatSummary = fn(array $counts) => implode(', ', array_map(
                fn($name, $qty) => $qty > 1 ? "{$qty}× {$name}" : $name,
                array_keys($counts), $counts
            ));

            $additionalPayment = $newAdvancePayment - (float) $booking->total_advance_payment;

            $dateChangeSummary = null;
            if ($datesChanged) {
                $dateChangeSummary = "{$existingPickup}→{$bookingDate} / {$existingReturn}→{$returnDate}";
            }

            BookingChangeLog::create([
                'booking_id'            => $id,
                'changed_by_id'         => $user->id,
                'changed_by_name'       => $user->first_name . ' ' . $user->last_name,
                'removed_items_summary' => $removedCounts    ? $formatSummary($removedCounts) : null,
                'added_items_summary'   => $addedCounts      ? $formatSummary($addedCounts)   : null,
                'old_total_price'       => $booking->total_agreed_price,
                'new_total_price'       => $newTotalPrice,
                'new_advance_payment'   => $newAdvancePayment,
                'additional_payment'    => $additionalPayment,
                'date_change_summary'   => $dateChangeSummary,
                'notes'                 => $data['notes'] ?? null,
                'changed_at'            => now(),
            ]);

            $auditParts = [];
            if ($addedCounts)   $auditParts[] = 'Added: '   . $formatSummary($addedCounts);
            if ($removedCounts) $auditParts[] = 'Removed: ' . $formatSummary($removedCounts);
            if ($datesChanged)  $auditParts[] = "Dates: {$dateChangeSummary}";
            if (isset($data['newTotalAgreedPrice'])) $auditParts[] = "Price: {$booking->total_agreed_price}→{$newTotalPrice}";
            $this->audit->log('Booking', $booking->id, 'MODIFIED', $user->email, $booking->shop_id, $booking->branch_id, implode('; ', $auditParts) ?: null, $this->actorName());

            $updates = [];
            if (isset($data['newTotalAgreedPrice'])) $updates['total_agreed_price']    = $newTotalPrice;
            if (isset($data['newAdvancePaid']))       $updates['total_advance_payment'] = $newAdvancePayment;
            if ($datesChanged) {
                $updates['booking_date'] = $bookingDate;
                $updates['return_date']  = $returnDate;
            }
            if ($updates) $booking->update($updates);

            return $booking->fresh()->load('items.item', 'changeLogs');
        });

        return response()->json($result);
    }

    public function markItemReturned(Request $request, $itemId)
    {
        $bi = $this->authorizedBookingItem((int) $itemId);
        if (!in_array($bi->booking->status, ['PICKED_UP', 'RETURNED'])) {
            abort(400, 'Cannot mark an item as returned on a booking with status: ' . $bi->booking->status . '.');
        }
        $bi->update(['is_returned' => true]);
        return response()->json(['message' => 'Item marked as returned.']);
    }

    public function customerBookings(Request $request, $customerId)
    {
        return response()->json(
            Booking::where('customer_id', $customerId)->with('items.item')->orderByDesc('id')->paginate($request->size ?? 20)
        );
    }

    public function searchByPhone(Request $request, $phone)
    {
        $user   = auth('api')->user();
        $shopId = $user->shop_id ?? $user->branch->shop_id;
        return response()->json(
            Booking::where('phone_number', 'like', "%$phone%")
                ->where('shop_id', $shopId)
                ->with('items.item', 'customer')
                ->orderByDesc('id')
                ->paginate($request->size ?? 20)
        );
    }

    public function historyByCode(Request $request, $code)
    {
        $user   = auth('api')->user();
        $shopId = $user->shop_id ?? $user->branch->shop_id;
        $item   = Item::whereHas('branch', fn($q) => $q->where('shop_id', $shopId))
            ->where('unique_code', $code)
            ->firstOrFail();
        return response()->json(
            Booking::whereHas('items', fn($q) => $q->where('item_id', $item->id))
                ->where('shop_id', $shopId)
                ->with('items.item', 'customer')
                ->orderByDesc('id')
                ->paginate($request->size ?? 20)
        );
    }
}
