<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product; // Đảm bảo Model Product đã được import

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     * API hiển thị danh sách sản phẩm (cho trang chủ, trang danh mục)
     */
   public function index()
    {
        // Vẫn giữ load variants để có thể tính toán giá hiển thị
        $products = Product::with(['variants', 'category'])
            ->where('status', 'active') // Chỉ lấy sản phẩm đang hoạt động
            ->latest()
            ->get();

        // Xử lý dữ liệu hiển thị cho danh sách
        $products->transform(function ($product) {
            
            // --- Cải thiện Logic (Không cần thiết phải tính ở Backend nếu Frontend đã tính) ---
            // GIỮ LẠI variants. Nếu bạn muốn tối ưu response, có thể chỉ lấy min/max price
            
            // Lấy giá thấp nhất/cao nhất từ các biến thể (variants)
            $minPrice = $product->variants->min('price');
            $maxPrice = $product->variants->max('price');

            // Gán giá hiển thị (để Frontend tiện dùng hoặc fallback)
            $product->display_price = $minPrice ?: ($product->price ?? 0);
            $product->display_price_max = $maxPrice ?: ($product->price ?? 0);
            
            // BỎ DÒNG UNSET NÀY ĐI HOÀN TOÀN:
            // unset($product->variants); // <--- LOẠI BỎ DÒNG NÀY

            // Nếu muốn response NHẸ HƠN, hãy chọn cột cụ thể thay vì dùng unset:
            // $product->variants->each(function ($variant) {
            //     $variant->makeHidden(['created_at', 'updated_at', 'stock']); 
            // });

            return $product;
        });

        return response()->json([
            'status' => 'success',
            'data' => $products
        ]);
    }

    /**
     * Display the specified resource.
     * API hiển thị chi tiết sản phẩm (QUAN TRỌNG: Cần load sâu quan hệ)
     */
    public function show(string $id)
    {
        // Eager Loading sâu (Nested Relations) để lấy Attribute (Màu, Size...)
        // variants.attributeValues.attribute: Đi từ Biến thể -> Giá trị thuộc tính (Đỏ) -> Tên thuộc tính (Màu sắc)
        $product = Product::with([
            'variants.attributeValues.attribute', // Cốt lõi để Frontend vẽ nút chọn
            'images',                             // Ảnh phụ (Gallery)
            'category',                           // Danh mục
            'reviews.user'                        // Đánh giá kèm thông tin người dùng
        ])->find($id);

        if (!$product) {
            return response()->json(['message' => 'Không tìm thấy sản phẩm'], 404);
        }

        // Tính toán các thông số phụ trợ cho Frontend
        $variants = $product->variants;
        
        $product->min_price = $variants->isNotEmpty() ? $variants->min('price') : ($product->price ?? 0);
        $product->max_price = $variants->isNotEmpty() ? $variants->max('price') : ($product->price ?? 0);
        $product->total_stock = $variants->isNotEmpty() ? $variants->sum('stock') : ($product->stock ?? 0);

        // Xử lý ảnh: Nếu không có ảnh gallery, dùng ảnh đại diện làm ảnh đầu tiên
        // Laravel collection helper
        $gallery = collect();
        if ($product->thumbnail_url) {
            $gallery->push($product->thumbnail_url);
        }
        if ($product->images && $product->images->isNotEmpty()) {
            // Giả sử bảng image_product có cột image_url
            $gallery = $gallery->merge($product->images->pluck('image_url'));
        }
        $product->gallery_images = $gallery->unique()->values();

        return response()->json([
            'status' => 'success',
            'data' => $product
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}