<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\ItemBlock;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class ItemController extends Controller
{
    public function __construct(private AuditLogService $audit) {}

    private function actorName(): string
    {
        $u = auth('api')->user();
        return trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) ?: $u->email;
    }

    private function authorizedItem(int $id): Item
    {
        $item   = Item::with('branch')->findOrFail($id);
        $user   = auth('api')->user();
        $shopId = $user->shop_id ?? $user->branch->shop_id;
        if ((int) $item->branch->shop_id !== (int) $shopId) abort(403, 'Access denied.');
        return $item;
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string', 'uniqueCode' => 'required|string',
            'minPrice' => 'nullable|numeric|min:0', 'hasCleaningGap' => 'nullable|boolean',
            'quantity' => 'nullable|integer|min:1', 'description' => 'nullable|string',
            'categoryId' => 'nullable|integer', 'branchId' => 'required|integer',
        ]);

        $user = auth('api')->user();
        $shopId = $user->shop_id ?? $user->branch->shop_id;
        $duplicate = Item::whereHas('branch', fn($q) => $q->where('shop_id', $shopId))
            ->where('unique_code', $data['uniqueCode'])
            ->exists();
        if ($duplicate) {
            return response()->json(['message' => 'An item with this code already exists in your shop.'], 422);
        }

        $item = Item::create([
            'name' => $data['name'], 'unique_code' => $data['uniqueCode'],
            'min_price' => $data['minPrice'] ?? 0, 'has_cleaning_gap' => $data['hasCleaningGap'] ?? false,
            'quantity' => $data['quantity'] ?? 1, 'description' => $data['description'] ?? null,
            'category_id' => $data['categoryId'] ?? null, 'branch_id' => $data['branchId'],
        ]);
        $this->audit->log('Item', $item->id, 'CREATED', $user->email, $shopId, $data['branchId'], "Code: {$item->unique_code}, Name: {$item->name}", $this->actorName());
        return response()->json($item->load('category'), 201);
    }

    public function index(Request $request)
    {
        $user     = auth('api')->user();
        $branchId = $request->branchId ?? $user->branch_id;
        $query    = Item::where('branch_id', $branchId)->with('category');
        if ($request->search) $query->where('name', 'ilike', "%{$request->search}%");
        return response()->json($query->orderByDesc('id')->paginate($request->size ?? 20));
    }

    public function searchByCode($code)
    {
        $user   = auth('api')->user();
        $shopId = $user->shop_id ?? $user->branch->shop_id;
        return response()->json(
            Item::whereHas('branch', fn($q) => $q->where('shop_id', $shopId))
                ->where('unique_code', $code)
                ->with('category', 'branch')
                ->firstOrFail()
        );
    }

    public function detail($id)
    {
        $item = $this->authorizedItem((int) $id);

        $confirmedUnits = BookingItem::where('item_id', $id)
            ->whereHas('booking', fn($q) => $q->where('status', 'CONFIRMED')->where('booking_type', '!=', 'MAINTENANCE'))
            ->count();

        $pickedUpUnits = BookingItem::where('item_id', $id)
            ->whereHas('booking', fn($q) => $q->where('status', 'PICKED_UP'))
            ->count();

        // Apply the same cleaning-gap expansion used in dashboard() and checkConflicts()
        // so this view shows consistent availability with the booking flow.
        $checkDate = $item->has_cleaning_gap
            ? Carbon::today()->subDay()->toDateString()
            : today()->toDateString();

        $blockedUnits = (int) ItemBlock::where('item_id', $id)
            ->where('end_date', '>=', $checkDate)
            ->sum('quantity');

        $availableUnits = max(0, $item->quantity - $confirmedUnits - $pickedUpUnits - $blockedUnits);

        return response()->json([
            ...$item->toArray(),
            'confirmedUnits' => $confirmedUnits,
            'pickedUpUnits'  => $pickedUpUnits,
            'blockedUnits'   => $blockedUnits,
            'availableUnits' => $availableUnits,
        ]);
    }

    public function history(Request $request, $id)
    {
        return response()->json(
            BookingItem::where('item_id', $id)
                ->with('booking')
                ->orderByDesc('id')
                ->paginate($request->size ?? 10)
        );
    }

    public function update(Request $request, $id)
    {
        $item = $this->authorizedItem((int) $id);

        $request->validate([
            'quantity' => 'nullable|integer|min:1',
        ]);

        $fields = array_filter([
            'name'             => $request->input('name'),
            'unique_code'      => $request->input('uniqueCode'),
            'min_price'        => $request->input('minPrice'),
            'has_cleaning_gap' => $request->input('hasCleaningGap'),
            'quantity'         => $request->input('quantity'),
            'description'      => $request->input('description'),
            'category_id'      => $request->input('categoryId'),
        ], fn($v) => !is_null($v));

        if (isset($fields['unique_code']) && $fields['unique_code'] !== $item->unique_code) {
            $user   = auth('api')->user();
            $shopId = $user->shop_id ?? $user->branch->shop_id;
            $duplicate = Item::whereHas('branch', fn($q) => $q->where('shop_id', $shopId))
                ->where('unique_code', $fields['unique_code'])
                ->where('id', '!=', $id)
                ->exists();
            if ($duplicate) {
                return response()->json(['message' => 'An item with this code already exists in your shop.'], 422);
            }
        }

        $item->update($fields);
        $user   = auth('api')->user();
        $shopId = $user->shop_id ?? $user->branch->shop_id;
        $this->audit->log('Item', $item->id, 'UPDATED', $user->email, $shopId, $item->branch_id, "Code: {$item->unique_code}", $this->actorName());
        return response()->json($item->fresh()->load('category'));
    }

    public function destroy($id)
    {
        $item = $this->authorizedItem((int) $id);
        if (BookingItem::where('item_id', $id)->exists()) {
            abort(400, 'Cannot delete item with booking history.');
        }
        $user    = auth('api')->user();
        $shopId  = $user->shop_id ?? $user->branch->shop_id;
        $code    = $item->unique_code;
        $name    = $item->name;
        $branchId = $item->branch_id;
        $item->delete();
        $this->audit->log('Item', null, 'DELETED', $user->email, $shopId, $branchId, "Code: {$code}, Name: {$name}", $this->actorName());
        return response()->json(['message' => 'Item deleted.']);
    }

    public function uploadImage(Request $request, $id)
    {
        $request->validate(['image' => 'required|image|max:5120']);
        $item = $this->authorizedItem((int) $id);
        $path = $request->file('image')->store('items', 'public');
        $item->update(['image_url' => '/storage/' . $path]);
        return response()->json(['imageUrl' => $item->image_url]);
    }
}
