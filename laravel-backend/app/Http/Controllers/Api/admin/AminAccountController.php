<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin; 
use Illuminate\Support\Facades\Hash;

class AminAccountController extends Controller
{
    /**
     * Display a listing of the resource.
     * Lấy danh sách tất cả tài khoản nội bộ
     */
    public function index()
    {
        $admins = Admin::orderBy('created_at', 'desc')->get();
        return response()->json($admins);
    }

    /**
     * Store a newly created resource in storage.
     * Tạo tài khoản nội bộ mới
     */
    public function store(Request $request)
    {
        // 1. Validate dữ liệu đầu vào
        $request->validate([
            // Đã xóa 'username' vì frontend không gửi lên nữa
            'fullname' => 'required|string|max:255', // Thêm validate cho fullname
            'email' => 'required|email|max:100|unique:admins,email',
            'password' => 'required|string|min:6', 
            'role_id' => 'required', 
            'phone' => 'nullable|string|max:20', // Thêm validate cơ bản cho phone
        ]);

        try {
            // 2. Tạo đối tượng Admin mới
            $admin = new Admin();
            // $admin->username = $request->username; // XÓA DÒNG NÀY để tránh lỗi
            
            // Sử dụng fullname từ request
            $admin->fullname = $request->fullname; 
            
            $admin->email = $request->email;
            $admin->password = Hash::make($request->password);
            $admin->phone = $request->phone ?? null;
            $admin->address = $request->address ?? null;
            
            // QUAN TRỌNG: Lưu vào cột role_id
            $admin->role_id = $request->role_id;
            
            $admin->status = $request->status ?? 'active';
            $admin->avatar_url = $request->avatar_url ?? '';
            
            $admin->save();

            return response()->json($admin, 201);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi tạo tài khoản: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     * Xem chi tiết 1 tài khoản
     */
    public function show(string $id)
    {
        $admin = Admin::findOrFail($id);
        return response()->json($admin);
    }

    /**
     * Update the specified resource in storage.
     * Cập nhật thông tin
     */
    public function update(Request $request, string $id)
    {
        $admin = Admin::findOrFail($id);

        // 1. Validate
        $request->validate([
            // Xóa validate username
            'fullname' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|max:100|unique:admins,email,' . $id,
            'password' => 'sometimes|nullable|min:6',
        ]);

        try {
            // 2. Cập nhật các trường thông thường
            // Xóa logic update username
            if ($request->has('fullname')) $admin->fullname = $request->fullname;
            if ($request->has('email')) $admin->email = $request->email;
            if ($request->has('phone')) $admin->phone = $request->phone;
            if ($request->has('address')) $admin->address = $request->address;
            
            if ($request->has('role_id')) $admin->role_id = $request->role_id;
            
            if ($request->has('status')) $admin->status = $request->status;
            if ($request->has('avatar_url')) $admin->avatar_url = $request->avatar_url;

            // 3. Xử lý mật khẩu
            if ($request->filled('password')) {
                $admin->password = Hash::make($request->password);
            }

            $admin->save();

            return response()->json($admin);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi cập nhật: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     * Xóa tài khoản
     */
    public function destroy(string $id)
    {
        try {
            $admin = Admin::findOrFail($id);
            $admin->delete();
            return response()->json(['message' => 'Đã xóa tài khoản thành công.']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi xóa: ' . $e->getMessage()
            ], 500);
        }
    }
}