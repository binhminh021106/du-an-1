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
            'avatar'   => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120', // 5MB
        ]);

        // Lấy các dữ liệu text trước
        $data = $request->only(['fullName', 'phone', 'sex', 'birthday']);

        // 2. Xử lý upload ảnh nếu có
        if ($request->hasFile('avatar')) {
            // (Tuỳ chọn) Xóa ảnh cũ để tránh rác server
            if ($user->avatar_url) {
                // Lấy path tương đối từ full URL cũ để xóa (logic này tùy project)
                // Ví dụ: http://domain.com/storage/avatars/abc.jpg -> avatars/abc.jpg
                $oldPath = str_replace(asset('storage/'), '', $user->avatar_url);
                if (Storage::disk('public')->exists($oldPath)) {
                   // Storage::disk('public')->delete($oldPath); // Uncomment nếu muốn xóa thật
                }
            }

            // Lưu file vào thư mục: storage/app/public/avatars
            $path = $request->file('avatar')->store('avatars', 'public');

            // [FIX QUAN TRỌNG]: Dùng asset() hoặc url() để tạo đường dẫn tuyệt đối (Full URL)
            // Kết quả sẽ là: http://localhost:8000/storage/avatars/ten-file.jpg
            // Thay vì chỉ là: /storage/avatars/ten-file.jpg
            $data['avatar_url'] = asset('storage/' . $path);
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