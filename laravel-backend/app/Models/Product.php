<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Models\Category;
use App\Models\Variant;
use App\Models\ImageProduct;
use App\Models\Comment;
use App\Models\Review;

class Product extends Model
{
    use HasFactory;
    use SoftDeletes; // Sử dụng xóa mềm

    protected $table = 'product';

    protected $fillable = [
        'name',
        'category_id',
        'thumbnail_url',
        'sold_count',
        'favorite_count',
        'review_count',
        'average_rating',
        'status',
        'description',
    ];

    // --- PHẦN QUAN TRỌNG CẦN THÊM ---
    /**
     * Hàm này sẽ chạy tự động khi các sự kiện của Model được kích hoạt.
     * Giúp đồng bộ việc xóa mềm (Soft Delete) các quan hệ con.
     */
    protected static function booted()
    {
        static::deleting(function ($product) {
            // Khi xóa mềm Product:
            
            // 1. Xóa mềm các Variants (nếu Variant cũng có SoftDeletes)
            // Hoặc xóa cứng nếu Variant không có SoftDeletes
            $product->variants()->delete(); 

            // 2. Xóa các ảnh liên quan
            $product->images()->delete();
            
            // 3. Có thể xóa thêm comment hoặc review nếu muốn
            // $product->comments()->delete();
        });
        
        // Tùy chọn: Khi khôi phục lại sản phẩm (restore)
        static::restored(function ($product) {
             $product->variants()->restore();
             $product->images()->restore(); 
             // Lưu ý: Chỉ hoạt động nếu các model con cũng dùng SoftDeletes
        });
    }
    // --------------------------------

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    public function variants()
    {
        return $this->hasMany(Variant::class, 'product_id', 'id');
    }

    public function images()
    {
        return $this->hasMany(ImageProduct::class, 'product_id', 'id');
    }

    public function comments()
    {
        return $this->hasMany(Comment::class, 'product_id', 'id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'product_id', 'id');
    }
}