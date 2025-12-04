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
        // 1. Thêm 'brand' vào with() để lấy thông tin thương hiệu ra danh sách
        $products = Product::with(['category', 'brand', 'variants', 'images'])
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
        // 2. Thêm validate cho brand_id
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive,draft',
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120', 
        ], [
            'name.required' => 'Tên sản phẩm là bắt buộc.',
            'category_id.required' => 'Vui lòng chọn danh mục.',
            'category_id.exists' => 'Danh mục không tồn tại.',
            'brand_id.exists' => 'Thương hiệu không tồn tại.',
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
            // 3. Thêm brand_id vào mảng dữ liệu tạo mới
            $productData = [
                'name' => $request->name,
                'category_id' => $request->category_id,
                'brand_id' => $request->brand_id,
                'description' => $request->description ?? '',
                'status' => $request->status,
                'thumbnail_url' => null,
                'sold_count' => 0,
                'favorite_count' => 0,
                'review_count' => 0,
                'average_rating' => 0.00,
            ];

            $product = Product::create($productData);

            // Xử lý upload Thumbnail (Giữ nguyên code cũ của bạn)
            if ($request->hasFile('thumbnail')) {
                $file = $request->file('thumbnail');
                $randomNum = mt_rand(100000, 999999);
                $filename = 'thumb_' . $product->id . '_' . $randomNum . '.' . $file->getClientOriginalExtension();
                $path = public_path('thumbnail');

                if (!File::exists($path)) {
                    File::makeDirectory($path, 0755, true);
                }

                $file->move($path, $filename);
                $product->thumbnail_url = '/thumbnail/' . $filename;
                $product->save();
            } else {
                $product->thumbnail_url = 'https://placehold.co/150x150?text=No+Img';
                $product->save();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Tạo sản phẩm thành công',
                'data' => $product,
                'id' => $product->id, 
                'product_id' => $product->id 
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
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
            // 4. Thêm 'brand' vào with() để hiển thị chi tiết
            $product = Product::with([
                'category',
                'brand',
                'variants' => function($query) {
                    $query->with(['attributeValues' => function($q) {
                        $q->with('attribute');
                    }]);
                },
                'images', 
            ])->findOrFail($id);
            
            // Format lại variants (Giữ nguyên code cũ của bạn)
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
                    'image' => $variant->image,
                    'attributes' => $attributes, 
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

        // 5. Thêm validate brand_id khi update
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'category_id' => 'sometimes|exists:categories,id',
            'brand_id' => 'nullable|exists:brands,id',
            'status' => 'sometimes|in:active,inactive,draft',
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // 6. Thêm 'brand_id' vào danh sách các trường được phép update
            // QUAN TRỌNG: Nếu thiếu ở đây thì dù gửi lên nó cũng không lưu
            $dataToUpdate = $request->only(['name', 'category_id', 'brand_id', 'description', 'status']); 

            if ($request->hasFile('thumbnail')) {
                if ($product->thumbnail_url && $product->thumbnail_url !== 'https://placehold.co/150x150?text=No+Img') {
                    $oldPath = public_path($product->thumbnail_url);
                    if (File::exists($oldPath)) {
                        File::delete($oldPath);
                    }
                }

                $file = $request->file('thumbnail');
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
     * Xóa sản phẩm (Giữ nguyên)
     */
    public function destroy(string $id)
    {
        try {
            $product = Product::findOrFail($id);
            
            if ($product->thumbnail_url && $product->thumbnail_url !== 'https://placehold.co/150x150?text=No+Img') {
                 $thumbPath = public_path($product->thumbnail_url);
                 if (File::exists($thumbPath)) {
                    File::delete($thumbPath);
                 }
            }

            $product->variants()->delete();
            
            $galleryImages = $product->images;

            if ($galleryImages) {
                foreach ($galleryImages as $image) {
                    if ($image->image_url) {
                        $imagePath = public_path($image->image_url);
                        if (File::exists($imagePath)) {
                            File::delete($imagePath);
                        }
                    }
                }
                $product->images()->delete();
            }
            
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