<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;

    protected $table = 'permissions';

    protected $fillable = [
        'slug',       // Mã quyền (VD: product.view)
        'name',       // Tên hiển thị (VD: Xem sản phẩm)
        'group_name'  // Nhóm (VD: Sản phẩm)
    ];

    /**
     * Quan hệ Many-to-Many với Role
     * Một quyền có thể thuộc về nhiều vai trò
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permissions', 'permission_id', 'role_id');
    }
}