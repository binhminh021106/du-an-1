<?php

namespace App\Http\Controllers\Api\Admin; 

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AdminUserController extends Controller
{
    /**
     * Lấy tất cả danh sách người dùng (khách hàng).
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        // *** ĐÃ THÊM TRY/CATCH ĐỂ HIỂN THỊ LỖI CHÍNH XÁC ***
        try {
            $users = User::orderBy('created_at', 'desc')->get();
            return response()->json($users);
        } catch (\Exception $e) {
            // Trả về lỗi chi tiết từ PHP Exception (Lỗi DB, Model, v.v.)
            return response()->json([
                'message' => 'Lỗi truy vấn danh sách người dùng.',
                'debug' => $e->getMessage(), // HIỂN THỊ LỖI CẦN THIẾT NHẤT
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Tạo người dùng (khách hàng) mới.
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'phone' => 'nullable|string|max:20',
            'sex' => 'required|in:male,female,other',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240|dimensions:min_width=100,min_height=100',
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
            // Lưu ý: Tên cột DB là fullName, nhưng request từ front-end dùng 'name'
            $user->fullName = $request->name; 
            $user->email = $request->email;
            $user->phone = $request->phone;
            $user->sex = $request->sex;
            $user->status = $request->status ?? 'active';
            $user->password = Hash::make($request->password);
            $user->birthday = $request->birthday; // Thêm trường birthday
            $user->address = $request->address;   // Thêm trường address
            
            // Lưu user trước để có ID
            $user->save(); 

            // --- XỬ LÝ LƯU ẢNH SAU KHI CÓ ID ---
            if ($request->hasFile('avatar')) {
                $file = $request->file('avatar');
                
                $filename = 'avatar_' . $user->id . '.' . $file->getClientOriginalExtension();
                $path = public_path('uploads/users');

                if (!File::exists($path)) {
                    File::makeDirectory($path, 0755, true);
                }

                $file->move($path, $filename);
                
                $user->avatar_url = 'uploads/users/' . $filename; // Bỏ dấu '/' đầu tiên
                $user->save(); 
            }
            
            DB::commit();
            return response()->json($user, 201);

        } catch (\Exception $e) {
            DB::rollBack();
            // Trả về lỗi chi tiết để debug
            return response()->json(['message' => 'Lỗi tạo tài khoản: ' . $e->getMessage()], 500); 
        }
    }

    /**
     * Cập nhật thông tin người dùng.
     * @param Request $request
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
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
            if ($request->has('address')) $user->address = $request->address; // Cập nhật address
            if ($request->has('birthday')) $user->birthday = $request->birthday; // Cập nhật birthday
            if ($request->has('status')) $user->status = $request->status;
            if ($request->has('sex')) $user->sex = $request->sex;
            
            if ($request->filled('password')) {
                $user->password = Hash::make($request->password);
            }

            // --- XỬ LÝ CẬP NHẬT ẢNH ---
            if ($request->hasFile('avatar')) {
                // Xóa ảnh cũ
                if ($user->avatar_url) {
                    $oldPath = public_path($user->avatar_url);
                    if (File::exists($oldPath)) {
                        File::delete($oldPath);
                    }
                }

                $file = $request->file('avatar');
                
                $filename = 'avatar_' . $user->id . '.' . $file->getClientOriginalExtension();
                $path = public_path('uploads/users');

                if (!File::exists($path)) {
                    File::makeDirectory($path, 0755, true);
                }

                $file->move($path, $filename);
                $user->avatar_url = 'uploads/users/' . $filename; // Bỏ dấu '/' đầu tiên
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
     * @param string $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy(string $id)
    {
        try {
            $user = User::findOrFail($id);
            
            // Xóa ảnh đại diện vật lý nếu có
            if ($user->avatar_url) {
                $imagePath = public_path($user->avatar_url);
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