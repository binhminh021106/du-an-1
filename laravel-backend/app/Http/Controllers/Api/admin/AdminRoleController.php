<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Role;

class AdminRoleController extends Controller
{
    public function index()
    {
        $roles = Role::all();
        return response()->json($roles);
    }

    public function store(Request $request)
    {
        // Validate
        $request->validate([
            'value' => 'required|unique:role,value|max:50', // Mã role không trùng
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
        $role = Role::findOrFail($id);
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

    public function destroy($id)
    {
        try {
            $role = Role::findOrFail($id);
            // Kiểm tra xem có admin nào đang dùng role này không?
            // Nếu dùng Relationship hasMany trong Model Role thì check: if ($role->admins()->exists()) ...

            $role->delete();
            return response()->json(['message' => 'Đã xóa vai trò']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi xóa role: ' . $e->getMessage()], 500);
        }
    }
}
