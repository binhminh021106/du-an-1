<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Admin; 
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\File; // Thêm thư viện xử lý file

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
        $request->validate([
            'fullname' => 'required|string|max:255',
            'email' => 'required|email|max:100|unique:admins,email',
            'password' => 'required|string|min:6', 
            'role_id' => 'required', 
            'phone' => 'nullable|string|max:20',
            // Thêm validate cho file ảnh nếu cần
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', 
        ]);

        try {
            $admin = new Admin();
            $admin->fullname = $request->fullname; 
            $admin->email = $request->email;
            $admin->password = Hash::make($request->password);
            $admin->phone = $request->phone ?? null;
            $admin->address = $request->address ?? null;
            $admin->role_id = $request->role_id;
            $admin->status = $request->status ?? 'active';
            
            // --- XỬ LÝ UPLOAD ẢNH (MỚI) ---
            if ($request->hasFile('avatar')) {
                $file = $request->file('avatar');
                // Tạo tên file ngẫu nhiên để tránh trùng
                $fileName = time() . '_' . $file->getClientOriginalName();
                // Lưu vào thư mục public/uploads/admins
                $file->move(public_path('uploads/admins'), $fileName);
                // Lưu đường dẫn vào DB (đường dẫn tuyệt đối hoặc tương đối)
                $admin->avatar_url = '/uploads/admins/' . $fileName;
            } else {
                // Nếu không gửi file, dùng avatar_url nếu có (trường hợp gửi link ảnh mạng)
                $admin->avatar_url = $request->avatar_url ?? '';
            }
            // ------------------------------
            
            $admin->save();

            return response()->json($admin, 201);
        } catch (\Exception $e) {
            return response()->json([
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

        $request->validate([
            'fullname' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|max:100|unique:admins,email,' . $id,
            'password' => 'sometimes|nullable|min:6',
            'avatar' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        try {
            if ($request->has('fullname')) $admin->fullname = $request->fullname;
            if ($request->has('email')) $admin->email = $request->email;
            if ($request->has('phone')) $admin->phone = $request->phone;
            if ($request->has('address')) $admin->address = $request->address;
            if ($request->has('role_id')) $admin->role_id = $request->role_id;
            if ($request->has('status')) $admin->status = $request->status;
            
            // --- XỬ LÝ UPLOAD ẢNH KHI UPDATE (MỚI) ---
            if ($request->hasFile('avatar')) {
                // 1. Xóa ảnh cũ nếu tồn tại (để tiết kiệm dung lượng)
                $oldPath = public_path($admin->avatar_url);
                if ($admin->avatar_url && File::exists($oldPath)) {
                    File::delete($oldPath);
                }

                // 2. Lưu ảnh mới
                $file = $request->file('avatar');
                $fileName = time() . '_' . $file->getClientOriginalName();
                $file->move(public_path('uploads/admins'), $fileName);
                $admin->avatar_url = '/uploads/admins/' . $fileName;
            } 
            // ------------------------------------------

            if ($request->filled('password')) {
                $admin->password = Hash::make($request->password);
            }

            $admin->save();

            return response()->json($admin);
        } catch (\Exception $e) {
            return response()->json([
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
            
            // Xóa ảnh khi xóa user (tùy chọn)
            if ($admin->avatar_url && File::exists(public_path($admin->avatar_url))) {
                File::delete(public_path($admin->avatar_url));
            }

            $admin->delete();
            return response()->json(['message' => 'Đã xóa tài khoản thành công.']);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Lỗi khi xóa: ' . $e->getMessage()
            ], 500);
        }
    }
}