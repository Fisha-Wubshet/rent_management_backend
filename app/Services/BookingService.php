<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingChangeLog;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemBlock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class BookingService
{
    public function __construct(private AuditLogService $audit) {}

    public function generateInvoiceNumber(): string
    {
        for ($i = 0; $i < 10; $i++) {
            $number = 'REF-' . strtoupper(Str::random(8));
            if (!Booking::where('invoice_number', $number)->exists()) {
                return $number;
            }
        }
        throw new \RuntimeException('Could not generate a unique invoice number after 10 attempts.');
    }

    public function checkConflicts(array $itemIds, string $bookingDate, string $returnDate, ?int $excludeBookingId = null): void
    {
        // Count how many units of each item are requested (duplicates = multiple units)
        $requestedCounts = array_count_values(array_map('intval', $itemIds));

        foreach ($requestedCounts as $itemId => $requestedQty) {
            $item = Item::findOrFail($itemId);
            $checkStart = $bookingDate;
            $checkEnd   = $returnDate;
            if ($item->has_cleaning_gap) {
                $checkStart = Carbon::parse($bookingDate)->subDay()->toDateString();
                $checkEnd   = Carbon::parse($returnDate)->addDay()->toDateString();
            }

            $bookedByCustomers = BookingItem::where('item_id', $itemId)
                ->whereHas('booking', function ($q) use ($checkStart, $checkEnd, $excludeBookingId) {
                    $q->where(function ($sq) {
                            $sq->whereNull('status')->orWhereNotIn('status', ['CANCELLED', 'RETURNED']);
                        })
                        ->where(function ($bq) use ($checkEnd) {
                            $bq->whereNull('booking_date')
                               ->orWhereDate('booking_date', '<=', $checkEnd);
                        })
                        ->where(function ($rq) use ($checkStart) {
                            $rq->whereNull('return_date')
                               ->orWhereDate('return_date', '>=', $checkStart);
                        });
                    if ($excludeBookingId) $q->where('id', '!=', $excludeBookingId);
                })->count();

            $blockedByMaintenance = (int) ItemBlock::where('item_id', $itemId)
                ->where('start_date', '<=', $checkEnd)
                ->where('end_date', '>=', $checkStart)
                ->sum('quantity');

            $available = $item->quantity - $bookedByCustomers - $blockedByMaintenance;
            if ($requestedQty > $available) {
                abort(400, "Only {$available} unit(s) of '{$item->name}' available for the selected date range.");
            }
        }
    }

    public function createInvoice(array $data, int $shopId, int $branchId, string $performedBy): Booking
    {
        return DB::transaction(function () use ($data, $shopId, $branchId, $performedBy) {
            // Lock all requested items for the duration of this transaction to prevent double-booking.
            $itemIds = array_unique(array_column($data['items'], 'itemId'));
            Item::whereIn('id', $itemIds)->lockForUpdate()->get();

            $this->checkConflicts(array_column($data['items'], 'itemId'), $data['bookingDate'], $data['returnDate']);

            // Auto-create or link customer
            $customerId = $data['customerId'] ?? null;
            if (!$customerId && isset($data['phoneNumber'])) {
                $customer = Customer::firstOrCreate(
                    ['phone_number' => $data['phoneNumber'], 'shop_id' => $shopId],
                    ['first_name' => $data['firstName'], 'last_name' => $data['lastName'], 'shop_id' => $shopId]
                );
                $customerId = $customer->id;
            }

            $booking = Booking::create([
                'invoice_number' => $this->generateInvoiceNumber(),
                'booking_date' => $data['bookingDate'],
                'return_date' => $data['returnDate'],
                'first_name' => $data['firstName'],
                'last_name' => $data['lastName'],
                'phone_number' => $data['phoneNumber'],
                'alt_phone_number' => $data['altPhoneNumber'] ?? null,
                'booking_type' => $data['bookingType'] ?? 'CUSTOMER',
                'status' => 'CONFIRMED',
                'customer_id' => $customerId,
                'total_agreed_price' => $data['totalAgreedPrice'],
                'total_advance_payment' => $data['totalAdvancePayment'] ?? 0,
                'security_deposit' => $data['securityDeposit'] ?? 0,
                'shop_id' => $shopId,
                'branch_id' => $branchId,
            ]);

            foreach ($data['items'] as $item) {
                BookingItem::create(['booking_id' => $booking->id, 'item_id' => $item['itemId']]);
            }

            $this->audit->log('Booking', $booking->id, 'CREATED', $performedBy, $shopId, $branchId, "Invoice: {$booking->invoice_number}");
            return $booking->load('items.item', 'customer', 'branch');
        });
    }
}
