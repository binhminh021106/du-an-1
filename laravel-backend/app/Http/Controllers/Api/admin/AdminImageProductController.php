<?php
namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ImageProduct;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;

class AdminImageProductController extends Controller
{
    public function index()
    {
        $imageProducts = ImageProduct::all();
        return response()->json($imageProducts);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:product,id',
            'image' => 'required|image|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $imageUrl = '';

            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
                
                $path = public_path('product'); 

                if (!File::exists($path)) {
                    File::makeDirectory($path, 0755, true);
                }

                $file->move($path, $filename);
                $imageUrl = '/product/' . $filename;
            }

            $imageProduct = ImageProduct::create([
                'product_id' => $request->product_id,
                'image_url' => $imageUrl
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Upload ảnh thành công',
                'data' => $imageProduct
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi upload ảnh: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id)
    {
        $imageProduct = ImageProduct::findOrFail($id);
        return response()->json($imageProduct);
    }

    public function update(Request $request, string $id)
    {
        return response()->json(['message' => 'Feature not implemented yet']);
    }

    // Xóa 1 ảnh (Giữ lại để tương thích ngược nếu cần)
    public function destroy(string $id)
    {
        $imageProduct = ImageProduct::findOrFail($id);
        
        if ($imageProduct->image_url && File::exists(public_path($imageProduct->image_url))) {
            File::delete(public_path($imageProduct->image_url));
        }
        
        $imageProduct->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa ảnh thành công'
        ]);
    }

    /**
     * API MỚI: Xóa nhiều ảnh cùng lúc
     * Endpoint gợi ý: POST /api/admin/imageProducts/bulk-delete
     * Body: { ids: [1, 2, 3] }
     */
    public function bulkDestroy(Request $request)
    {
        $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:image_product,id'
        ]);

        $ids = $request->input('ids');

        try {
            // Lấy danh sách ảnh để xóa file vật lý trước
            $images = ImageProduct::whereIn('id', $ids)->get();

            foreach ($images as $img) {
                if ($img->image_url && File::exists(public_path($img->image_url))) {
                    File::delete(public_path($img->image_url));
                }
            }

            // Xóa record trong DB
            ImageProduct::whereIn('id', $ids)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Đã xóa ' . count($ids) . ' ảnh thành công'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi xóa ảnh: ' . $e->getMessage()
            ], 500);
        }
    }
}