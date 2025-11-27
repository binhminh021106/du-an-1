<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Category;
use Illuminate\Validation\Rule;

class AdminCategoryController extends Controller
{
    // Lấy danh sách
    public function index()
    {
        // Sắp xếp theo thứ tự nhỏ -> lớn
        $categories = Category::orderBy('order_number', 'asc')->get();

        // Map dữ liệu để trả về format Vue cần
        $data = $categories->map(function ($cat) {
            return [
                'id' => $cat->id,
                'name' => $cat->name,
                'description' => $cat->description,
                'icon' => $cat->icon,
                'order' => $cat->order_number, 
                'status' => $cat->status,
                'created_at' => $cat->created_at,
            ];
        });

        return response()->json($data);
    }

    // Thêm mới
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100|unique:categories,name',
            'icon' => 'required|string',
            'order' => 'required|integer|min:1',
            'status' => 'required|in:active,disabled',
        ], [
            'name.required' => 'Tên danh mục không được để trống',
            'name.unique' => 'Tên danh mục đã tồn tại',
            'icon.required' => 'Vui lòng nhập icon',
        ]);

        $category = Category::create([
            'name' => $request->name,
            'description' => $request->description,
            'icon' => $request->icon,
            'order_number' => $request->order,
            'status' => $request->status,
        ]);

        return response()->json(['message' => 'Tạo thành công', 'data' => $category]);
    }

    // Cập nhật
    public function update(Request $request, string $id)
    {
        $category = Category::findOrFail($id);

        // 1. Xử lý update STATUS (Toggle Switch)
        // Điều kiện: Request có 'status' và tổng số trường gửi lên là 1
        if ($request->has('status') && count($request->all()) == 1) {
            // Validate dữ liệu status
            $request->validate(['status' => 'required|in:active,disabled']);
            
            $category->update(['status' => $request->status]);
            return response()->json(['message' => 'Cập nhật trạng thái thành công']);
        }

        // 2. Xử lý update ORDER (Sắp xếp)
        // Điều kiện: Request có 'order' và KHÔNG có 'name' (tránh nhầm với form edit full)
        if ($request->has('order') && !$request->has('name')) {
            // Validate dữ liệu order
            $request->validate(['order' => 'required|integer|min:0']);

            $category->update(['order_number' => $request->order]);
            return response()->json(['message' => 'Cập nhật thứ tự thành công']);
        }

        // 3. Xử lý update FULL (Form chỉnh sửa)
        $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('categories')->ignore($category->id)],
            'icon' => 'required|string',
            'order' => 'required|integer|min:1',
        ], [
            'name.unique' => 'Tên danh mục đã tồn tại',
        ]);

        $category->update([
            'name' => $request->name,
            'description' => $request->description,
            'icon' => $request->icon,
            'order_number' => $request->order,
            'status' => $request->status,
        ]);

        return response()->json(['message' => 'Cập nhật thành công', 'data' => $category]);
    }

    // Xóa
    public function destroy(string $id)
    {
        $category = Category::findOrFail($id);
        $category->delete(); 

        return response()->json(['message' => 'Đã xóa thành công']);
    }
}