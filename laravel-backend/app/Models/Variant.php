<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Models\OrderItem;
// Thêm Model AttributeValue để thiết lập quan hệ
use App\Models\AttributeValue; 
use App\Models\Product; // Đảm bảo đã có Product Model

class Variant extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'variants';


    protected $fillable = [
        'product_id',
        'price',
        'original_price', 
        'stock',
        // Giả sử 2 cột này được thay thế bằng quan hệ attributes
        // 'configuration', 
        // 'color',
    ];

    /**
     * Thiết lập quan hệ many-to-many với các giá trị thuộc tính.
     * Sử dụng bảng trung gian 'variant_attribute_values'.
     */
    public function attributeValues()
    {
        return $this->belongsToMany(
            AttributeValue::class,
            'variant_attribute_values',
            'variant_id',
            'attribute_value_id'
        )->withTimestamps();
    }
    
    /**
     * Một Variant thuộc về một Product.
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class, 'variant_id', 'id');
    }
    
    /**
     * Xóa mềm các quan hệ khi xóa mềm Variant
     */
    protected static function booted()
    {
        static::deleting(function ($variant) {
            // Khi xóa mềm Variant, xóa cứng các liên kết trong bảng pivot
            // Bạn có thể cân nhắc xóa mềm các orderItems nếu OrderItem cũng có SoftDeletes
            $variant->attributeValues()->detach(); 
        });
    }
}