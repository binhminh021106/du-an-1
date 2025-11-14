<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// Thêm các model sẽ liên kết
use App\Models\User;
use App\Models\Coupon;
use App\Models\OrderDetail;

class Order extends Model
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */ // <-- Thêm dòng này
    use HasFactory;
    use SoftDeletes;

    /**
     * Tên bảng mà Model này quản lý.
     * Quy chuẩn Laravel là 'orders' (số nhiều).
     */
    protected $table = 'orders'; // <-- Sửa thành 'orders'

    /**
     * Các cột được phép gán hàng loạt (Mass Assignment).
     */
    protected $fillable = [
        'user_id',
        'customer_name', 
        'customer_phone',
        'customer_email',
        'shipping_address',
        'status',
        'subtotal_amount',
        'shipping_fee',
        'discount_amount',
        'total_amount',
        'payment_method',
        'payment_status',
        'coupon_id'
    ];

    /**
     * Ép kiểu dữ liệu cho các cột.
     * Rất quan trọng cho các cột tiền tệ.
     */
    protected $casts = [
        'subtotal_amount' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'user_id' => 'integer',
        'coupon_id' => 'integer',
    ];

    /**
     * Ghi chú: Nếu bảng của bạn KHÔNG có 2 cột 'created_at' và 'updated_at',
     * hãy thêm dòng này vào để tắt tính năng timestamps của Laravel:
     */
    // public $timestamps = false;


    // --- CÁC RELATIONSHIPS (RẤT QUAN TRỌNG) ---

    /**
     * Lấy thông tin người dùng (user) đã đặt hàng.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Lấy thông tin coupon đã sử dụng (nếu có).
     */
    public function coupon()
    {
        // 'coupon_id' có thể là NULL, nên dùng 'optional'
        return $this->belongsTo(Coupon::class, 'coupon_id', 'id');
    }

    /**
     * Lấy TẤT CẢ các chi tiết (sản phẩm) của đơn hàng này.
     * Đây là quan hệ quan trọng nhất.
     */
    public function orderDetails()
    {
        return $this->hasMany(OrderItem::class, 'order_id', 'id');
    }
}