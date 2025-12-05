<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminInventoryController extends Controller
{
    /**
     * Lấy danh sách kho hàng (Sản phẩm kèm biến thể & tồn kho)
     * GET: /api/admin/inventory
     */
    public function index(Request $request)
    {
        try {
            // 1. Eager Load sâu hơn: Lấy variants kèm attribute_values và attribute của nó
            $products = Product::with([
                'category',
                'brand',
                'variants.attributeValues.attribute' // Load quan hệ lồng nhau để lấy tên thuộc tính (Màu, Size)
            ])
            ->where('status', 'active')
            ->whereNull('deleted_at')
            ->orderBy('created_at', 'desc')
            ->get();

            // 2. Xử lý dữ liệu (Transform)
            $products->each(function ($product) {
                // Fix ảnh Product
                if ($product->thumbnail_url) {
                    // Xóa dấu / ở đầu để tránh double slash khi dùng asset()
                    $path = ltrim($product->thumbnail_url, '/');
                    $product->thumbnail_url = asset($path);
                }

                // Fix ảnh & Tạo tên thuộc tính cho từng Variant
                $product->variants->each(function ($variant) {
                    // a. Fix ảnh Variant
                    if ($variant->image) {
                        $path = ltrim($variant->image, '/');
                        $variant->image = asset($path);
                    }

                    // b. Tạo chuỗi thuộc tính (Ví dụ: "Đen - 256GB")
                    $attributes = $variant->attributeValues->map(function ($av) {
                        return $av->value; // Lấy giá trị: "Đen", "256GB"
                    })->join(' - ');

                    // Gán thêm field ảo để Frontend dễ hiển thị
                    $variant->attributes_text = $attributes;
                });
            });

            return response()->json([
                'status' => 'success',
                'data' => $products
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lỗi khi lấy dữ liệu kho: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cập nhật nhanh số lượng tồn kho
     * PUT: /api/admin/inventory/variants/{id}/stock
     */
    public function updateStock(Request $request, $id)
    {
        $request->validate(['stock' => 'required|integer|min:0']);

        try {
            $variant = Variant::find($id);
            if (!$variant) {
                return response()->json(['status' => 'error', 'message' => 'Variant not found'], 404);
            }

            $variant->stock = $request->stock;
            $variant->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Cập nhật thành công',
                'data' => $variant
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}