<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Coupon extends Model
{
    /** @use HasFactory<\Database\Factories\CouponFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $table = 'coupons';

    protected $fillable = [
        'name',
        'code',
        'min_spend', // Số tiền tối thiểu
        'type', // Kiểu giảm giá (vd: %,...)
        'value', // Giá trị giảm (vd: 100K,...)
        'usage_limit', // Tổng số lượt dùng
        'usage_count', // Số lượng mã giảm giá
        'usage_limit_per_user', // Số lượt dùng cho từng user
        'expires_at', // Ngày hết hạn
    ];

    /**
     * Tự động ép kiểu dữ liệu cho đúng.
     */
    protected function casts(): array
    {
        return [
            // Ép các cột này thành số nguyên (integer)
            'min_spend' => 'integer',
            'value' => 'integer',
            'usage_limit' => 'integer',
            'usage_count' => 'integer',
            'usage_limit_per_user' => 'integer',
            
            // Ép cột này thành đối tượng Ngày/Giờ (Carbon)
            'expires_at' => 'datetime',
        ];
    }

    /**
     * "Nối" để lấy TẤT CẢ các lượt sử dụng của coupon này.
     * (Quan hệ 1-Nhiều: Một Coupon 'hasMany' CouponUsage)
     */
    public function usages()
    {
        // Giả sử Model của bạn tên là CouponUsage
        return $this->hasMany(CouponUsage::class); 
    }
}