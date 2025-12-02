<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;

class AminAccountController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $admins = Admin::orderBy('created_at', 'desc')->get();
        return response()->json($admins);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fullname' => 'required|string|max:255',
            'email' => 'required|email|max:100|unique:admins,email',
            'password' => 'required|string|min:6',
            'role_id' => 'required',
            'phone' => 'nullable|string|max:20',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi dữ liệu đầu vào',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $admin = new Admin();
            $admin->fullname = $request->fullname;
            $admin->email = $request->email;
            $admin->password = Hash::make($request->password);
            $admin->phone = $request->phone ?? null;
            $admin->address = $request->address ?? null;
            $admin->role_id = $request->role_id;
            $admin->status = $request->status ?? 'active';
            
            // Lưu lần 1 để lấy ID trước (Chưa có ảnh)
            $admin->save();

            // --- XỬ LÝ UPLOAD ẢNH SAU KHI CÓ ID ---
            if ($request->hasFile('avatar')) {
                $file = $request->file('avatar');
                
                // Đặt tên file theo format: avatar_ID.extension (Ví dụ: avatar_15.jpg)
                $extension = $file->getClientOriginalExtension();
                $fileName = 'avatar_' . $admin->id . '.' . $extension; 
                
                $path = public_path('uploads/admins');

                if (!File::exists($path)) {
                    File::makeDirectory($path, 0755, true);
                }

                // Di chuyển file
                $file->move($path, $fileName);

                // Cập nhật lại đường dẫn ảnh cho admin
                $admin->avatar_url = '/uploads/admins/' . $fileName;
                $admin->save(); // Lưu lần 2 để update avatar_url
            }

            return response()->json([
                'success' => true,
                'message' => 'Tạo tài khoản thành công',
                'data' => $admin
            ], 201);

        } catch (\Exception $e) {
            // Nếu lỗi, và đã lỡ tạo admin rồi nhưng lỗi upload thì nên xóa admin rác đi (tuỳ chọn)
            // if (isset($admin->id)) $admin->delete();
            
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi tạo tài khoản: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $admin = Admin::findOrFail($id);
        return response()->json($admin);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $admin = Admin::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'fullname' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|max:100|unique:admins,email,' . $id,
            'password' => 'sometimes|nullable|min:6',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi dữ liệu đầu vào',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            if ($request->has('fullname')) $admin->fullname = $request->fullname;
            if ($request->has('email')) $admin->email = $request->email;
            if ($request->has('phone')) $admin->phone = $request->phone;
            if ($request->has('address')) $admin->address = $request->address;
            if ($request->has('role_id')) $admin->role_id = $request->role_id;
            if ($request->has('status')) $admin->status = $request->status;

            // --- XỬ LÝ UPLOAD ẢNH KHI UPDATE ---
            if ($request->hasFile('avatar')) {
                // 1. Xóa ảnh cũ (bất kể tên gì) để tránh rác
                if ($admin->avatar_url) {
                    $oldPath = public_path($admin->avatar_url);
                    if (File::exists($oldPath)) {
                        File::delete($oldPath);
                    }
                }

                // 2. Lưu ảnh mới với tên là avatar_ID
                $file = $request->file('avatar');
                $extension = $file->getClientOriginalExtension();
                $fileName = 'avatar_' . $admin->id . '.' . $extension; // Ví dụ: avatar_15.jpg
                
                $path = public_path('uploads/admins');

                if (!File::exists($path)) {
                    File::makeDirectory($path, 0755, true);
                }

                $file->move($path, $fileName);
                $admin->avatar_url = '/uploads/admins/' . $fileName;
            }

            if ($request->filled('password')) {
                $admin->password = Hash::make($request->password);
            }

            $admin->save();

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật thành công',
                'data' => $admin
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi cập nhật: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        try {
            $admin = Admin::findOrFail($id);

            // --- XÓA FILE VẬT LÝ ---
            if ($admin->avatar_url) {
                $path = public_path($admin->avatar_url);
                if (File::exists($path)) {
                    File::delete($path);
                }
            }

            $admin->delete();

            return response()->json([
                'success' => true,
                'message' => 'Đã xóa tài khoản và ảnh đại diện thành công.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi xóa: ' . $e->getMessage()
            ], 500);
        }
    }
}