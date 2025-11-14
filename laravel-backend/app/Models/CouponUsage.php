<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
// Giả sử bạn có các model này
use App\Models\User;
use App\Models\Coupon;
use App\Models\Order;

class CouponUsage extends Model
{
    /** @use HasFactory<\Database\Factories\CouponUsageFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $table = 'coupon_usage';

    // Thêm dòng này nếu bảng không có created_at, updated_at
    // public $timestamps = false;

    protected $fillable = [
        'coupon_id',
        'user_id',
        'order_id'
    ];

    /**
     * Lấy thông tin user đã sử dụng coupon.
     */
    public function user()
    {
        // 'user_id' là foreign key, 'id' là primary key của bảng users
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Lấy thông tin coupon đã được sử dụng.
     */
    public function coupon()
    {
        return $this->belongsTo(Coupon::class, 'coupon_id', 'id');
    }

    /**
     * Lấy thông tin đơn hàng đã áp dụng coupon.
     */
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }
}