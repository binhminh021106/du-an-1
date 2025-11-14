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

    protected $table = 'order_items';

    protected $fillable = [
        'order_id',
        'variant_id',
        'quantity',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer',
        'order_id' => 'integer',
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
     * (Giả sử bạn có model ProductVariant)
     */
    public function variant()
    {
        // 'variant_id' là khoá ngoại, liên kết với 'id' của bảng 'product_variants'
        return $this->belongsTo(Variant::class, 'variant_id', 'id');
    }
}
