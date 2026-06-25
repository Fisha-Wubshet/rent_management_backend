<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Http\Request;

class BranchController extends Controller
{
    private function shopId(): int { return auth('api')->user()->shop_id; }

    public function index() { return response()->json(Branch::where('shop_id', $this->shopId())->get()); }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string', 'address' => 'nullable|string', 'phone' => 'nullable|string']);
        $branch = Branch::create([...$data, 'shop_id' => $this->shopId()]);
        return response()->json($branch, 201);
    }

    public function show($id) { return response()->json(Branch::where('shop_id', $this->shopId())->findOrFail($id)); }

    public function update(Request $request, $id)
    {
        $branch = Branch::where('shop_id', $this->shopId())->findOrFail($id);
        $branch->update($request->only(['name', 'address', 'phone']));
        return response()->json($branch->fresh());
    }

    public function destroy($id)
    {
        Branch::where('shop_id', $this->shopId())->findOrFail($id)->delete();
        return response()->json(['message' => 'Branch deleted.']);
    }
}
