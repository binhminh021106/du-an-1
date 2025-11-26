<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminProductController extends Controller
{
    public function index()
    {
        // Lấy kèm variants và images để frontend hiển thị đầy đủ
        $products = Product::with(['category', 'variants', 'images'])
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive,draft',
            'thumbnail_url' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi dữ liệu đầu vào',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::beginTransaction();
        
        try {
            $productData = [
                'name' => $request->name,
                'category_id' => $request->category_id,
                'description' => $request->description ?? '',
                'status' => $request->status,
                'thumbnail_url' => $request->thumbnail_url,
                // Mặc định các trường số
                'sold_count' => 0,
                'favorite_count' => 0,
                'review_count' => 0,
                'average_rating' => 0.00,
            ];

            // FIX: Bỏ check product_id vì DB không có cột này
            $product = Product::create($productData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tạo sản phẩm thành công',
                'data' => $product,
                'id' => $product->id, 
                // Trả về id làm product_id cho frontend dùng tạm
                'product_id' => $product->id 
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tạo sản phẩm: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id)
    {
        // FIX: Chỉ tìm theo ID chính (id)
        $product = Product::with(['category', 'variants', 'images', 'comments', 'reviews'])
            ->findOrFail($id);
            
        return response()->json([
            'success' => true,
            'data' => $product
        ]);
    }

    public function update(Request $request, string $id)
    {
        // FIX: Chỉ tìm theo ID chính (id)
        $product = Product::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'category_id' => 'sometimes|exists:categories,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $product->update($request->only(['name', 'category_id', 'description', 'status', 'thumbnail_url']));
            
            DB::commit();
            return response()->json([
                'success' => true,
                'message' => 'Cập nhật thành công',
                'data' => $product
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            // FIX: Chỉ tìm theo ID chính
            $product = Product::findOrFail($id);
            
            // Xóa variants và images liên quan
            $product->variants()->delete();
            $product->images()->delete();
            $product->delete();

            return response()->json([
                'success' => true,
                'message' => 'Xóa sản phẩm thành công'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xóa: ' . $e->getMessage()
            ], 500);
        }
    }
}