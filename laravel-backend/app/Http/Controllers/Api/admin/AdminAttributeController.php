<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attribute;

class AdminAttributeController extends Controller
{
    /**
     * Lấy danh sách tất cả thuộc tính (Màu sắc, Kích thước.....)
     */
    public function index()
    {
        try {
            // Lấy tất cả, sắp xếp mới nhất lên đầu
            $attributes = Attribute::orderBy('id', 'desc')->get();
            return response()->json($attributes);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi server: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Tạo nhanh thuộc tính mới
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255|unique:attributes,name'
        ], [
            'name.required' => 'Tên thuộc tính không được để trống',
            'name.unique' => 'Thuộc tính này đã tồn tại'
        ]);

        try {
            $attribute = Attribute::create([
                'name' => $request->name
            ]);

            return response()->json($attribute, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Không thể tạo thuộc tính: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Cập nhật tên thuộc tính (Bổ sung thêm)
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            // unique nhưng bỏ qua id hiện tại (để cho phép giữ nguyên tên cũ hoặc đổi tên khác không trùng với thằng khác)
            'name' => 'required|string|max:255|unique:attributes,name,' . $id
        ], [
            'name.required' => 'Tên thuộc tính không được để trống',
            'name.unique' => 'Thuộc tính này đã tồn tại'
        ]);

        try {
            $attribute = Attribute::findOrFail($id);
            $attribute->update([
                'name' => $request->name
            ]);

            return response()->json($attribute);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi cập nhật: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Xóa thuộc tính
     */
    public function destroy($id)
    {
        try {
            $attribute = Attribute::findOrFail($id);
            $attribute->delete();
            return response()->json(['message' => 'Đã xóa thuộc tính thành công']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi xóa: ' . $e->getMessage()], 500);
        }
    }
}