<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    /** @use HasFactory<\Database\Factories\RoleFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $table = 'role';

    protected $fillable = [
        'value', // tên user hoặc admin 
        'label', // tên hiển thị
        'badgeClass'
    ];

    /**
     * Quan hệ Many-to-Many với Permission
     * Một vai trò có thể có nhiều quyền
     */
    public function permissions()
    {
        // Tham số thứ 2 là tên bảng trung gian: 'role_permissions'
        // Tham số thứ 3 là khóa ngoại của model hiện tại trong bảng trung gian: 'role_id'
        // Tham số thứ 4 là khóa ngoại của model liên kết trong bảng trung gian: 'permission_id'
        return $this->belongsToMany(Permission::class, 'role_permissions', 'role_id', 'permission_id');
    }

    /**
     * Hàm helper để check xem Role có quyền cụ thể không
     * VD: $role->hasPermission('product.view')
     */
    public function hasPermission($permissionSlug)
    {
        return $this->permissions->contains('slug', $permissionSlug);
    }
}