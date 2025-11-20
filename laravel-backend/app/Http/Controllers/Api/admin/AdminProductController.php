<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ImageProduct;
use App\Models\Variant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AdminProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Sử dụng Eager Loading để lấy kèm Category và Images
        // Dùng paginate thay vì all() để tối ưu hiệu năng nếu dữ liệu lớn
        $products = Product::with(['category', 'images', 'variants'])
            ->orderBy('created_at', 'desc')
            ->paginate(10); // Lấy 10 sản phẩm mỗi trang

        return response()->json($products);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // 1. Validate dữ liệu đầu vào
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'description' => 'required|string',
            'status' => 'required|in:active,inactive', // Giả sử status là active/inactive
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // Validate mảng ảnh
            // Nếu bạn gửi kèm variants ngay lúc tạo
            'variants' => 'nullable|array', 
            'variants.*.price' => 'required_with:variants|numeric|min:0',
            'variants.*.original_price' => 'required_with:variants|numeric|min:0',
            'variants.*.stock' => 'required_with:variants|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // 2. Sử dụng Transaction để đảm bảo tính toàn vẹn dữ liệu
        DB::beginTransaction();
        try {
            $dataProduct = $request->only([
                'name', 'category_id', 'description', 'status'
            ]);

            // Xử lý upload Thumbnail
            if ($request->hasFile('thumbnail')) {
                $path = $request->file('thumbnail')->store('products/thumbnails', 'public');
                // Lưu đường dẫn đầy đủ hoặc tương đối tùy cấu hình FE của bạn
                $dataProduct['thumbnail_url'] = '/storage/' . $path;
            }

            // Tạo Product
            $product = Product::create($dataProduct);

            // Xử lý upload Album ảnh (image_product)
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $imageFile) {
                    $path = $imageFile->store('products/gallery', 'public');
                    ImageProduct::create([
                        'product_id' => $product->id,
                        'image_url' => '/storage/' . $path,
                    ]);
                }
            }

            // Xử lý tạo Variants (Nếu có gửi kèm)
            if ($request->has('variants')) {
                foreach ($request->variants as $variantData) {
                    Variant::create([
                        'product_id' => $product->id,
                        'price' => $variantData['price'],
                        'original_price' => $variantData['original_price'],
                        'stock' => $variantData['stock']
                    ]);
                }
            }

            DB::commit();

            // Load lại quan hệ để trả về client
            $product->load(['images', 'variants']);

            return response()->json([
                'message' => 'Tạo sản phẩm thành công',
                'data' => $product
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Lỗi khi tạo sản phẩm',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        // Lấy chi tiết sản phẩm kèm theo tất cả quan hệ cần thiết
        $product = Product::with([
            'category', 
            'images', 
            'variants',
            'reviews'
        ])->find($id);

        if (!$product) {
            return response()->json(['message' => 'Không tìm thấy sản phẩm'], 404);
        }

        return response()->json($product);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $product = Product::find($id);
        if (!$product) {
            return response()->json(['message' => 'Không tìm thấy sản phẩm'], 404);
        }

        // 1. Validate
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'category_id' => 'sometimes|exists:categories,id',
            'status' => 'sometimes|in:active,inactive',
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $dataUpdate = $request->only(['name', 'category_id', 'description', 'status']);

            // Xử lý Thumbnail mới (nếu có)
            if ($request->hasFile('thumbnail')) {
                // Xóa ảnh cũ nếu tồn tại (logic tùy chọn)
                if ($product->thumbnail_url) {
                    $oldPath = str_replace('/storage/', '', $product->thumbnail_url);
                    if (Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->delete($oldPath);
                    }
                }

                $path = $request->file('thumbnail')->store('products/thumbnails', 'public');
                $dataUpdate['thumbnail_url'] = '/storage/' . $path;
            }

            $product->update($dataUpdate);

            // Xử lý thêm ảnh vào Album (nếu có)
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $imageFile) {
                    $path = $imageFile->store('products/gallery', 'public');
                    ImageProduct::create([
                        'product_id' => $product->id,
                        'image_url' => '/storage/' . $path,
                    ]);
                }
            }

            // NOTE: Việc update Variants thường phức tạp, nên tách ra API riêng 
            // hoặc xử lý logic sync (xóa cũ thêm mới) tại đây tùy nhu cầu.

            DB::commit();

            return response()->json([
                'message' => 'Cập nhật sản phẩm thành công',
                'data' => $product->fresh(['images'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Lỗi cập nhật', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $product = Product::find($id);

        if (!$product) {
            return response()->json(['message' => 'Không tìm thấy sản phẩm'], 404);
        }

        try {
            // Gọi delete() sẽ kích hoạt SoftDeletes và hàm booted() trong Model
            // để xóa mềm cả variants và images liên quan
            $product->delete();

            return response()->json(['message' => 'Đã xóa sản phẩm thành công']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi khi xóa sản phẩm', 'error' => $e->getMessage()], 500);
        }
    }
}