<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use Illuminate\Validation\Rule;

class AdminCategoryController extends Controller
{
    /**
     * Lấy danh sách danh mục
     * Sắp xếp mặc định theo thứ tự (order_number)
     */
    public function index()
    {
        // Sắp xếp tăng dần theo order_number để hiển thị đúng vị trí trên menu
        $categories = Category::orderBy('order_number', 'asc')->get();
        return response()->json($categories);
    }

    /**
     * Tạo danh mục mới
     */
    public function store(Request $request)
    {
        // 1. Validate dữ liệu đầu vào
        $validated = $request->validate([
            'name'          => 'required|string|max:100|unique:categories,name',
            'description'   => 'nullable|string',
            'icon'          => 'required|string|max:100', // Bắt buộc nhập icon
            'order_number'  => 'required|integer|min:0',   // Vue gửi 'order_number', DB lưu 'order_number'
            'status'        => 'required|in:active,disabled',
        ], [
            'name.required' => 'Tên danh mục là bắt buộc.',
            'name.unique'   => 'Tên danh mục đã tồn tại.',
            'icon.required' => 'Vui lòng nhập mã Icon (HTML).',
            'order_number.required' => 'Số thứ tự là bắt buộc.',
            'order_number.integer'  => 'Số thứ tự phải là số nguyên.',
        ]);

        // 2. Tạo mới
        try {
            $category = Category::create($validated);
            return response()->json($category, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi server: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Xem chi tiết danh mục
     */
    public function show(string $id)
    {
        $category = Category::findOrFail($id);
        return response()->json($category);
    }

    /**
     * Cập nhật danh mục
     */
    public function update(Request $request, string $id)
    {
        $category = Category::findOrFail($id);

        // 1. Validate
        $validated = $request->validate([
            // Kiểm tra trùng tên, nhưng bỏ qua (ignore) ID hiện tại
            'name'          => ['sometimes', 'required', 'string', 'max:100', Rule::unique('categories')->ignore($category->id)],
            'description'   => 'nullable|string',
            'icon'          => 'sometimes|required|string|max:100',
            'order_number'  => 'sometimes|required|integer|min:0',
            'status'        => 'sometimes|required|in:active,disabled',
        ], [
            'name.unique' => 'Tên danh mục đã được sử dụng.',
        ]);

        // 2. Update
        try {
            $category->update($validated);
            return response()->json($category);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi cập nhật: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Xóa danh mục
     */
    public function destroy(string $id)
    {
        $category = Category::findOrFail($id);
        
        // (Optional) Kiểm tra xem danh mục có đang chứa sản phẩm không
        if ($category->product()->exists()) {
             return response()->json(['message' => 'Không thể xóa danh mục đang chứa sản phẩm.'], 422);
        }

        $category->delete();

        return response()->json(['message' => 'Xóa danh mục thành công']);
    }
}