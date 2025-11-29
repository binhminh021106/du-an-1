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
    public function index()
    {
        // SỬA: Thêm with('variants') để lấy kèm biến thể
        $products = Product::with('variants')->latest()->get();

        // SỬA: Duyệt qua từng sản phẩm để gán giá hiển thị (nếu giá nằm trong biến thể)
        $products->transform(function ($product) {
            // Cách 1: Lấy giá thấp nhất trong các biến thể (ví dụ: 100k - 200k thì lấy 100k)
            $product->price = $product->variants->min('price');
            
            // Cách 2: Nếu muốn lấy thêm cả giá khuyến mãi (nếu có)
            $product->sale_price = $product->variants->min('sale_price');
            
            // Cách 3: Lấy ảnh đại diện từ biến thể đầu tiên nếu bảng products không có ảnh
            // $product->image = $product->image ?? $product->variants->first()?->image;

            return $product;
        });

        return response()->json($products);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // Hàm này đã có with('variants') nên ổn, nhưng nên xử lý thêm nếu variants rỗng
        $product = Product::with('variants')->find($id);

        if (!$product) {
            return response()->json(['message' => 'Không tìm thấy sản phẩm'], 404);
        }

        // Tính toán giá min/max để hiển thị chi tiết (nếu cần)
        $product->min_price = $product->variants->min('price');
        $product->max_price = $product->variants->max('price');

        return response()->json($product);
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