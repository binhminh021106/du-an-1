<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Product; // Nhớ import Model Product

class Brand extends Model
{
    use HasFactory;
    use SoftDeletes; // Chuẩn, vì bảng brands bạn tạo có cột deleted_at

    protected $table = 'brands';

    protected $fillable = [
        'name',
        'slug', 
        'description',
        'logo_url',
        'order_number',
        'status',
    ];

    /**
     * Ép kiểu dữ liệu
     */
    protected function casts(): array
    {
        return [
            'order_number' => 'integer',
        ];
    }

    /**
     * Lấy danh sách sản phẩm thuộc thương hiệu này.
     * Quan hệ 1-Nhiều (1 Brand có nhiều Product)
     */
    public function products()
    {
        // Tham số thứ 2 là khóa ngoại trên bảng 'product' (brand_id)
        // Tham số thứ 3 là khóa chính trên bảng 'brands' (id)
        return $this->hasMany(Product::class, 'brand_id', 'id');
    }
}