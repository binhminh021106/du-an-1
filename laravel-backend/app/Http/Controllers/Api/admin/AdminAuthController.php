<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AdminAuthController extends Controller
{
    public function register(Request $request)
    {
        // 1. Validate dữ liệu
        $validator = Validator::make($request->all(), [
            'fullName' => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:admins',
            'phone'    => 'required|string|max:15|unique:admins',
            'password' => 'required|string|min:8',
        ], [
            'email.unique' => 'Email này đã tồn tại trong hệ thống quản trị.',
            'phone.unique' => 'Số điện thoại này đã tồn tại.',
            'fullName.required' => 'Vui lòng nhập họ tên.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // 3. Tạo Admin
        $admin = Admin::create([
            'fullname' => $request->fullName,
            'email'    => $request->email,
            'phone'    => $request->phone,
            'password' => Hash::make($request->password),
            'status'   => 'active',
            'role_id'  => 12,
        ]);

        return response()->json([
            'message' => 'Đăng ký tài khoản Admin thành công!',
            'user'    => $admin
        ], 201);
    }


    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login_id' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // 2. Tìm Admin trong database (theo email hoặc phone)
        $loginInput = $request->login_id;

        $admin = Admin::where('email', $loginInput)
            ->orWhere('phone', $loginInput)
            ->first();
        if (!$admin || !Hash::check($request->password, $admin->password)) {
            return response()->json([
                'message' => 'Tài khoản hoặc mật khẩu quản trị không chính xác.'
            ], 401);
        }

        if ($admin->status !== 'active') {
            return response()->json(['message' => 'Tài khoản quản trị của bạn đang bị khóa.'], 403);
        }

        // 5. Đăng nhập thành công -> Tạo Token 
        $token = $admin->createToken('admin-token')->plainTextToken;

        return response()->json([
            'message' => 'Đăng nhập trang quản trị thành công!',
            'user'    => $admin, 
            'token'   => $token,
        ], 200);
    }
}
