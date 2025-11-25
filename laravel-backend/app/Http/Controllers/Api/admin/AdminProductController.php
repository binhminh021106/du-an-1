<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\Variant;
use App\Models\ImageProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;

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
        // 1. Validation lỏng hơn (Bỏ required variants)
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'description' => 'nullable|string', // Cho phép null
            'status' => 'required|in:active,inactive,draft',
            'thumbnail_url' => 'nullable|string',
            // Bỏ validation variants và imageProducts ở đây vì frontend gửi riêng
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
            // 2. Tạo Product
            // Lưu ý: Nếu bảng products của bạn có cột 'product_id' (string), hãy thêm vào fillable trong Model Product
            $productData = [
                'name' => $request->name,
                'category_id' => $request->category_id,
                'description' => $request->description ?? '',
                'status' => $request->status,
                'thumbnail_url' => $request->thumbnail_url,
                'sold_count' => 0,
                'favorite_count' => 0,
                'review_count' => 0,
                'average_rating' => 0.00,
            ];

            // Nếu frontend gửi product_id (ID nghiệp vụ), hãy lưu nó
            if ($request->has('product_id')) {
                $productData['product_id'] = $request->product_id;
            }

            $product = Product::create($productData);

            // Commit transaction (Lưu sản phẩm trước)
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tạo sản phẩm thành công',
                'data' => $product,
                // Trả về ID để frontend dùng tiếp cho variants
                'id' => $product->id, 
                'product_id' => $product->product_id ?? $product->id
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
        // Tìm theo id hoặc product_id
        $product = Product::with(['category', 'variants', 'images', 'comments', 'reviews'])
            ->where('id', $id)
            ->orWhere('product_id', $id)
            ->firstOrFail();
            
        return response()->json([
            'success' => true,
            'data' => $product
        ]);
    }

    public function update(Request $request, string $id)
    {
        $product = Product::where('id', $id)->orWhere('product_id', $id)->firstOrFail();

        // Validate lỏng lẻo cho update
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
            
            // Lưu ý: Variants và Images được cập nhật qua API riêng ở Frontend mới
            // Nhưng nếu muốn giữ tương thích code cũ, có thể để logic cũ ở đây (tùy chọn)

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
            $product = Product::where('id', $id)->orWhere('product_id', $id)->firstOrFail();
            
            // Xóa variants và images liên quan trước (nếu DB không set cascade)
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