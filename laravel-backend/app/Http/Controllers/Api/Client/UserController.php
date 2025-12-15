<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $users = User::all();
        return response()->json($users);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // 
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $user = User::findOrFail($id);
        return response()->json($user);
    }

    /**
     * Update the specified resource in storage.
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user(); // Lấy user đang đăng nhập

        // 1. Validate dữ liệu
        $request->validate([
            'fullName' => 'required|string|max:255',
            'phone'    => 'required|regex:/(0)[0-9]{9}/',
            'sex'      => 'nullable|string',
            'birthday' => 'nullable|date',
            'avatar'   => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
        ]);

        // Lấy các dữ liệu text trước
        $data = $request->only(['fullName', 'phone', 'sex', 'birthday']);

        // 2. Xử lý upload ảnh nếu có
        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');

            // --- XÓA ẢNH CŨ (Nếu có) ---
            if ($user->avatar_url) {
                // Lấy tên file từ đường dẫn URL cũ (ví dụ: http://.../abc.jpg => abc.jpg)
                $oldFileName = basename($user->avatar_url);
                // Xác định đường dẫn vật lý của file cũ
                $oldFilePath = public_path('uploads/users/' . $oldFileName);

                // Kiểm tra file có tồn tại trong thư mục uploads/users không thì xóa
                if (file_exists($oldFilePath)) {
                    unlink($oldFilePath);
                }
            }

            // --- LƯU ẢNH MỚI ---
            // Tạo tên file mới: thời gian + tên gốc (để tránh trùng lặp)
            $fileName = time() . '_' . $file->getClientOriginalName();

            // Di chuyển file vào thư mục public/uploads/users
            $file->move(public_path('uploads/users'), $fileName);

            // Lưu đường dẫn đầy đủ vào DB (http://your-domain/uploads/users/ten-file.jpg)
            $data['avatar_url'] = asset('uploads/users/' . $fileName);
        }

        // 3. Cập nhật vào DB
        $user->update($data);

        return response()->json([
            'message' => 'Cập nhật hồ sơ thành công',
            'user' => $user
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
