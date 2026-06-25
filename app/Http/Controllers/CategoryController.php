<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Item;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    private function shopId(): int
    {
        $user = auth('api')->user();
        return $user->shop_id ?? $user->branch->shop_id;
    }

    public function index() { return response()->json(Category::where('shop_id', $this->shopId())->where('deleted', false)->get()); }

    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string']);
        return response()->json(Category::create(['name' => $data['name'], 'shop_id' => $this->shopId()]), 201);
    }

    public function update(Request $request, $id)
    {
        $cat = Category::where('shop_id', $this->shopId())->findOrFail($id);
        $cat->update($request->only(['name']));
        return response()->json($cat->fresh());
    }

    public function destroy($id)
    {
        $cat = Category::where('shop_id', $this->shopId())->findOrFail($id);
        Item::where('category_id', $id)->update(['category_id' => null]);
        $cat->update(['deleted' => true]);
        return response()->json(['message' => 'Category deleted.']);
    }
}
