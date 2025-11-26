<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BrandSlide;
use Illuminate\Support\Facades\Storage;

class AdminBrandSlideController extends Controller
{
    /**
     * Lấy danh sách Brand
     */
    public function index()
    {
        $brands = BrandSlide::orderBy('order_number', 'asc')->get();

        $data = $brands->map(function ($brand) {
            return [
                'id'          => $brand->id,
                'name'        => $brand->name,
                'imageUrl'    => $brand->image_url ? asset($brand->image_url) : null,
                'linkUrl'     => $brand->link_url,
                'order'       => $brand->order_number,
                'status'      => $brand->status,
                'created_at'  => $brand->created_at,
            ];
        });

        return response()->json($data);
    }

    /**
     * Thêm mới Brand
     */
    public function store(Request $request)
    {
        $request->validate([
            'name'   => 'required|string|max:255',
            'image'  => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'order'  => 'nullable|integer|min:0',
            'status' => 'required|in:published,draft',
        ], [
            'name.required'  => 'Vui lòng nhập tên thương hiệu.',
            'image.required' => 'Vui lòng chọn logo/ảnh thương hiệu.',
        ]);

        // Upload ảnh vào thư mục 'brands'
        $imagePath = null;
        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('brands', 'public');
            $imagePath = '/storage/' . $path;
        }

        $brand = BrandSlide::create([
            'name'         => $request->name,
            'image_url'    => $imagePath,
            'link_url'     => $request->linkUrl,    // Vue gửi linkUrl
            'order_number' => $request->order ?? 0, // Vue gửi order
            'status'       => $request->status,
        ]);

        return response()->json([
            'message' => 'Thêm brand thành công',
            'data'    => $brand
        ], 201);
    }

    /**
     * Cập nhật Brand (Đã sửa lỗi Partial Update)
     */
    public function update(Request $request, string $id)
    {
        $brand = BrandSlide::findOrFail($id);

        // Validate linh hoạt (sometimes)
        $request->validate([
            'name'   => 'sometimes|required|string|max:255',
            'image'  => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'order'  => 'nullable|integer|min:0',
            'status' => 'sometimes|required|in:published,draft',
        ]);

        // Chỉ cập nhật các trường có gửi lên
        if ($request->has('name')) {
            $brand->name = $request->name;
        }

        if ($request->has('linkUrl')) {
            $brand->link_url = $request->linkUrl;
        }

        if ($request->has('order')) {
            $brand->order_number = $request->order;
        }

        if ($request->has('status')) {
            $brand->status = $request->status;
        }

        // Xử lý ảnh nếu có thay đổi
        if ($request->hasFile('image')) {
            // Xóa ảnh cũ dùng hàm helper
            $this->deleteImageFromStorage($brand->image_url);

            // Upload ảnh mới
            $path = $request->file('image')->store('brands', 'public');
            $brand->image_url = '/storage/' . $path;
        }

        $brand->save();

        return response()->json([
            'message' => 'Cập nhật thành công',
            'data'    => $brand
        ]);
    }

    /**
     * Xóa Brand
     */
    public function destroy(string $id)
    {
        $brand = BrandSlide::findOrFail($id);

        // Xóa file ảnh
        $this->deleteImageFromStorage($brand->image_url);

        $brand->delete();

        return response()->json(['message' => 'Đã xóa brand thành công']);
    }

    public function show(string $id)
    {
        return response()->json(BrandSlide::findOrFail($id));
    }

    /**
     * Hàm phụ: Xóa file ảnh khỏi đĩa cứng
     */
    private function deleteImageFromStorage($path)
    {
        if ($path) {
            $relativePath = str_replace('/storage/', '', $path);
            
            if (Storage::disk('public')->exists($relativePath)) {
                Storage::disk('public')->delete($relativePath);
            }
        }
    }
}