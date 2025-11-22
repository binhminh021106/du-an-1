<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
// Tùy chọn: nếu bạn muốn sử dụng SoftDeletes cho bảng này
// use Illuminate\Database\Eloquent\SoftDeletes;

class Attribute extends Model
{
    use HasFactory;
    // use SoftDeletes; 

    protected $table = 'attributes';

    protected $fillable = [
        'name',
    ];
    
    /**
     * Một Attribute có nhiều AttributeValue (ví dụ: Màu sắc có Đỏ, Xanh)
     */
    public function values()
    {
        return $this->hasMany(AttributeValue::class, 'attribute_id', 'id');
    }
}