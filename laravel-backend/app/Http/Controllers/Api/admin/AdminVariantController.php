<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Variant;

class AdminVariantController extends Controller
{
    public function store(Request $request)
    {
        // Validate
        $request->validate([
            'product_id' => 'required', // Cần thiết để link với sản phẩm
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
        ]);

        // Tạo biến thể mới
        $variant = Variant::create([
            'product_id' => $request->product_id,
            'price' => $request->price,
            'original_price' => $request->original_price ?? 0,
            'stock' => $request->stock,
            // Nếu bạn lưu attributes dạng JSON vào cột 'attributes' trong bảng variants
            'attributes' => $request->attributes 
        ]);

        return response()->json($variant, 201);
    }

    public function update(Request $request, $id)
    {
        $variant = Variant::findOrFail($id);
        
        $variant->update($request->only([
            'price', 'original_price', 'stock', 'attributes'
        ]));

        return response()->json($variant);
    }

    public function destroy($id)
    {
        Variant::destroy($id);
        return response()->json(['message' => 'Deleted']);
    }
}