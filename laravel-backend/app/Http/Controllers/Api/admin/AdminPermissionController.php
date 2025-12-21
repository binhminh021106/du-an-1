<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Permission;

class AdminPermissionController extends Controller
{
    /**
     * Lấy danh sách quyền hạn, gom nhóm theo 'group_name'
     * Để hiển thị đẹp trên giao diện phân quyền
     */
    public function index()
    {
        // Lấy tất cả quyền
        $permissions = Permission::all();

        // Gom nhóm dữ liệu để Frontend dễ hiển thị theo từng block (Sản phẩm, Đơn hàng...)
        // Kết quả trả về dạng: { "Sản phẩm": [...], "Đơn hàng": [...] }
        $grouped = $permissions->groupBy('group_name');

        return response()->json($grouped);
    }
}