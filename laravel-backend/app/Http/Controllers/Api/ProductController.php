<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product; // ◀️ Chắc chắn là bạn đã import Model Product
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Hiển thị danh sách products.
     */
    public function index()
    {
        // Lấy tất cả sản phẩm từ database
        $products = Product::all();

        // Trả về dữ liệu dưới dạng JSON
        return response()->json([
            'success' => true,
            'message' => 'Lấy danh sách sản phẩm thành công!',
            'data'    => $products
        ], 200);
    }
}