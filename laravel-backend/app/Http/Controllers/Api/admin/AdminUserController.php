<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\File; // Dùng File facade cho nhất quán
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminUserController extends Controller
{
    /**
     * Lấy tất cả danh sách người dùng.
     */
    public function index()
    {
        try {
            $users = User::orderBy('created_at', 'desc')->get();
            return response()->json($users);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi truy vấn danh sách người dùng.',
                'debug' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Tạo người dùng mới.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'phone' => 'nullable|string|max:20',
            'sex' => 'required|in:male,female,other',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
        ], [
            'avatar.max' => 'Ảnh không được vượt quá 10MB.',
            'sex.required' => 'Vui lòng chọn giới tính.',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $user = new User();
            $user->fullName = $request->name;
            $user->email = $request->email;
            $user->phone = $request->phone;
            $user->sex = $request->sex;
            $user->status = $request->status ?? 'active';
            $user->password = Hash::make($request->password);
            $user->birthday = $request->birthday;
            $user->address = $request->address;
            
            $user->save(); // Lưu trước để lấy ID (nếu cần dùng ID đặt tên file)

            // --- XỬ LÝ LƯU ẢNH ---
            if ($request->hasFile('avatar')) {
                $file = $request->file('avatar');
                
                // Đặt tên file giống bên User: Time + Tên gốc (tránh trùng và tránh cache)
                $filename = time() . '_' . $file->getClientOriginalName();
                $destinationPath = public_path('uploads/users');

                // Tạo thư mục nếu chưa có
                if (!File::exists($destinationPath)) {
                    File::makeDirectory($destinationPath, 0755, true);
                }

                // Di chuyển file
                $file->move($destinationPath, $filename);
                
                // [QUAN TRỌNG] Dùng asset() để lưu Full URL giống bên User Controller
                $user->avatar_url = asset('uploads/users/' . $filename);
                $user->save(); 
            }
            
            DB::commit();
            return response()->json($user, 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Lỗi tạo tài khoản: ' . $e->getMessage()], 500); 
        }
    }

    /**
     * Cập nhật thông tin người dùng.
     */
    public function update(Request $request, string $id)
    {
        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'email' => 'sometimes|required|email|unique:users,email,' . $id,
            'name' => 'sometimes|required|string|max:255',
            'phone' => 'nullable|string',
            'sex' => 'sometimes|in:male,female,other',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            if ($request->has('name')) $user->fullName = $request->name;
            if ($request->has('email')) $user->email = $request->email;
            if ($request->has('phone')) $user->phone = $request->phone;
            if ($request->has('address')) $user->address = $request->address;
            if ($request->has('birthday')) $user->birthday = $request->birthday;
            if ($request->has('status')) $user->status = $request->status;
            if ($request->has('sex')) $user->sex = $request->sex;
            
            if ($request->filled('password')) {
                $user->password = Hash::make($request->password);
            }

            // --- XỬ LÝ CẬP NHẬT ẢNH ---
            if ($request->hasFile('avatar')) {
                $file = $request->file('avatar');

                // 1. Xóa ảnh cũ (Logic giống UserController)
                if ($user->avatar_url) {
                    $oldFileName = basename($user->avatar_url); // Lấy tên file từ URL
                    $oldPath = public_path('uploads/users/' . $oldFileName);
                    
                    if (File::exists($oldPath)) {
                        File::delete($oldPath);
                    }
                }

                // 2. Lưu ảnh mới
                $filename = time() . '_' . $file->getClientOriginalName();
                $destinationPath = public_path('uploads/users');

                if (!File::exists($destinationPath)) {
                    File::makeDirectory($destinationPath, 0755, true);
                }

                $file->move($destinationPath, $filename);

                // [QUAN TRỌNG] Lưu Full URL
                $user->avatar_url = asset('uploads/users/' . $filename);
            }

            $user->save();
            DB::commit();
            return response()->json($user);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Lỗi cập nhật: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Xóa người dùng.
     */
    public function destroy(string $id)
    {
        try {
            $user = User::findOrFail($id);
            
            // Xóa ảnh đại diện vật lý nếu có
            if ($user->avatar_url) {
                $oldFileName = basename($user->avatar_url);
                $imagePath = public_path('uploads/users/' . $oldFileName);
                
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