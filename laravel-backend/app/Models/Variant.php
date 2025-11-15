<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Models\OrderItem;

class Variant extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Tên bảng mà Model này quản lý.
     * * Ghi chú: Dòng này KHÔNG bắt buộc nếu tên bảng của bạn
     * là 'products'. Laravel tự đoán được.
     * Mình thêm vào để bạn biết cách làm nếu lỡ đặt tên bảng khác (ví dụ: 'san_pham').
     */
    protected $table = 'variants';


    /**
     * Khai báo CÁC CỘT (column) MÀ BẠN CHO PHÉP
     * được "thêm" hoặc "sửa" hàng loạt từ bên ngoài (ví dụ: từ API).
     *
     * Đây là tính năng bảo mật Mass Assignment của Laravel.
     */
    protected $fillable = [
        'product_id',
        'price',
        'original_price', // giá gốc,
        'stock',
        'configuration', // Cấu hình (vd: 512gb, ...)
        'color',
    ];


    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'variant_id', 'id');
    }
}
