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
use Illuminate\Support\Facades\Log;

class AdminProductController extends Controller
{
    public function index()
    {
        $products = Product::with(['category', 'images', 'variants.attributeValues'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($products);
    }

    public function store(Request $request)
    {
        // 1. XỬ LÝ JSON STRING TỪ FORM-DATA
        if ($request->has('variants') && !is_array($request->variants)) {
            $decodedVariants = json_decode($request->variants, true);
            if (is_array($decodedVariants)) {
                $request->merge(['variants' => $decodedVariants]);
            }
        }

        // 2. FIX DỮ LIỆU THIẾU TỪ FE (Tạm thời)
        // Nếu FE không gửi original_price, ta tự động gán nó bằng price
        $fixedVariants = [];
        if ($request->has('variants') && is_array($request->variants)) {
            foreach ($request->variants as $v) {
                if (!isset($v['original_price']) && isset($v['price'])) {
                    $v['original_price'] = $v['price'];
                }
                $fixedVariants[] = $v;
            }
            $request->merge(['variants' => $fixedVariants]);
        }

        // 3. Validate
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'category_id' => 'required|exists:categories,id',
            'description' => 'required|string',
            'status' => 'required|in:active,inactive',
            'thumbnail' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'images' => 'nullable|array',
            
            // Validate Variants
            'variants' => 'nullable|array',
            'variants.*.price' => 'required_with:variants|numeric|min:0',
            // Bây giờ original_price đã được fill ở bước 2 nên chắc chắn qua
            'variants.*.original_price' => 'numeric|min:0', 
            'variants.*.stock' => 'required_with:variants|integer|min:0',
            
            // CẢNH BÁO: FE đang gửi "attributes" dạng object text, 
            // trong khi code cần "attribute_value_ids" dạng array ID.
            // Validate này sẽ bỏ qua nếu không có key attribute_value_ids
            'variants.*.attribute_value_ids' => 'nullable|array',
            'variants.*.attribute_value_ids.*' => 'exists:attribute_values,id', 
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // A. Tạo Product
            $dataProduct = $request->only(['name', 'category_id', 'description', 'status']);

            if ($request->hasFile('thumbnail')) {
                $path = $request->file('thumbnail')->store('products/thumbnails', 'public');
                $dataProduct['thumbnail_url'] = '/storage/' . $path;
            }

            $product = Product::create($dataProduct);

            // B. Tạo Album
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $imageFile) {
                    $path = $imageFile->store('products/gallery', 'public');
                    ImageProduct::create([
                        'product_id' => $product->id,
                        'image_url' => '/storage/' . $path,
                    ]);
                }
            }

            // C. Tạo Variants
            if ($request->has('variants') && !empty($request->variants)) {
                foreach ($request->variants as $variantData) {
                    $newVariant = Variant::create([
                        'product_id' => $product->id,
                        'price' => $variantData['price'],
                        // Dùng toán tử ?? để đảm bảo không lỗi nếu bước fix trên có vấn đề
                        'original_price' => $variantData['original_price'] ?? $variantData['price'],
                        'stock' => $variantData['stock'],
                    ]);

                    // LOGIC GẮN THUỘC TÍNH
                    // Kiểm tra xem FE gửi đúng attribute_value_ids (mảng ID) chưa
                    if (isset($variantData['attribute_value_ids']) && is_array($variantData['attribute_value_ids'])) {
                        $newVariant->attributeValues()->sync($variantData['attribute_value_ids']);
                    } else {
                        // Log cảnh báo nếu FE gửi sai định dạng (ví dụ gửi "attributes": {...})
                        Log::warning("Variant ID {$newVariant->id} được tạo nhưng không có thuộc tính vì FE gửi sai định dạng key.");
                    }
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Tạo sản phẩm thành công',
                'data' => $product->load(['images', 'variants.attributeValues'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lỗi tạo sản phẩm: ' . $e->getMessage());
            return response()->json(['message' => 'Lỗi server', 'error' => $e->getMessage()], 500);
        }
    }

    public function show(string $id)
    {
        $product = Product::with(['category', 'images', 'variants.attributeValues', 'reviews'])->find($id);
        if (!$product) return response()->json(['message' => 'Không tìm thấy'], 404);
        return response()->json($product);
    }

    public function update(Request $request, string $id)
    {
        $product = Product::find($id);
        if (!$product) return response()->json(['message' => 'Không tìm thấy'], 404);

        // 1. Decode JSON Variants nếu cần
        if ($request->has('variants') && !is_array($request->variants)) {
            $decoded = json_decode($request->variants, true);
            if (is_array($decoded)) $request->merge(['variants' => $decoded]);
        }
        
        // 2. Fix original_price cho update
        $fixedVariants = [];
        if ($request->has('variants') && is_array($request->variants)) {
            foreach ($request->variants as $v) {
                if (!isset($v['original_price']) && isset($v['price'])) {
                    $v['original_price'] = $v['price'];
                }
                $fixedVariants[] = $v;
            }
            $request->merge(['variants' => $fixedVariants]);
        }

        // Validate
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'category_id' => 'sometimes|required|exists:categories,id',
            'variants' => 'nullable|array',
            'variants.*.price' => 'required_with:variants|numeric|min:0',
        ]);
        
        if ($validator->fails()) return response()->json(['errors' => $validator->errors()], 422);

        DB::beginTransaction();
        try {
            $product->update($request->only(['name', 'category_id', 'status', 'description']));

            if ($request->hasFile('thumbnail')) {
                // Logic xóa ảnh cũ nếu cần...
                $path = $request->file('thumbnail')->store('products/thumbnails', 'public');
                $product->update(['thumbnail_url' => '/storage/' . $path]);
            }

            // Update Variants
            if ($request->has('variants')) {
                foreach ($request->variants as $variantData) {
                    if (isset($variantData['id'])) {
                        // Update cũ (Kiểm tra xem ID này có thuộc product này không để bảo mật)
                         $variant = Variant::where('id', $variantData['id'])->where('product_id', $product->id)->first();
                        if ($variant) {
                            $variant->update([
                                'price' => $variantData['price'],
                                'stock' => $variantData['stock'],
                                'original_price' => $variantData['original_price'] ?? $variantData['price']
                            ]);
                            if (isset($variantData['attribute_value_ids'])) {
                                $variant->attributeValues()->sync($variantData['attribute_value_ids']);
                            }
                        }
                    } else {
                        // Tạo mới
                        $newVariant = Variant::create([
                            'product_id' => $product->id,
                            'price' => $variantData['price'],
                            'original_price' => $variantData['original_price'] ?? $variantData['price'],
                            'stock' => $variantData['stock'],
                        ]);
                        if (isset($variantData['attribute_value_ids'])) {
                            $newVariant->attributeValues()->sync($variantData['attribute_value_ids']);
                        }
                    }
                }
            }

             if ($request->hasFile('images')) {
                foreach ($request->file('images') as $imageFile) {
                    $path = $imageFile->store('products/gallery', 'public');
                    ImageProduct::create([
                        'product_id' => $product->id,
                        'image_url' => '/storage/' . $path,
                    ]);
                }
            }

            DB::commit();
            return response()->json(['message' => 'Cập nhật thành công', 'data' => $product->load('variants.attributeValues')]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lỗi update: ' . $e->getMessage());
            return response()->json(['message' => 'Lỗi cập nhật', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(string $id)
    {
        $product = Product::find($id);
        if (!$product) return response()->json(['message' => 'Không tìm thấy'], 404);
        try {
            $product->delete();
            return response()->json(['message' => 'Đã xóa sản phẩm']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi', 'error' => $e->getMessage()], 500);
        }
    }
}