<?php
namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    private function branchId(Request $request): int
    {
        $user = auth('api')->user();
        $id   = (int) ($request->branchId ?? $user->branch_id ?? 0);
        if (!$id) abort(422, 'A branch must be selected.');
        return $id;
    }

    public function index(Request $request)
    {
        $branchId = $this->branchId($request);
        return response()->json(
            Category::where('branch_id', $branchId)
                    ->where('deleted', false)
                    ->orderBy('name')
                    ->get()
        );
    }

    public function store(Request $request)
    {
        $data     = $request->validate(['name' => 'required|string|max:255']);
        $branchId = $this->branchId($request);
        $category = Category::create([
            'name'      => $data['name'],
            'shop_id'   => auth('api')->user()->shop_id ?? auth('api')->user()->branch->shop_id,
            'branch_id' => $branchId,
        ]);
        return response()->json($category, 201);
    }

    public function update(Request $request, $id)
    {
        $data     = $request->validate(['name' => 'required|string|max:255']);
        $branchId = $this->branchId($request);
        $category = Category::where('id', $id)->where('branch_id', $branchId)->firstOrFail();
        $category->update(['name' => $data['name']]);
        return response()->json($category);
    }

    public function destroy(Request $request, $id)
    {
        $branchId = $this->branchId($request);
        $category = Category::where('id', $id)->where('branch_id', $branchId)->firstOrFail();
        $category->update(['deleted' => true]);
        return response()->json(['message' => 'Category deleted']);
    }
}
