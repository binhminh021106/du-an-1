<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BrandSlide;
use Illuminate\Support\Facades\File; // Sử dụng File facade cho nhất quán

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
                // Kiểm tra nếu là URL đầy đủ (http) thì giữ nguyên, nếu không thì dùng asset
                'imageUrl'    => $brand->image_url ? (filter_var($brand->image_url, FILTER_VALIDATE_URL) ? $brand->image_url : asset($brand->image_url)) : null,
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

        try {
            // 1. Tạo Brand trước để lấy ID (Chưa lưu ảnh thật)
            $brand = BrandSlide::create([
                'name'         => $request->name,
                'image_url'    => '', // Tạm thời để trống
                'link_url'     => $request->linkUrl,
                'order_number' => $request->order ?? 0,
                'status'       => $request->status,
            ]);

            // 2. Xử lý upload ảnh sau khi có ID -> Đổi tên thành brand_{ID}_{RANDOM}
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $extension = $file->getClientOriginalExtension();
                
                // --- THAY ĐỔI: Thêm số random vào tên file ---
                // Sử dụng mt_rand() để tạo số ngẫu nhiên 6 chữ số
                $randomNum = mt_rand(100000, 999999);
                $fileName = 'brand_' . $brand->id . '_' . $randomNum . '.' . $extension;
                
                // Đường dẫn lưu: public/uploads/brands
                $path = public_path('uploads/brands');

                if (!File::exists($path)) {
                    File::makeDirectory($path, 0755, true);
                }

                $file->move($path, $fileName);
                
                // Cập nhật lại đường dẫn
                $brand->image_url = '/uploads/brands/' . $fileName;
                $brand->save();
            }

            return response()->json([
                'message' => 'Thêm brand thành công',
                'data'    => $brand
            ], 201);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Cập nhật Brand
     */
    public function update(Request $request, string $id)
    {
        $brand = BrandSlide::findOrFail($id);

        $request->validate([
            'name'   => 'sometimes|required|string|max:255',
            'image'  => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'order'  => 'nullable|integer|min:0',
            'status' => 'sometimes|required|in:published,draft',
        ]);

        try {
            if ($request->has('name')) $brand->name = $request->name;
            if ($request->has('linkUrl')) $brand->link_url = $request->linkUrl;
            if ($request->has('order')) $brand->order_number = $request->order;
            if ($request->has('status')) $brand->status = $request->status;

            // Xử lý ảnh nếu có thay đổi
            if ($request->hasFile('image')) {
                // 1. Xóa ảnh cũ
                if ($brand->image_url) {
                    $oldPath = public_path($brand->image_url);
                    if (File::exists($oldPath)) {
                        File::delete($oldPath);
                    }
                }

                // 2. Lưu ảnh mới với tên chuẩn brand_{ID}_{RANDOM}
                $file = $request->file('image');
                $extension = $file->getClientOriginalExtension();
                
                // --- THAY ĐỔI: Thêm số random vào tên file ---
                $randomNum = mt_rand(100000, 999999);
                $fileName = 'brand_' . $brand->id . '_' . $randomNum . '.' . $extension;
                
                $path = public_path('uploads/brands');

                if (!File::exists($path)) {
                    File::makeDirectory($path, 0755, true);
                }

                $file->move($path, $fileName);
                $brand->image_url = '/uploads/brands/' . $fileName;
            }

            $brand->save();

            return response()->json([
                'message' => 'Cập nhật thành công',
                'data'    => $brand
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Xóa Brand
     */
    public function destroy(string $id)
    {
        try {
            $brand = BrandSlide::findOrFail($id);

            // Xóa file ảnh vật lý
            if ($brand->image_url) {
                $path = public_path($brand->image_url);
                if (File::exists($path)) {
                    File::delete($path);
                }
            }

            $brand->delete();

            return response()->json(['message' => 'Đã xóa brand thành công']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi: ' . $e->getMessage()], 500);
        }
    }

    public function show(string $id)
    {
        return response()->json(BrandSlide::findOrFail($id));
    }
}