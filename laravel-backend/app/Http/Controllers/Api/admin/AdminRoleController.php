<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Role;
use Illuminate\Support\Facades\DB; // Import DB để dùng transaction

class AdminRoleController extends Controller
{
    public function index()
    {
        // Khi lấy danh sách role, đếm luôn số lượng quyền đã gán (permissions_count)
        $roles = Role::withCount('permissions')->get();
        return response()->json($roles);
    }

    public function store(Request $request)
    {
        $request->validate([
            'value' => 'required|unique:role,value|max:50',
            'label' => 'required|max:100',
        ]);

        try {
            $role = new Role();
            $role->value = $request->value;
            $role->label = $request->label;
            $role->badgeClass = $request->badgeClass ?? 'text-bg-secondary';
            $role->save();

            return response()->json($role, 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi tạo role: ' . $e->getMessage()], 500);
        }
    }

    public function show($id)
    {
        // Lấy role kèm theo danh sách quyền đã sở hữu
        $role = Role::with('permissions')->findOrFail($id);
        return response()->json($role);
    }

    public function update(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        $request->validate([
            'value' => 'sometimes|required|unique:role,value,' . $id . '|max:50',
            'label' => 'sometimes|required|max:100',
        ]);

        try {
            $role->update($request->all());
            return response()->json($role);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi cập nhật role: ' . $e->getMessage()], 500);
        }
    }

    /**
     * [NEW] Hàm Cấp Quyền cho Vai Trò
     * API: POST /api/admin/roles/{id}/permissions
     * Body: { "permissions": [1, 2, 5, ...] }  (Mảng các ID của quyền)
     */
    public function assignPermissions(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        // Validate dữ liệu gửi lên phải là mảng
        $request->validate([
            'permissions' => 'array',
            'permissions.*' => 'integer|exists:permissions,id' // Các ID phải tồn tại
        ]);

        try {
            // Sử dụng sync: Tự động thêm quyền mới và gỡ quyền cũ không có trong mảng
            // Ví dụ: Cũ [1,2], Mới [1,3] -> Xóa 2, Thêm 3 -> Kết quả [1,3]
            $role->permissions()->sync($request->permissions);

            return response()->json([
                'message' => 'Đã cập nhật quyền hạn cho vai trò ' . $role->label,
                'role' => $role->load('permissions') // Trả về role kèm quyền mới
            ]);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi cấp quyền: ' . $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $role = Role::findOrFail($id);
            
            // Chặn xóa các role hệ thống quan trọng
            if (in_array($role->value, ['admin', 'staff', 'user'])) {
                return response()->json(['message' => 'Không thể xóa vai trò mặc định của hệ thống.'], 403);
            }

            // Xóa role (Bảng trung gian role_permissions sẽ tự xóa nhờ Foreign Key Cascade ở DB)
            $role->delete();
            
            return response()->json(['message' => 'Đã xóa vai trò thành công']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi xóa role: ' . $e->getMessage()], 500);
        }
    }
}