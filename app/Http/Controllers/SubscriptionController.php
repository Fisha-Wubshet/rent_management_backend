<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function index() { return response()->json(Subscription::with('shop')->get()); }

    public function store(Request $request)
    {
        $data = $request->validate(['shopId' => 'required|integer', 'planName' => 'nullable|string', 'amountPaid' => 'nullable|numeric', 'durationDays' => 'required|integer', 'maxBranches' => 'nullable|integer', 'isTrial' => 'nullable|boolean', 'notes' => 'nullable|string']);
        $start = now();
        return response()->json(Subscription::create(['shop_id' => $data['shopId'], 'plan_name' => $data['planName'] ?? 'Basic', 'amount_paid' => $data['amountPaid'] ?? 0, 'start_date' => $start, 'end_date' => $start->copy()->addDays($data['durationDays']), 'max_branches' => $data['maxBranches'] ?? 1, 'is_trial' => $data['isTrial'] ?? false, 'notes' => $data['notes'] ?? null]), 201);
    }

    public function byShop($shopId) { return response()->json(Subscription::where('shop_id', $shopId)->with('shop')->firstOrFail()); }

    public function renew(Request $request, $id)
    {
        $data = $request->validate(['durationDays' => 'required|integer', 'amountPaid' => 'nullable|numeric', 'planName' => 'nullable|string', 'isTrial' => 'nullable|boolean', 'maxBranches' => 'nullable|integer', 'notes' => 'nullable|string']);
        $sub = Subscription::findOrFail($id);
        $sub->update(['end_date' => $sub->end_date->addDays($data['durationDays']), 'amount_paid' => $data['amountPaid'] ?? $sub->amount_paid, 'plan_name' => $data['planName'] ?? $sub->plan_name, 'is_trial' => $data['isTrial'] ?? $sub->is_trial, 'max_branches' => $data['maxBranches'] ?? $sub->max_branches, 'notes' => $data['notes'] ?? $sub->notes, 'renewed_at' => now()]);
        return response()->json($sub->fresh());
    }

    public function patch(Request $request, $id)
    {
        Subscription::findOrFail($id)->update($request->only(['plan_name', 'amount_paid', 'start_date', 'end_date', 'max_branches', 'is_trial', 'notes']));
        return response()->json(Subscription::find($id));
    }

    public function suspend($id) { Subscription::findOrFail($id)->update(['is_suspended' => true]); return response()->json(['message' => 'Suspended.']); }
    public function unsuspend($id) { Subscription::findOrFail($id)->update(['is_suspended' => false]); return response()->json(['message' => 'Unsuspended.']); }
}
