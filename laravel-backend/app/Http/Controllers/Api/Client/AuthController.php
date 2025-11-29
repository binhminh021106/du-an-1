<?php

namespace App\Http\Controllers\Api\Client;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
// Thêm 2 dòng này
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'phone'    => 'required|string|max:15|unique:users',
            'password' => 'required|string|min:8',
            'sex'      => 'required|in:male,female,other',
        ], [
            'email.unique' => 'Email này đã được sử dụng.',
            'phone.unique' => 'Số điện thoại này đã được sử dụng.',
            'sex.required' => 'Vui lòng chọn giới tính.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'fullName' => $request->name,
            'email'    => $request->email,
            'phone'    => $request->phone,
            'sex' => $request->sex,
            'password' => Hash::make($request->password),
            'status'   => 'active',
            'avatar_url' => null,
        ]);

        return response()->json([
            'message' => 'Đăng ký thành công!',
            'user'    => $user
        ], 201);
    }

    public function login(Request $request)
    {
        // 1. Validate dữ liệu gửi lên
        $validator = Validator::make($request->all(), [
            'login_id' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $loginInput = $request->login_id;

        // 2. Tự động nhận diện là Email hay SĐT
        $fieldType = filter_var($loginInput, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        $credentials = [
            $fieldType => $loginInput,
            'password' => $request->password,
        ];

        // 3. Xác thực (Laravel tự so sánh hash password ở đây)
        if (!Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'Tài khoản hoặc mật khẩu không chính xác.'
            ], 401);
        }

        // 4. Đăng nhập thành công -> Tạo Token
        $user = Auth::user();

        if ($user->status !== 'active') {
            return response()->json(['message' => 'Tài khoản của bạn đã bị khóa.'], 403);
        }

        $token = $user->createToken('client-token')->plainTextToken;

        return response()->json([
            'message' => 'Đăng nhập thành công',
            'user'    => $user,
            'token'   => $token,
        ], 200);
        
    }

    // ==========================================
    // PHẦN THÊM MỚI CHO GOOGLE LOGIN
    // ==========================================

    public function redirectToGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }

    public function handleGoogleCallback()
    {
        try {
            $googleUser = Socialite::driver('google')->stateless()->user();

            // Tìm user theo google_id hoặc email
            $user = User::where('google_id', $googleUser->id)
                        ->orWhere('email', $googleUser->email)
                        ->first();

            if (!$user) {
                // Tạo user mới khớp với DB của bạn
                $user = User::create([
                    'fullName'   => $googleUser->name, // Map sang fullName
                    'email'      => $googleUser->email,
                    'google_id'  => $googleUser->id,
                    'password'   => null, // Mật khẩu null vì login Google
                    'status'     => 'active', // Set active luôn để login được ngay
                    'avatar_url' => $googleUser->avatar, // Lấy luôn avatar Google
                    'phone'      => null, // Google thường không trả về phone, để null
                ]);
            } else {
                // Nếu user đã có email nhưng chưa có google_id thì cập nhật thêm
                if (!$user->google_id) {
                    $user->update(['google_id' => $googleUser->id]);
                }
            }

            // Kiểm tra status (dùng chung logic với login thường)
            if ($user->status !== 'active') {
                return response()->json(['message' => 'Tài khoản bị khóa'], 403);
            }

            // Tạo Token (dùng chung tên 'client-token' như hàm login của bạn)
            $token = $user->createToken('client-token')->plainTextToken;

            // Redirect về Vue
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000/google-callback');
            return redirect($frontendUrl . '?token=' . $token);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Google Login Failed: ' . $e->getMessage()], 500);
        }
    }
}