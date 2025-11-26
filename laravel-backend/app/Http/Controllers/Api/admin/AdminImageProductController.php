<?php
namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ImageProduct;
use Illuminate\Support\Facades\File; // Thêm thư viện File
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
        // Validate file thay vì string url
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:product,id',
            'image' => 'required|image|max:5120', // Bắt buộc là file ảnh
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $imageUrl = '';

            // Xử lý lưu ảnh vào public/product
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $filename = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
                
                $path = public_path('product'); // Đường dẫn: public/product

                // Tạo thư mục nếu chưa có
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
        // Logic update ảnh nếu cần (thường ít dùng, hay xóa đi up lại)
        return response()->json(['message' => 'Feature not implemented yet']);
    }

    public function destroy(string $id)
    {
        $imageProduct = ImageProduct::findOrFail($id);
        
        // Xóa file ảnh vật lý
        if ($imageProduct->image_url && File::exists(public_path($imageProduct->image_url))) {
            File::delete(public_path($imageProduct->image_url));
        }
        
        $imageProduct->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa ảnh thành công'
        ]);
    }
}