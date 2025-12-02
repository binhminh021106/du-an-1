<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ImageProduct;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB; // [NEW] Import Transaction

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
            'product_id' => 'required|exists:product,id', // Lưu ý: check lại tên bảng 'products' hay 'product' trong DB
            'image' => [
                'required',
                'image',                   // Check Magic Number
                'mimes:jpeg,png,jpg,gif,webp', 
                'max:5120'                 // Max 5MB
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

        DB::beginTransaction(); // Bắt đầu giao dịch

        try {
            // 2. Tạo record rỗng trước để lấy ID
            $imageProduct = ImageProduct::create([
                'product_id' => $request->product_id,
                'image_url' => '', // Tạm thời để trống
            ]);

            // 3. Xử lý file sau khi có ID
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $extension = $file->getClientOriginalExtension();
                
                // --- THAY ĐỔI: Thêm số random vào tên file ---
                // Tên file theo chuẩn: product_img_{ID}_{RANDOM}.{ext}
                $randomNum = mt_rand(100000, 999999);
                $filename = 'product_img_' . $imageProduct->id . '_' . $randomNum . '.' . $extension;
                
                $path = public_path('product'); 

                if (!File::exists($path)) {
                    File::makeDirectory($path, 0755, true);
                }

                $file->move($path, $filename);
                
                // Cập nhật lại đường dẫn
                $imageProduct->image_url = '/product/' . $filename;
                $imageProduct->save();
            }

            DB::commit(); // Lưu thành công

            return response()->json([
                'success' => true,
                'message' => 'Upload ảnh thành công',
                'data' => $imageProduct
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack(); // Hoàn tác nếu lỗi
            
            // Nếu file lỡ upload rồi mà lỗi DB thì xóa file đi cho sạch (nếu cần thiết)
            // Logic ở trên move file sau khi create nên rollback DB là đủ, nhưng cẩn thận vẫn tốt.
            
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
                
                // 2. Xóa record
                $img->delete(); 
                $countDeleted++;
            }

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