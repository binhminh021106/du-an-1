<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log; // ✅ THÊM DÒNG NÀY

class AdminProductController extends Controller
{
    public function index()
    {
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
            'thumbnail' => 'nullable|image|max:5120',
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
            $thumbnailUrl = 'https://placehold.co/150x150?text=No+Img';
            
            if ($request->hasFile('thumbnail')) {
                $file = $request->file('thumbnail');
                $filename = time() . '_' . $file->getClientOriginalName();
                $path = public_path('thumbnail');

                if (!File::exists($path)) {
                    File::makeDirectory($path, 0755, true);
                }

                $file->move($path, $filename);
                $thumbnailUrl = '/thumbnail/' . $filename;
            }

            $productData = [
                'name' => $request->name,
                'category_id' => $request->category_id,
                'description' => $request->description ?? '',
                'status' => $request->status,
                'thumbnail_url' => $thumbnailUrl,
                'sold_count' => 0,
                'favorite_count' => 0,
                'review_count' => 0,
                'average_rating' => 0.00,
            ];

            $product = Product::create($productData);

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
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tạo sản phẩm: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id)
    {
        try {
            // Load đầy đủ quan hệ
            $product = Product::with([
                'category', 
                'variants' => function($query) {
                    $query->with(['attributeValues' => function($q) {
                        $q->with('attribute');
                    }]);
                },
                'images', 
            ])->findOrFail($id);
            
            // Format lại variants
            $formattedVariants = $product->variants->map(function($variant) {
                $attributes = [];
                
                // Kiểm tra tồn tại attributeValues
                if ($variant->attributeValues && $variant->attributeValues->count() > 0) {
                    foreach($variant->attributeValues as $av) {
                        // Kiểm tra null safety
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
                    'attributes' => $attributes,
                    'created_at' => $variant->created_at,
                    'updated_at' => $variant->updated_at,
                ];
            });
            
            // Gán lại variants đã format
            $productData = $product->toArray();
            $productData['variants'] = $formattedVariants;
            
            return response()->json([
                'success' => true,
                'data' => $productData
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error in AdminProductController@show: ' . $e->getMessage()); // ✅ Bỏ dấu \
            Log::error($e->getTraceAsString()); // ✅ Bỏ dấu \
            
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tải sản phẩm: ' . $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $product = Product::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'category_id' => 'sometimes|exists:categories,id',
            'thumbnail' => 'nullable|image|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $dataToUpdate = $request->only(['name', 'category_id', 'description', 'status']);

            if ($request->hasFile('thumbnail')) {
                if ($product->thumbnail_url && file_exists(public_path($product->thumbnail_url))) {
                    // File::delete(public_path($product->thumbnail_url));
                }

                $file = $request->file('thumbnail');
                $filename = time() . '_' . $file->getClientOriginalName();
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

    public function destroy(string $id)
    {
        try {
            $product = Product::findOrFail($id);
            
            if ($product->thumbnail_url && File::exists(public_path($product->thumbnail_url))) {
                File::delete(public_path($product->thumbnail_url));
            }

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