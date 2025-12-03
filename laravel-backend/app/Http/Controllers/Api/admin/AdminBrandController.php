<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Brand;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminBrandController extends Controller
{
    /**
     * Lấy danh sách Brand
     */
    public function index(Request $request)
    {
        $brands = Brand::orderBy('order_number', 'asc')->orderBy('id', 'desc')->get();
        return response()->json($brands);
    }

    /**
     * Tạo mới Brand
     */
    public function store(Request $request)
    {
        // 1. Validate
        $request->validate([
            'name' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'status' => 'required|in:active,inactive',
            'order_number' => 'nullable|integer',
        ], [
            'name.required' => 'Tên thương hiệu không được để trống.',
            'status.in' => 'Trạng thái phải là active hoặc inactive.',
        ]);

        $data = $request->except('logo');

        // 2. Tạo Slug tự động
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        // 3. Lưu ảnh
        if ($request->hasFile('logo')) {
            $path = $request->file('logo')->store('brands', 'public');
            $data['logo_url'] = $path;
        }

        // 4. LƯU VÀO DATABASE
        $brand = Brand::create($data);

        return response()->json($brand, 201);
    }

    /**
     * Cập nhật Brand (ĐÃ SỬA LỖI: Thêm lệnh update)
     */
    public function update(Request $request, string $id)
    {
        $brand = Brand::findOrFail($id);

        // 1. Validate
        $request->validate([
            'name' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'status' => 'required|in:active,inactive',
            'order_number' => 'nullable|integer',
        ]);

        $data = $request->except('logo');

        // 2. Cập nhật Slug nếu cần
        if (empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }

        // 3. Xử lý ảnh mới
        if ($request->hasFile('logo')) {
            // Xóa ảnh cũ nếu có
            if ($brand->logo_url && Storage::disk('public')->exists($brand->logo_url)) {
                Storage::disk('public')->delete($brand->logo_url);
            }
            // Lưu ảnh mới
            $path = $request->file('logo')->store('brands', 'public');
            $data['logo_url'] = $path;
        }

        // 4. LƯU VÀO DATABASE (Đây là dòng bạn bị thiếu trước đó)
        $brand->update($data);

        return response()->json($brand);
    }

    /**
     * Xem chi tiết 1 Brand
     */
    public function show(string $id)
    {
        return response()->json(Brand::findOrFail($id));
    }

    /**
     * Xóa mềm
     */
    public function destroy(string $id)
    {
        $brand = Brand::findOrFail($id);
        $brand->delete();
        return response()->json(['message' => 'Đã chuyển vào thùng rác thành công']);
    }

    /**
     * Danh sách thùng rác
     */
    public function trashed(Request $request)
    {
        $brands = Brand::onlyTrashed()->orderBy('deleted_at', 'desc')->get();
        return response()->json($brands);
    }

    /**
     * Khôi phục
     */
    public function restore(string $id)
    {
        $brand = Brand::onlyTrashed()->findOrFail($id);
        $brand->restore();
        return response()->json(['message' => 'Khôi phục thành công', 'data' => $brand]);
    }

    /**
     * Xóa vĩnh viễn
     */
    public function forceDelete(string $id)
    {
        $brand = Brand::onlyTrashed()->findOrFail($id);
        if ($brand->logo_url && Storage::disk('public')->exists($brand->logo_url)) {
            Storage::disk('public')->delete($brand->logo_url);
        }
        $brand->forceDelete();
        return response()->json(['message' => 'Đã xóa vĩnh viễn']);
    }
}