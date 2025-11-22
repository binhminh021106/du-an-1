<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
// use Illuminate\Database\Eloquent\SoftDeletes;

class AttributeValue extends Model
{
    use HasFactory;
    // use SoftDeletes;

    protected $table = 'attribute_values';

    protected $fillable = [
        'attribute_id',
        'value',
    ];

    /**
     * Một AttributeValue thuộc về một Attribute (ví dụ: Đỏ thuộc về Màu sắc)
     */
    public function attribute()
    {
        return $this->belongsTo(Attribute::class, 'attribute_id', 'id');
    }

    /**
     * Một AttributeValue có thể được liên kết với nhiều Variant.
     * Sử dụng quan hệ many-to-many thông qua bảng pivot 'variant_attribute_values'.
     */
    public function variants()
    {
        return $this->belongsToMany(
            Variant::class, 
            'variant_attribute_values', 
            'attribute_value_id', 
            'variant_id'
        )->withTimestamps();
    }
}