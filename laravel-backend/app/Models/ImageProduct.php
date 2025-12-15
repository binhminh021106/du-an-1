<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Models\Product;

class ImageProduct extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'image_product';

    protected $fillable = [
        'product_id',
        'image_url' // Tên trường lưu trữ đường dẫn ảnh thô trong DB
    ];
    
    // Tùy chọn: Ẩn các trường không cần thiết khi trả về JSON
    protected $hidden = [
        'product_id', 
        'created_at', 
        'updated_at',
        'deleted_at'
    ]; // <<< ĐÃ THÊM DẤU CHẤM PHẨY

    /**
     * Định nghĩa quan hệ ngược: Một ảnh thuộc về một sản phẩm.
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}