<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Product;

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

    protected $table = 'categories';

    protected $fillable = [
        'name',
        'icon',
        'description',
        'order_number',
        'status',
    ];


    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id', 'id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}
