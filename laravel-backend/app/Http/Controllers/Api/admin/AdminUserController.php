<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class AdminUserController extends Controller
{
    public function index()
    {
        // Lấy danh sách user, sắp xếp mới nhất
        $users = User::orderBy('created_at', 'desc')->get();
        return response()->json($users);
    }

    public function store(Request $request)
    {
        // Validate dữ liệu đầu vào
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'avatar' => 'nullable|image|max:5120',
        ]);

        DB::beginTransaction();
        try {
            $user = new User();
            $user->fullName = $request->name; // Map 'name' từ form sang 'fullName' trong DB
            $user->email = $request->email;
            $user->phone = $request->phone;
            // $user->address = $request->address; // XÓA DÒNG NÀY: Bảng users không có cột address
            $user->status = $request->status ?? 'active';
            $user->password = Hash::make($request->password);

            // --- LƯU ẢNH ---
            if ($request->hasFile('avatar')) {
                $path = $request->file('avatar')->store('avatars', 'public');
                $user->avatar_url = asset('storage/' . $path);
            }
            
            $user->save();

            DB::commit();
            return response()->json($user, 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Lỗi tạo tài khoản: ' . $e->getMessage()], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        // Validate cập nhật
        $request->validate([
            'email' => 'sometimes|required|email|unique:users,email,' . $id,
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            // Kiểm tra và cập nhật các trường
            if ($request->has('name')) $user->fullName = $request->name;
            if ($request->has('email')) $user->email = $request->email;
            if ($request->has('phone')) $user->phone = $request->phone;
            if ($request->has('status')) $user->status = $request->status;
            
            // XÓA DÒNG CẬP NHẬT ADDRESS VÌ CỘT KHÔNG TỒN TẠI
            // if ($request->has('address')) $user->address = $request->address;

            // --- CẬP NHẬT ẢNH ---
            if ($request->hasFile('avatar')) {
                // Xóa ảnh cũ nếu có và không phải là link ngoài
                if ($user->avatar_url && strpos($user->avatar_url, asset('storage/')) !== false) {
                    $oldPath = str_replace(asset('storage/'), '', $user->avatar_url);
                    if (Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->delete($oldPath);
                    }
                }
                // Lưu ảnh mới
                $path = $request->file('avatar')->store('avatars', 'public');
                $user->avatar_url = asset('storage/' . $path);
            }

            $user->save();
            DB::commit();
            return response()->json($user);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Lỗi cập nhật: ' . $e->getMessage()], 500);
        }
    }

    public function destroy(string $id)
    {
        try {
            $user = User::findOrFail($id);
            // Xóa ảnh nếu cần
            if ($user->avatar_url && strpos($user->avatar_url, asset('storage/')) !== false) {
                 $oldPath = str_replace(asset('storage/'), '', $user->avatar_url);
                 if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                 }
            }
            $user->delete();
            return response()->json(['message' => 'Xóa thành công']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi khi xóa: ' . $e->getMessage()], 500);
        }
    }
}