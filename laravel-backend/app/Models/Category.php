<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Khai báo CÁC CỘT (column) MÀ BẠN CHO PHÉP
     * được "thêm" hoặc "sửa" hàng loạt từ bên ngoài (ví dụ: từ API).
     *
     * Đây là tính năng bảo mật Mass Assignment của Laravel.
     */
    protected $fillable = [
        'name',
        'description',
        'order_number',
        'status',
    ];


    /**
     * Ghi chú: Nếu bảng của bạn KHÔNG có 2 cột 'created_at' và 'updated_at',
     * hãy thêm dòng này vào để tắt tính năng timestamps của Laravel:
     */
    // public $timestamps = false;

}