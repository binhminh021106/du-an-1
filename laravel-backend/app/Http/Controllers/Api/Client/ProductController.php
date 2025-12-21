<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        // Khởi tạo query từ Model Product
        $query = Product::query();

        // [QUAN TRỌNG] Eager Loading:
        // 'variants.attributeValues.attribute': Lấy thông tin Màu/Size để hiển thị tên biến thể (VD: iPhone | Đỏ - 128GB)
        // 'brand', 'category': Để hiển thị thông tin hãng và danh mục
        // 'images': Lấy ảnh phụ
        $query->with([
            'variants.attributeValues.attribute',
            'category',
            'brand',
            'images'
        ]);

        // Chỉ lấy sản phẩm đang hoạt động
        $query->where('status', 'active');

        // --- Logic Lọc ---
        if ($request->has('category_id') && $request->category_id != 'null') {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('keyword') && !empty($request->keyword)) {
            $keyword = $request->keyword;
            $query->where('name', 'like', "%{$keyword}%");
        }

        // Sắp xếp mới nhất
        $query->latest();

        $products = $query->get();

        // [LOGIC XỬ LÝ DỮ LIỆU] Tính toán giá min/max VÀ TÊN BIẾN THỂ
        $products->transform(function ($product) {
            $hasVariants = $product->variants && $product->variants->isNotEmpty();

            if ($hasVariants) {
                // Vì bảng variants thường không có cột 'name', ta phải ghép từ Color, Size...
                $product->variants->transform(function ($variant) {
                    // Nếu variant chưa có tên, hoặc tên rỗng
                    if (empty($variant->name)) {
                        // Lấy danh sách giá trị (VD: ["Đen", "128GB"])
                        $attributes = $variant->attributeValues->map(function ($av) {
                            return $av->value;
                        })->toArray();

                        // Nối lại thành chuỗi: "Đen - 128GB"
                        $variant->name = !empty($attributes) ? implode(' - ', $attributes) : '';
                    }
                    return $variant;
                });

                $minPrice = $product->variants->min('price');
                $maxPrice = $product->variants->max('price');
            } else {
                $minPrice = $product->price ?? 0;
                $maxPrice = $product->price ?? 0;
            }

            $product->price = $minPrice; // Giá hiển thị là giá thấp nhất
            $product->min_price = $minPrice;
            $product->max_price = $maxPrice;

            // Ẩn bớt trường không cần thiết để API nhẹ hơn
            $product->makeHidden(['description', 'content']);

            return $product;
        });

        return response()->json([
            'status' => 'success',
            'data' => $products
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // Load chi tiết kèm đầy đủ quan hệ để hiển thị trang Detail
        $product = Product::with([
            'variants.attributeValues.attribute', // Quan trọng: Lấy tên thuộc tính
            'images',
            'category',
            'brand',
            'reviews.user',
            'comments.user'
        ])->find($id);

        if (!$product) {
            return response()->json(['message' => 'Không tìm thấy sản phẩm'], 404);
        }

        // [LOGIC TÍNH TOÁN CHI TIẾT]
        $variants = $product->variants;
        $hasVariants = $variants && $variants->isNotEmpty();

        if ($hasVariants) {
            $variants->transform(function ($variant) {
                if (empty($variant->name)) {
                    $attributes = $variant->attributeValues->map(function ($av) {
                        return $av->value;
                    })->toArray();
                    $variant->name = !empty($attributes) ? implode(' - ', $attributes) : '';
                }
                return $variant;
            });
        }

        $product->min_price = $hasVariants ? $variants->min('price') : ($product->price ?? 0);
        $product->max_price = $hasVariants ? $variants->max('price') : ($product->price ?? 0);
        $product->total_stock = $hasVariants ? $variants->sum('stock') : ($product->stock ?? 0);

        // Gom ảnh vào gallery
        $gallery = collect();
        if ($product->thumbnail_url) {
            $gallery->push($product->thumbnail_url);
        }
        if ($product->images && $product->images->isNotEmpty()) {
            $gallery = $gallery->merge($product->images->pluck('image_url'));
        }
        $product->gallery_images = $gallery->unique()->values();

        return response()->json([
            'status' => 'success',
            'data' => $product
        ]);
    }

    // Các method chưa dùng
    public function store(Request $request) {}
    public function update(Request $request, string $id) {}
    public function destroy(string $id) {}
}