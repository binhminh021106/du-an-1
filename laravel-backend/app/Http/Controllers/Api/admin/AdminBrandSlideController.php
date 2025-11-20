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
                // Trả về link ảnh đầy đủ (có domain)
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
     * Cập nhật Brand
     */
    public function update(Request $request, string $id)
    {
        $brand = BrandSlide::findOrFail($id);

        // --- ĐOẠN MỚI THÊM: Xử lý riêng cho nút Toggle Status ---
        // Nếu request có 'status' mà KHÔNG có 'name' -> Coi như là đổi trạng thái nhanh
        if ($request->has('status') && !$request->has('name')) {
            $request->validate([
                'status' => 'required|in:published,draft',
            ]);

            $brand->update(['status' => $request->status]);
            return response()->json(['message' => 'Cập nhật trạng thái thành công']);
        }
        // ---------------------------------------------------------

        // Validate đầy đủ (cho trường hợp sửa trong form)
        $request->validate([
            'name'   => 'required|string|max:255',
            'image'  => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'order'  => 'nullable|integer|min:0',
            'status' => 'required|in:published,draft',
        ]);

        $updateData = [
            'name'         => $request->name,
            'link_url'     => $request->linkUrl, 
            'order_number' => $request->order ?? 0,
            'status'       => $request->status,
        ];

        // Xử lý ảnh nếu có thay đổi
        if ($request->hasFile('image')) {
            // Xóa ảnh cũ
            if ($brand->image_url) {
                $oldPath = str_replace('/storage/', '', $brand->image_url);
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }

            // Upload ảnh mới
            $path = $request->file('image')->store('brands', 'public');
            $updateData['image_url'] = '/storage/' . $path;
        }

        $brand->update($updateData);

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
        if ($brand->image_url) {
            $oldPath = str_replace('/storage/', '', $brand->image_url);
            if (Storage::disk('public')->exists($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }
        }

        $brand->delete();

        return response()->json(['message' => 'Đã xóa brand thành công']);
    }

    public function show(string $id)
    {
        return response()->json(BrandSlide::findOrFail($id));
    }
}
