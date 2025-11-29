<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\File; // Sử dụng File Facade để xử lý file vật lý
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

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
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            // Cập nhật validate ảnh: Max 10MB, kích thước tối thiểu 100x100
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240|dimensions:min_width=100,min_height=100',
            'phone' => 'nullable|string|max:20',
        ], [
            'avatar.max' => 'Ảnh không được vượt quá 10MB.',
            'avatar.dimensions' => 'Ảnh đại diện phải có kích thước tối thiểu 100x100 pixel.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $user = new User();
            $user->fullName = $request->name; // Map 'name' từ form sang 'fullName'
            $user->email = $request->email;
            $user->phone = $request->phone;
            $user->status = $request->status ?? 'active';
            $user->password = Hash::make($request->password);

            // --- XỬ LÝ LƯU ẢNH ---
            if ($request->hasFile('avatar')) {
                $file = $request->file('avatar');
                // Tạo tên file unique
                $filename = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
                $path = public_path('uploads/users'); // Lưu vào thư mục public/uploads/users

                if (!File::exists($path)) {
                    File::makeDirectory($path, 0755, true);
                }

                $file->move($path, $filename);
                $user->avatar_url = '/uploads/users/' . $filename; // Lưu đường dẫn tương đối
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
        $validator = Validator::make($request->all(), [
            'email' => 'sometimes|required|email|unique:users,email,' . $id,
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string',
            // Cập nhật validate ảnh: Max 10MB, kích thước tối thiểu 100x100
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240|dimensions:min_width=100,min_height=100',
        ], [
            'avatar.max' => 'Ảnh không được vượt quá 10MB.',
            'avatar.dimensions' => 'Ảnh đại diện phải có kích thước tối thiểu 100x100 pixel.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // Kiểm tra và cập nhật các trường
            if ($request->has('name')) $user->fullName = $request->name;
            if ($request->has('email')) $user->email = $request->email;
            if ($request->has('phone')) $user->phone = $request->phone;
            if ($request->has('status')) $user->status = $request->status;
            
            if ($request->filled('password')) {
                $user->password = Hash::make($request->password);
            }

            // --- XỬ LÝ CẬP NHẬT ẢNH ---
            if ($request->hasFile('avatar')) {
                // 1. Xóa ảnh cũ nếu có
                if ($user->avatar_url) {
                    $oldPath = public_path($user->avatar_url);
                    if (File::exists($oldPath)) {
                        File::delete($oldPath);
                    }
                }

                // 2. Lưu ảnh mới
                $file = $request->file('avatar');
                $filename = time() . '_' . uniqid() . '_' . $file->getClientOriginalName();
                $path = public_path('uploads/users');

                if (!File::exists($path)) {
                    File::makeDirectory($path, 0755, true);
                }

                $file->move($path, $filename);
                $user->avatar_url = '/uploads/users/' . $filename;
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
            
            // --- XOÁ ẢNH VẬT LÝ ---
            if ($user->avatar_url) {
                $imagePath = public_path($user->avatar_url);
                // Kiểm tra file có tồn tại không trước khi xóa
                if (File::exists($imagePath)) {
                    File::delete($imagePath);
                }
            }
            
            $user->delete();
            return response()->json(['message' => 'Xóa thành công']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi khi xóa: ' . $e->getMessage()], 500);
        }
    }
}