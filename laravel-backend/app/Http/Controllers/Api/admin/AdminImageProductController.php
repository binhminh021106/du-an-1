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
        // 1. Validate chặt chẽ
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:product,id', // Lưu ý: check lại tên bảng là 'product' hay 'products'
            'image' => [
                'required',
                'image',             // Check Magic Number (chặn file giả mạo đuôi)
                'mimes:jpeg,png,jpg,gif,webp', // Chỉ chấp nhận các đuôi này
                'max:5120'           // Tối đa 5MB (5120 KB)
            ],
        ], [
            'image.image' => 'File tải lên không phải là hình ảnh hợp lệ.',
            'image.mimes' => 'Chỉ chấp nhận định dạng: jpeg, png, jpg, gif, webp.',
            'image.max' => 'Dung lượng ảnh không được vượt quá 5MB.',
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
                
                // 2. Tạo tên file an toàn (Không dùng tên gốc để tránh lỗi ký tự đặc biệt hoặc hack path)
                $extension = $file->getClientOriginalExtension();
                $filename = 'prod_' . time() . '_' . uniqid() . '.' . $extension;
                
                $path = public_path('product'); 

                if (!File::exists($path)) {
                    File::makeDirectory($path, 0755, true);
                }

                $file->move($path, $filename);
                
                // Lưu đường dẫn tương đối để serve web
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

    // Xóa 1 ảnh
    public function destroy(string $id)
    {
        try {
            $imageProduct = ImageProduct::findOrFail($id);
            
            // 1. Xóa file vật lý trước
            $this->deletePhysicalFile($imageProduct->image_url);
            
            // 2. Xóa trong database
            $imageProduct->delete();

            return response()->json([
                'success' => true,
                'message' => 'Đã xóa ảnh và file rác thành công'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xóa: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * API MỚI: Xóa nhiều ảnh cùng lúc (Bulk Delete)
     */
    public function bulkDestroy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'ids' => 'required|array',
            'ids.*' => 'integer|exists:image_product,id' // Check lại tên bảng image_product trong DB nhé
        ]);

        if ($validator->fails()) {
             return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $ids = $request->input('ids');

        try {
            // Lấy danh sách ảnh cần xóa
            $images = ImageProduct::whereIn('id', $ids)->get();

            $countDeleted = 0;
            foreach ($images as $img) {
                // 1. Xóa file vật lý
                $this->deletePhysicalFile($img->image_url);
                
                // 2. Xóa record (xóa từng cái hoặc xóa bulk ở dưới)
                $img->delete(); 
                $countDeleted++;
            }

            // Nếu muốn xóa record 1 lần bằng query (nhanh hơn nếu ko dùng Observer)
            // ImageProduct::whereIn('id', $ids)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Đã xóa ' . $countDeleted . ' ảnh và dọn dẹp file hệ thống.'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi xóa ảnh: ' . $e->getMessage()
            ], 500);
        }
    }

    // Helper function để xóa file tránh lặp code
    private function deletePhysicalFile($relativePath) {
        if ($relativePath) {
            // Đảm bảo đường dẫn đúng format public_path
            $absolutePath = public_path($relativePath);
            
            // Kiểm tra file tồn tại mới xóa
            if (File::exists($absolutePath)) {
                File::delete($absolutePath);
            }
        }
    }
}