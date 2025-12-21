<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\Order;
use App\Models\Variant;

class OrderItem extends Model
{
    /** @use HasFactory<\Database\Factories\OrderItemFactory> */
    use HasFactory;

    /**
     * Tên bảng trong Database.
     * Khớp với schema: 'order_items' (số nhiều).
     */
    protected $table = 'order_items';

    protected $fillable = [
        'order_id',
        'variant_id',
        'quantity',
        'price',
    ];

    /**
     * Ép kiểu dữ liệu.
     * QUAN TRỌNG: 'price' trong DB là bigint -> ép về 'integer'.
     * Tránh dùng 'decimal:2' cho tiền VNĐ để đảm bảo tính toán chính xác.
     */
    protected $casts = [
        'price'      => 'integer',
        'quantity'   => 'integer',
        'order_id'   => 'integer',
        'variant_id' => 'integer',
    ];

    // --- RELATIONSHIPS ---

    /**
     * Chi tiết này thuộc về đơn hàng nào.
     */
    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id', 'id');
    }

    /**
     * Chi tiết này là của biến thể sản phẩm nào.
     * Liên kết với Model Variant.
     */
    public function variant()
    {
        // 'variant_id' là khoá ngoại, liên kết với 'id' của bảng variants
        return $this->belongsTo(Variant::class, 'variant_id', 'id');
    }
}