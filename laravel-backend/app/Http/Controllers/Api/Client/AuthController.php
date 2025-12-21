<?php

namespace App\Http\Controllers\Api\Client;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    // --- ĐĂNG KÝ ---
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name'     => 'required|string|max:255',
            'email'    => 'required|string|email|max:255|unique:users',
            'phone'    => 'required|string|max:15|unique:users',
            'password' => 'required|string|min:8',
        ], [
            'email.unique' => 'Email này đã được sử dụng.',
            'phone.unique' => 'Số điện thoại này đã được sử dụng.',
        ]);

        if ($validator->fails()) return response()->json(['errors' => $validator->errors()], 422);

        $user = User::create([
            'fullName'   => $request->name,
            'email'      => $request->email,
            'phone'      => $request->phone,
            'password'   => Hash::make($request->password),
            'status'     => 'active',
        ]);

        return response()->json(['message' => 'Đăng ký thành công!', 'user' => $user], 201);
    }

    // --- ĐĂNG NHẬP ---
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'login_id' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) return response()->json($validator->errors(), 422);

        // Check login bằng Email hay SĐT
        $field = filter_var($request->login_id, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        if (!Auth::attempt([$field => $request->login_id, 'password' => $request->password])) {
            return response()->json(['message' => 'Tài khoản hoặc mật khẩu không chính xác.'], 401);
        }

        $user = Auth::user();

        if ($user->status !== 'active') {
            return response()->json(['message' => 'Tài khoản bị khóa.'], 403);
        }

        $token = $user->createToken('client-token')->plainTextToken;

        return response()->json([
            'message' => 'Đăng nhập thành công',
            'user'    => $user,
            'token'   => $token,
        ], 200);
    }

    // --- SOCIAL LOGIN (Google & Facebook) ---

    public function redirectToGoogle()
    {
        return Socialite::driver('google')->stateless()->redirect();
    }
    public function handleGoogleCallback()
    {
        return $this->handleSocialCallback('google');
    }

    public function redirectToFacebook()
    {
        return Socialite::driver('facebook')->stateless()->redirect();
    }
    public function handleFacebookCallback()
    {
        return $this->handleSocialCallback('facebook');
    }

    // Xử lý chung
    protected function handleSocialCallback($driver)
    {
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');

        // 1. Check lỗi/hủy thao tác
        if (request()->has('error') || request()->missing('code')) {
            return redirect($frontendUrl . '/login?error=social_cancelled');
        }

        try {
            $socialUser = Socialite::driver($driver)->stateless()->user();

            // 2. Tìm user (theo ID MXH hoặc Email)
            $user = User::where($driver . '_id', $socialUser->id)
                ->orWhere('email', $socialUser->email)
                ->first();

            // 3. Tạo mới hoặc Update ID
            if (!$user) {
                $user = User::create([
                    'fullName'    => $socialUser->name,
                    'email'       => $socialUser->email ?? $socialUser->id . '@facebook.local',
                    $driver . '_id' => $socialUser->id,
                    'status'      => 'active',
                    'avatar_url'  => $socialUser->avatar,
                    'sex'         => 'other',
                ]);
            } elseif (!$user->{$driver . '_id'}) {
                $user->update([$driver . '_id' => $socialUser->id]);
            }

            if ($user->status !== 'active') return redirect($frontendUrl . '/login?error=account_locked');

            // 4. Tạo token & Redirect về Frontend (kèm origin)
            $token = $user->createToken('client-token')->plainTextToken;
            return redirect($frontendUrl . '/social-callback?token=' . $token . '&origin=' . $driver);
        } catch (\Exception $e) {
            return redirect($frontendUrl . '/login?error=login_failed&message=' . urlencode($e->getMessage()));
        }
    }
}
