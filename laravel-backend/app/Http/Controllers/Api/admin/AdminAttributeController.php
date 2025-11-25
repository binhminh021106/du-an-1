<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Attribute;

class AdminAttributeController extends Controller
{
    /**
     * Lấy danh sách tất cả thuộc tính (Màu sắc, Kích thước...)
     */
    public function index()
    {
        try {
            // Lấy tất cả, sắp xếp theo tên hoặc id
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
     * Xóa thuộc tính (Tùy chọn, nếu cần quản lý kỹ hơn)
     */
    public function destroy($id)
    {
        try {
            $attribute = Attribute::findOrFail($id);
            $attribute->delete();
            return response()->json(['message' => 'Đã xóa thuộc tính']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi xóa: ' . $e->getMessage()], 500);
        }
    }
}