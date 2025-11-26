<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Models\OrderItem;
use App\Models\AttributeValue;
use App\Models\Product;

class Variant extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'variants';

    protected $fillable = [
        'product_id',
        'price',
        'original_price',
        'stock'
    ];

    /**
     * Quan hệ Many-to-Many với AttributeValue
     * Bảng trung gian: variant_attribute_values
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    // Quan hệ với AttributeValues (Many-to-Many)
    public function attributeValues()
    {
        return $this->belongsToMany(
            AttributeValue::class,
            'variant_attribute_values', // Bảng pivot
            'variant_id',
            'attribute_value_id'
        );
    }


    // public function attributeValues()
    // {
    //     return $this->belongsToMany(
    //         AttributeValue::class,
    //         'variant_attribute_values',
    //         'variant_id',
    //         'attribute_value_id'
    //     )->withTimestamps();
    // }

    // public function product()
    // {
    //     return $this->belongsTo(Product::class, 'product_id', 'id');
    // }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'variant_id', 'id');
    }

    /**
     * Xử lý sự kiện khi xóa Variant
     */
    protected static function booted()
    {
        // Sử dụng 'forceDeleting' thay vì 'deleting'
        // Lý do: Nếu dùng 'deleting', khi bạn xóa mềm (Soft Delete), quan hệ trong bảng pivot sẽ bị xóa luôn (detach).
        // Khi đó, nếu bạn khôi phục (restore) lại Variant, nó sẽ bị mất thuộc tính (không còn màu/size nữa).

        static::forceDeleting(function ($variant) {
            // Chỉ khi xóa vĩnh viễn (Force Delete) thì mới xóa sạch quan hệ trong bảng trung gian
            $variant->attributeValues()->detach();

            // Xử lý logic OrderItems nếu cần (thường thì không nên xóa OrderItem để giữ lịch sử đơn hàng)
            // $variant->orderItems()->delete(); 
        });
    }
}
