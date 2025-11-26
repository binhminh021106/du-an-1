<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Variant;
use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Support\Facades\DB;

class AdminVariantController extends Controller
{
    // 1. CHỈ TẠO BIẾN THỂ (GIÁ, KHO)
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:product,id',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
        ]);

        // Chỉ tạo variant, chưa quan tâm attributes
        $variant = Variant::create([
            'product_id' => $request->product_id,
            'price' => $request->price,
            'original_price' => $request->original_price ?? 0,
            'stock' => $request->stock,
        ]);

        return response()->json($variant, 201);
    }

    // 2. CHỈ CẬP NHẬT GIÁ, KHO
    public function update(Request $request, $id)
    {
        $variant = Variant::findOrFail($id);
        
        $variant->update($request->only([
            'price', 'original_price', 'stock'
        ]));

        return response()->json($variant);
    }

    // 3. API MỚI: CHUYÊN XỬ LÝ THUỘC TÍNH (GỬI RIÊNG)
    // Route gợi ý: POST /api/admin/variants/{id}/attributes
    public function updateAttributes(Request $request, $id)
    {
        $variant = Variant::findOrFail($id);
        
        // Payload mong đợi: { "attributes": { "Màu": "Đỏ", "Ram": "8GB" } }
        $attributes = $request->input('attributes', []);

        if (!is_array($attributes) || empty($attributes)) {
            return response()->json(['message' => 'Không có thuộc tính nào để lưu'], 200);
        }

        DB::beginTransaction();
        try {
            // Xóa liên kết cũ để cập nhật mới sạch sẽ
            DB::table('variant_attribute_values')->where('variant_id', $variant->id)->delete();

            foreach ($attributes as $attrName => $attrValueStr) {
                if (trim($attrValueStr) === '') continue;

                // A. Tìm/Tạo Tên Thuộc tính
                $attribute = Attribute::firstOrCreate(['name' => $attrName]);

                // B. Tìm/Tạo Giá trị (Gắn với attribute_id)
                $attrValue = AttributeValue::firstOrCreate([
                    'attribute_id' => $attribute->id,
                    'value' => $attrValueStr
                ]);

                // C. Tạo liên kết vào bảng trung gian
                // Sử dụng DB::table để tránh lỗi timestamp nếu bảng trung gian không có created_at
                DB::table('variant_attribute_values')->insertOrIgnore([
                    'variant_id' => $variant->id,
                    'attribute_value_id' => $attrValue->id
                ]);
            }

            DB::commit();
            return response()->json(['message' => 'Cập nhật thuộc tính thành công']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Lỗi lưu thuộc tính: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $variant = Variant::findOrFail($id);
            DB::table('variant_attribute_values')->where('variant_id', $id)->delete();
            $variant->delete();
            return response()->json(['message' => 'Deleted']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi xóa: ' . $e->getMessage()], 500);
        }
    }
}