<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function __construct(private AuditLogService $audit) {}

    private function shopId(): int
    {
        $user = auth('api')->user();
        return $user->shop_id ?? $user->branch->shop_id;
    }

    private function actorName(): string
    {
        $u = auth('api')->user();
        return trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: $u->email;
    }

    public function index(Request $request)
    {
        $query = Customer::where('shop_id', $this->shopId())->where('deleted', false);
        if ($request->search) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('first_name', 'ilike', "%$s%")
                ->orWhere('last_name', 'ilike', "%$s%")
                ->orWhere('phone_number', 'like', "%$s%"));
        }
        return response()->json($query->orderByDesc('id')->paginate($request->size ?? 20));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'firstName' => 'required|string', 'lastName' => 'required|string',
            'phoneNumber' => ['required', 'string', Rule::unique('customers', 'phone_number')->where('shop_id', $this->shopId())],
            'altPhoneNumber' => 'nullable|string', 'notes' => 'nullable|string',
        ]);
        $customer = Customer::create([
            'first_name' => $data['firstName'],
            'last_name' => $data['lastName'],
            'phone_number' => $data['phoneNumber'],
            'alt_phone_number' => $data['altPhoneNumber'] ?? null,
            'notes' => $data['notes'] ?? null,
            'shop_id' => $this->shopId(),
        ]);
        $this->audit->log('Customer', $customer->id, 'CREATED', auth('api')->user()->email, $this->shopId(), null, "{$customer->first_name} {$customer->last_name} ({$customer->phone_number})", $this->actorName());
        return response()->json($customer, 201);
    }

    public function show($id)
    {
        return response()->json(Customer::where('shop_id', $this->shopId())->findOrFail($id));
    }

    public function byPhone($phone)
    {
        return response()->json(Customer::where('phone_number', $phone)->where('shop_id', $this->shopId())->firstOrFail());
    }

    public function stats($id)
    {
        $customer = Customer::where('shop_id', $this->shopId())->findOrFail($id);
        $bookings = $customer->bookings()->where('status', '!=', 'CANCELLED')->get();
        return response()->json([
            'totalBookings'    => $bookings->count(),
            'totalSpent'       => (float) $bookings->sum('total_agreed_price'),
            'totalPaid'        => (float) $bookings->sum('total_advance_payment'),
            'totalOutstanding' => (float) ($bookings->sum('total_agreed_price') - $bookings->sum('total_advance_payment')),
            'lastBookingDate'  => $bookings->max('booking_date'),
        ]);
    }

    public function update(Request $request, $id)
    {
        $customer = Customer::where('shop_id', $this->shopId())->findOrFail($id);
        $request->validate([
            'phone_number' => ['nullable', 'string', Rule::unique('customers', 'phone_number')->where('shop_id', $this->shopId())->ignore($customer->id)],
        ]);
        $customer->update($request->only(['first_name', 'last_name', 'phone_number', 'alt_phone_number', 'notes']));
        $this->audit->log('Customer', $customer->id, 'UPDATED', auth('api')->user()->email, $this->shopId(), null, "{$customer->first_name} {$customer->last_name}", $this->actorName());
        return response()->json($customer->fresh());
    }

    public function destroy($id)
    {
        $customer = Customer::where('shop_id', $this->shopId())->findOrFail($id);
        $name = "{$customer->first_name} {$customer->last_name}";
        $customer->update(['deleted' => true]);
        $this->audit->log('Customer', $customer->id, 'DELETED', auth('api')->user()->email, $this->shopId(), null, $name, $this->actorName());
        return response()->json(['message' => 'Customer deleted.']);
    }

    public function blacklist(Request $request, $id)
    {
        $data = $request->validate(['reason' => 'required|string']);
        $customer = Customer::where('shop_id', $this->shopId())->findOrFail($id);
        $customer->update(['blacklisted' => true, 'blacklist_reason' => $data['reason'], 'blacklisted_at' => now()]);
        $this->audit->log('Customer', $customer->id, 'BLACKLISTED', auth('api')->user()->email, $this->shopId(), null, "Reason: {$data['reason']}", $this->actorName());
        return response()->json($customer->fresh());
    }

    public function unblacklist($id)
    {
        $customer = Customer::where('shop_id', $this->shopId())->findOrFail($id);
        $customer->update(['blacklisted' => false, 'blacklist_reason' => null, 'blacklisted_at' => null]);
        $this->audit->log('Customer', $customer->id, 'UNBLACKLISTED', auth('api')->user()->email, $this->shopId(), null, "{$customer->first_name} {$customer->last_name}", $this->actorName());
        return response()->json($customer->fresh());
    }

    public function blacklisted(Request $request)
    {
        return response()->json(Customer::where('shop_id', $this->shopId())->where('blacklisted', true)->where('deleted', false)->paginate($request->size ?? 20));
    }

    public function autocomplete(Request $request)
    {
        $s = $request->q;
        return response()->json(Customer::where('shop_id', $this->shopId())->where('deleted', false)
            ->where(fn($q) => $q->where('first_name', 'ilike', "%$s%")->orWhere('last_name', 'ilike', "%$s%")->orWhere('phone_number', 'like', "%$s%"))
            ->limit(10)->get());
    }
}
