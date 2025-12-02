<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class AdminProductController extends Controller
{
    /**
     * Lấy danh sách sản phẩm
     */
    public function index()
    {
        // Eager load category để tránh N+1 query
        // Lấy thêm variants và images để hiển thị nhanh nếu cần (ví dụ đếm số lượng variant)
        $products = Product::with(['category', 'variants', 'images'])
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    /**
     * Tạo mới sản phẩm
     */
    public function store(Request $request)
    {
        // 1. Validate dữ liệu đầu vào
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive,draft', // Đồng bộ 3 trạng thái với Frontend
            // Validate ảnh chặt chẽ: Max 5MB (5120KB)
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120', 
        ], [
            'name.required' => 'Tên sản phẩm là bắt buộc.',
            'category_id.required' => 'Vui lòng chọn danh mục.',
            'category_id.exists' => 'Danh mục không tồn tại.',
            'thumbnail.image' => 'File thumbnail không hợp lệ.',
            'thumbnail.mimes' => 'Chỉ chấp nhận ảnh: jpeg, png, jpg, gif, webp.',
            'thumbnail.max' => 'Thumbnail tối đa 5MB.',
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
            // 2. Chuẩn bị dữ liệu tạo mới
            $productData = [
                'name' => $request->name,
                'category_id' => $request->category_id,
                'description' => $request->description ?? '',
                'status' => $request->status,
                'thumbnail_url' => null, // Sẽ cập nhật sau khi upload
                'sold_count' => 0,
                'favorite_count' => 0,
                'review_count' => 0,
                'average_rating' => 0.00,
            ];

            // 3. Tạo record trong DB để lấy ID
            $product = Product::create($productData);

            // 4. Xử lý upload Thumbnail (nếu có)
            if ($request->hasFile('thumbnail')) {
                $file = $request->file('thumbnail');
                
                // --- THAY ĐỔI: Thêm số random vào tên file ---
                // Format: thumb_{ID}_{RANDOM}.ext
                $randomNum = mt_rand(100000, 999999);
                $filename = 'thumb_' . $product->id . '_' . $randomNum . '.' . $file->getClientOriginalExtension();
                
                $path = public_path('thumbnail');

                // Tạo thư mục nếu chưa có
                if (!File::exists($path)) {
                    File::makeDirectory($path, 0755, true);
                }

                $file->move($path, $filename);
                
                // Cập nhật lại đường dẫn ảnh vào DB
                $product->thumbnail_url = '/thumbnail/' . $filename;
                $product->save();
            } else {
                // Nếu không up ảnh, dùng ảnh placeholder mặc định (Tuỳ chọn)
                $product->thumbnail_url = 'https://placehold.co/150x150?text=No+Img';
                $product->save();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tạo sản phẩm thành công',
                'data' => $product,
                'id' => $product->id, // Trả về ID để frontend dùng tiếp (VD: upload gallery)
                'product_id' => $product->id 
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            // Log lỗi để debug
            Log::error("Error creating product: " . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tạo sản phẩm: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xem chi tiết sản phẩm
     */
    public function show(string $id)
    {
        try {
            $product = Product::with([
                'category', 
                // Load variants kèm thuộc tính
                'variants' => function($query) {
                    $query->with(['attributeValues' => function($q) {
                        $q->with('attribute');
                    }]);
                },
                'images', 
            ])->findOrFail($id);
            
            // Format lại cấu trúc Variants cho Frontend dễ dùng
            // Frontend mong đợi: variants: [{ attributes: { "Màu": "Đỏ", "Size": "L" }, ... }]
            $formattedVariants = $product->variants->map(function($variant) {
                $attributes = [];
                if ($variant->attributeValues && $variant->attributeValues->count() > 0) {
                    foreach($variant->attributeValues as $av) {
                        if ($av && $av->attribute && $av->attribute->name) {
                            $attributes[$av->attribute->name] = $av->value;
                        }
                    }
                }
                return [
                    'id' => $variant->id,
                    'product_id' => $variant->product_id,
                    'price' => (float) $variant->price,
                    'original_price' => (float) $variant->original_price,
                    'stock' => (int) $variant->stock,
                    'attributes' => $attributes, // Mảng key-value đơn giản
                    'created_at' => $variant->created_at,
                    'updated_at' => $variant->updated_at,
                ];
            });
            
            $productData = $product->toArray();
            $productData['variants'] = $formattedVariants;
            
            return response()->json([
                'success' => true,
                'data' => $productData
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy sản phẩm hoặc lỗi hệ thống.'
            ], 404);
        }
    }

    /**
     * Cập nhật sản phẩm
     */
    public function update(Request $request, string $id)
    {
        $product = Product::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'category_id' => 'sometimes|exists:categories,id',
            'status' => 'sometimes|in:active,inactive,draft',
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // Chỉ lấy các trường cần update để tránh ghi đè null vào dữ liệu cũ
            $dataToUpdate = $request->only(['name', 'category_id', 'description', 'status']);

            // Xử lý nếu có upload ảnh mới
            if ($request->hasFile('thumbnail')) {
                // 1. XÓA ẢNH CŨ VẬT LÝ NẾU CÓ
                if ($product->thumbnail_url && $product->thumbnail_url !== 'https://placehold.co/150x150?text=No+Img') {
                    $oldPath = public_path($product->thumbnail_url);
                    if (File::exists($oldPath)) {
                        File::delete($oldPath);
                    }
                }

                // 2. LƯU ẢNH MỚI
                $file = $request->file('thumbnail');
                
                // --- THAY ĐỔI: Dùng số random thay vì time() ---
                $randomNum = mt_rand(100000, 999999);
                $filename = 'thumb_' . $product->id . '_' . $randomNum . '.' . $file->getClientOriginalExtension();
                
                $path = public_path('thumbnail');

                if (!File::exists($path)) {
                    File::makeDirectory($path, 0755, true);
                }

                $file->move($path, $filename);
                $dataToUpdate['thumbnail_url'] = '/thumbnail/' . $filename;
            }

            $product->update($dataToUpdate);
            
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

    /**
     * Xóa sản phẩm (Xóa cả ảnh vật lý)
     */
    public function destroy(string $id)
    {
        try {
            $product = Product::findOrFail($id);
            
            // 1. XÓA THUMBNAIL VẬT LÝ
            if ($product->thumbnail_url && $product->thumbnail_url !== 'https://placehold.co/150x150?text=No+Img') {
                 $thumbPath = public_path($product->thumbnail_url);
                 if (File::exists($thumbPath)) {
                    File::delete($thumbPath);
                 }
            }

            // 2. XÓA CÁC BIẾN THỂ (DB Relation sẽ tự lo nếu có cascade, nhưng xóa tay cho chắc)
            $product->variants()->delete();
            
            // 3. XÓA GALLERY ẢNH (ImageProduct)
            $galleryImages = $product->images; // Lấy collection ảnh

            if ($galleryImages) {
                foreach ($galleryImages as $image) {
                    // Xóa file vật lý
                    if ($image->image_url) {
                        $imagePath = public_path($image->image_url);
                        if (File::exists($imagePath)) {
                            File::delete($imagePath);
                        }
                    }
                }
                // Xóa records trong DB
                $product->images()->delete();
            }
            
            // 4. XÓA SẢN PHẨM CHÍNH
            $product->delete();

            return response()->json([
                'success' => true,
                'message' => 'Đã xóa sản phẩm và toàn bộ dữ liệu liên quan'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xóa: ' . $e->getMessage()
            ], 500);
        }
    }
}