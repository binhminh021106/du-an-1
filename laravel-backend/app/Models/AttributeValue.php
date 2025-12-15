<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttributeValue extends Model
{
    use HasFactory;

    protected $table = 'attribute_values';

    protected $fillable = [
        'attribute_id', 
        'value',
    ];

    public function variants()
    {
        return $this->belongsToMany(
            Variant::class, 
            'variant_attribute_values', 
            'attribute_value_id', 
            'variant_id'
        );
    }
    
    // Thêm quan hệ ngược lại với Attribute nếu cần
    public function attribute()
    {
        return $this->belongsTo(Attribute::class, 'attribute_id', 'id');
    }
}