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
        // Validate
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'avatar' => 'nullable|image|max:2048',
        ]);

        DB::beginTransaction();
        try {
            $user = new User();
            $user->fullName = $request->name;
            $user->email = $request->email;
            $user->phone = $request->phone;
            $user->status = $request->status ?? 'active';
            $user->password = Hash::make('123456');

            // --- LƯU ẢNH ---
            if ($request->hasFile('avatar')) {
                $path = $request->file('avatar')->store('avatars', 'public');
                $user->avatar_url = asset('storage/' . $path);
            }
            // ---------------

            $user->save();

            DB::commit();
            return response()->json($user, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'email' => 'required|email|unique:users,email,' . $id,
        ]);

        DB::beginTransaction();
        try {
            if ($request->has('name')) $user->fullName = $request->name;
            if ($request->has('email')) $user->email = $request->email;
            if ($request->has('phone')) $user->phone = $request->phone;
            if ($request->has('status')) $user->status = $request->status;

            // --- CẬP NHẬT ẢNH ---
            if ($request->hasFile('avatar')) {
                // Xóa ảnh cũ
                if ($user->avatar_url) {
                    $oldPath = str_replace(asset('storage/'), '', $user->avatar_url);
                    Storage::disk('public')->delete($oldPath);
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
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function destroy(string $id)
    {
        $user = User::findOrFail($id);
        $user->delete();
        return response()->json(['message' => 'Đã xóa thành công']);
    }
}