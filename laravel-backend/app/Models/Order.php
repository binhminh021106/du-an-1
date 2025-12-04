<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// Thêm các model sẽ liên kết
use App\Models\User;
use App\Models\OrderItem;
use App\Models\Coupon;

class Order extends Model
{
    /** @use HasFactory<\Database\Factories\OrderFactory> */
    use HasFactory;
    use SoftDeletes;

    /**
     * Tên bảng trong Database.
     * QUAN TRỌNG: Database của bạn tên bảng là 'order' (số ít), 
     * nên bắt buộc phải khai báo dòng này. Không được sửa thành 'orders'.
     */
    protected $table = 'order'; 

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
     * QUAN TRỌNG: Vì DB dùng 'bigint' cho tiền tệ, ta ép kiểu về 'integer' 
     * để Laravel xử lý đúng số nguyên (VNĐ không dùng số thập phân).
     */
    protected $casts = [
        'subtotal_amount' => 'integer',
        'shipping_fee'    => 'integer',
        'discount_amount' => 'integer',
        'total_amount'    => 'integer',
        'user_id'         => 'integer',
        'coupon_id'       => 'integer',
        // 'created_at' và 'updated_at' tự động cast thành datetime
    ];

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
        // 'coupon_id' có thể là NULL
        return $this->belongsTo(Coupon::class, 'coupon_id', 'id');
    }

    /**
     * Lấy TẤT CẢ các chi tiết (sản phẩm) của đơn hàng này.
     * Quan hệ 1-N (Một đơn hàng có nhiều sản phẩm).
     */
    public function orderDetails()
    {
        return $this->hasMany(OrderItem::class, 'order_id', 'id');
    }
}