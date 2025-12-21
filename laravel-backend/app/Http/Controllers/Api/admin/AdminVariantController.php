<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Variant;
use App\Models\Attribute;
use App\Models\AttributeValue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File; // [QUAN TRỌNG] Dùng Facade File để thao tác thư mục public
use Illuminate\Support\Str;

class AdminVariantController extends Controller
{
    // 1. TẠO BIẾN THỂ (GIÁ, KHO, ẢNH)
    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:product,id',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120'
        ]);

        DB::beginTransaction();
        try {
            // Bước 1: Tạo variant
            $variant = Variant::create([
                'product_id' => $request->product_id,
                'price' => $request->price,
                'original_price' => $request->original_price ?? 0,
                'stock' => $request->stock,
            ]);

            // Bước 2: Xử lý upload ảnh (Lưu thẳng vào public)
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $extension = $file->getClientOriginalExtension();
                
                // Tên file: variant_{id}_{random}.{ext}
                $fileName = 'variant_' . $variant->id . '_' . Str::random(10) . '.' . $extension;
                
                $uploadPath = public_path('variants');

                // Tạo thư mục nếu chưa có
                if (!File::exists($uploadPath)) {
                    File::makeDirectory($uploadPath, 0755, true);
                }

                // Di chuyển file vào đó
                $file->move($uploadPath, $fileName);
                
                // Lưu đường dẫn vào DB (Thêm dấu / ở đầu cho chuẩn)
                $variant->image = '/variants/' . $fileName; 
                $variant->save();
            }

            DB::commit();
            return response()->json($variant, 201);

        } catch (\Exception $e) {
            DB::rollBack();
            // Xóa ảnh rác nếu lỡ upload mà lỗi DB
            if (isset($variant->image) && File::exists(public_path($variant->image))) {
                File::delete(public_path($variant->image));
            }
            return response()->json(['message' => 'Lỗi tạo biến thể: ' . $e->getMessage()], 500);
        }
    }

    // 2. CẬP NHẬT BIẾN THỂ
    public function update(Request $request, $id)
    {
        $variant = Variant::findOrFail($id);
        
        $request->validate([
            'price' => 'numeric|min:0',
            'stock' => 'integer|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120'
        ]);

        $variant->fill($request->only(['price', 'original_price', 'stock']));

        // Xử lý ảnh mới
        if ($request->hasFile('image')) {
            // Xóa ảnh cũ (nếu có) trong thư mục public
            if ($variant->image && File::exists(public_path($variant->image))) {
                File::delete(public_path($variant->image));
            }

            // Lưu ảnh mới vào public/variants
            $file = $request->file('image');
            $extension = $file->getClientOriginalExtension();
            $fileName = 'variant_' . $variant->id . '_' . Str::random(10) . '.' . $extension;
            
            $uploadPath = public_path('variants');
            if (!File::exists($uploadPath)) {
                File::makeDirectory($uploadPath, 0755, true);
            }
            
            $file->move($uploadPath, $fileName);
            $variant->image = '/variants/' . $fileName;
        }

        $variant->save();
        return response()->json($variant);
    }

    // 3. CẬP NHẬT THUỘC TÍNH (Giữ nguyên)
    public function updateAttributes(Request $request, $id)
    {
        $variant = Variant::findOrFail($id);
        $attributes = $request->input('attributes', []);

        if (!is_array($attributes) || empty($attributes)) {
            return response()->json(['message' => 'Không có thuộc tính nào để lưu'], 200);
        }

        DB::beginTransaction();
        try {
            DB::table('variant_attribute_values')->where('variant_id', $variant->id)->delete();

            foreach ($attributes as $attrName => $attrValueStr) {
                if (trim($attrValueStr) === '') continue;

                $attribute = Attribute::firstOrCreate(['name' => $attrName]);
                $attrValue = AttributeValue::firstOrCreate([
                    'attribute_id' => $attribute->id,
                    'value' => $attrValueStr
                ]);

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

    // XÓA BIẾN THỂ
    public function destroy($id)
    {
        try {
            $variant = Variant::findOrFail($id);
            
            // Xóa ảnh vật lý trong public/variants
            if ($variant->image && File::exists(public_path($variant->image))) {
                File::delete(public_path($variant->image));
            }

            DB::table('variant_attribute_values')->where('variant_id', $id)->delete();
            $variant->delete();
            
            return response()->json(['message' => 'Deleted']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi xóa: ' . $e->getMessage()], 500);
        }
    }
}