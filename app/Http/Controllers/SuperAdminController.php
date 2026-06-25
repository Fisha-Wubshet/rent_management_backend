<?php

namespace App\Http\Controllers;

use App\Models\Shop;
use App\Models\Branch;
use App\Models\User;
use App\Models\Subscription;
use App\Models\Booking;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class SuperAdminController extends Controller
{
    public function setupShop(Request $request)
    {
        $data = $request->validate([
            'shopName' => 'required|string', 'shopAddress' => 'nullable|string',
            'itemLabel' => 'nullable|string', 'email' => 'required|email|unique:users',
            'password' => 'required|min:6', 'firstName' => 'required|string', 'lastName' => 'required|string',
            'firstBranchName' => 'required|string', 'firstBranchAddress' => 'nullable|string',
            'durationDays' => 'required|integer', 'maxBranches' => 'nullable|integer',
            'isTrial' => 'nullable|boolean', 'amountPaid' => 'nullable|numeric',
            'planName' => 'nullable|string', 'subscriptionNotes' => 'nullable|string',
        ]);

        return DB::transaction(function () use ($data) {
            $shop = Shop::create(['name' => $data['shopName'], 'address' => $data['shopAddress'] ?? null, 'item_label' => $data['itemLabel'] ?? 'Dress']);
            $branch = Branch::create(['name' => $data['firstBranchName'], 'address' => $data['firstBranchAddress'] ?? null, 'shop_id' => $shop->id]);
            $user = User::create(['first_name' => $data['firstName'], 'last_name' => $data['lastName'], 'email' => $data['email'], 'password' => Hash::make($data['password']), 'shop_id' => $shop->id]);
            $user->assignRole('ROLE_SHOP_ADMIN');
            $start = now();
            Subscription::create(['shop_id' => $shop->id, 'plan_name' => $data['planName'] ?? 'Basic', 'amount_paid' => $data['amountPaid'] ?? 0, 'start_date' => $start, 'end_date' => $start->copy()->addDays($data['durationDays']), 'max_branches' => $data['maxBranches'] ?? 1, 'is_trial' => $data['isTrial'] ?? false, 'notes' => $data['subscriptionNotes'] ?? null]);
            return response()->json(['shop' => $shop, 'branch' => $branch, 'user' => $user], 201);
        });
    }

    public function listShops(Request $request)
    {
        return response()->json(Shop::with('subscription')->orderByDesc('id')->paginate($request->size ?? 20));
    }

    public function showShop($id)
    {
        return response()->json(Shop::with('branches', 'users', 'subscription')->findOrFail($id));
    }

    public function updateShop(Request $request, $id)
    {
        $shop = Shop::findOrFail($id);
        $shop->update($request->only(['name', 'address', 'item_label']));
        return response()->json($shop->fresh());
    }

    public function deleteShop($id) { Shop::findOrFail($id)->delete(); return response()->json(['message' => 'Shop deleted.']); }
    public function banShop($id) { Shop::findOrFail($id)->update(['banned' => true]); return response()->json(['message' => 'Shop banned.']); }
    public function unbanShop($id) { Shop::findOrFail($id)->update(['banned' => false]); return response()->json(['message' => 'Shop unbanned.']); }

    public function listUsers(Request $request)
    {
        return response()->json(User::with('shop', 'branch', 'roles')->orderByDesc('id')->paginate($request->size ?? 20));
    }

    public function banUser($id) { User::findOrFail($id)->update(['banned' => true]); return response()->json(['message' => 'User banned.']); }
    public function unbanUser($id) { User::findOrFail($id)->update(['banned' => false]); return response()->json(['message' => 'User unbanned.']); }

    public function analytics()
    {
        $startOfMonth = now()->startOfMonth();

        $shops = Shop::with('subscription')
            ->withCount(['branches as branch_count', 'users as staff_count', 'bookings as total_bookings'])
            ->get();

        $bookingStats = Booking::where('status', '!=', 'CANCELLED')
            ->selectRaw('shop_id, SUM(total_agreed_price) as revenue, SUM(total_agreed_price - total_advance_payment) as outstanding, MAX(booking_date) as last_booking_date')
            ->groupBy('shop_id')
            ->get()
            ->keyBy('shop_id');

        $subscriptions = Subscription::all();

        $shopBreakdown = $shops->map(function ($shop) use ($bookingStats) {
            $sub = $shop->subscription;
            $stats = $bookingStats[$shop->id] ?? null;
            return [
                'shopName'            => $shop->name,
                'branchCount'         => $shop->branch_count,
                'max_branches'        => $sub?->max_branches ?? 1,
                'staffCount'          => $shop->staff_count,
                'totalBookings'       => $shop->total_bookings,
                'totalRevenue'        => (float) ($stats?->revenue ?? 0),
                'totalOutstanding'    => (float) ($stats?->outstanding ?? 0),
                'subscriptionStatus'  => $sub ? $sub->status : 'NO_SUBSCRIPTION',
                'is_trial'            => $sub?->is_trial ?? false,
                'subscriptionEndDate' => $sub?->end_date?->toDateString(),
                'lastBookingDate'     => $stats?->last_booking_date,
            ];
        });

        return response()->json([
            'totalShops'         => $shops->count(),
            'totalUsers'         => User::count(),
            'totalBookings'      => Booking::count(),
            'totalRevenue'       => (float) Booking::where('status', '!=', 'CANCELLED')->sum('total_agreed_price'),
            'totalCustomers'     => Customer::count(),
            'newShopsThisMonth'  => Shop::where('created_at', '>=', $startOfMonth)->count(),
            'bookingsThisMonth'  => Booking::where('created_at', '>=', $startOfMonth)->where('status', '!=', 'CANCELLED')->count(),
            'totalOutstanding'   => (float) (Booking::where('status', '!=', 'CANCELLED')->sum('total_agreed_price') - Booking::where('status', '!=', 'CANCELLED')->sum('total_advance_payment')),
            'revenueThisMonth'   => (float) Booking::where('created_at', '>=', $startOfMonth)->where('status', '!=', 'CANCELLED')->sum('total_agreed_price'),
            'trialShops'         => $subscriptions->where('is_trial', true)->filter(fn($s) => $s->isAccessAllowed())->count(),
            'expiringSoonShops'  => $subscriptions->filter(fn($s) => $s->status === 'EXPIRING_SOON')->count(),
            'suspendedShops'     => $subscriptions->where('is_suspended', true)->count(),
            'shopBreakdown'      => $shopBreakdown,
        ]);
    }
}
