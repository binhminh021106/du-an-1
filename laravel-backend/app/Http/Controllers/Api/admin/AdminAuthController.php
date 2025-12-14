<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Notifications\AdminResetPasswordNotification; // Chúng ta sẽ tạo file này ở dưới
use Carbon\Carbon;

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

    /**
     * Chức năng 1: Gửi yêu cầu quên mật khẩu
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:admins,email',
        ], [
            'email.required' => 'Vui lòng nhập email.',
            'email.email' => 'Email không đúng định dạng.',
            'email.exists' => 'Email này không tồn tại trong hệ thống quản trị.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Tạo token ngẫu nhiên
        $token = Str::random(60);
        $email = $request->email;

        // Lưu token vào bảng password_reset_tokens
        // Lưu ý: Laravel mặc định có bảng này, nếu chưa có hãy chạy migrate
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'email' => $email,
                'token' => Hash::make($token), // Hash token để bảo mật
                'created_at' => Carbon::now()
            ]
        );

        // Gửi email
        $admin = Admin::where('email', $email)->first();
        if ($admin) {
            // Gửi Notification
            $admin->notify(new AdminResetPasswordNotification($token));
        }

        return response()->json([
            'message' => 'Chúng tôi đã gửi liên kết đặt lại mật khẩu vào email của bạn!'
        ], 200);
    }

    /**
     * Chức năng 2: Đặt lại mật khẩu mới
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|string|min:8|confirmed', // password_confirmation
        ], [
            'password.confirmed' => 'Mật khẩu xác nhận không khớp.',
            'password.min' => 'Mật khẩu phải có ít nhất 8 ký tự.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Kiểm tra token trong database
        $passwordReset = DB::table('password_reset_tokens')
            ->where('email', $request->email)
            ->first();

        // Kiểm tra tồn tại và check hash token
        if (!$passwordReset || !Hash::check($request->token, $passwordReset->token)) {
            return response()->json(['message' => 'Token không hợp lệ hoặc đã hết hạn.'], 400);
        }

        // Kiểm tra thời hạn token (ví dụ: 60 phút)
        if (Carbon::parse($passwordReset->created_at)->addMinutes(60)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $request->email)->delete();
            return response()->json(['message' => 'Token đã hết hạn, vui lòng gửi lại yêu cầu.'], 400);
        }

        // Cập nhật mật khẩu Admin
        $admin = Admin::where('email', $request->email)->first();
        if (!$admin) {
            return response()->json(['message' => 'Không tìm thấy người dùng.'], 404);
        }

        $admin->password = Hash::make($request->password);
        $admin->save();

        // Xóa token sau khi dùng xong
        DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json([
            'message' => 'Mật khẩu đã được đặt lại thành công! Vui lòng đăng nhập.'
        ], 200);
    }
}