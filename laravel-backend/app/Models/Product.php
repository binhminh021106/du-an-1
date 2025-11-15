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
    use SoftDeletes;

    /**
     * Tên bảng mà Model này quản lý.
     * * Ghi chú: Dòng này KHÔNG bắt buộc nếu tên bảng của bạn
     * là 'products'. Laravel tự đoán được.
     * Mình thêm vào để bạn biết cách làm nếu lỡ đặt tên bảng khác (ví dụ: 'san_pham').
     */
    protected $table = 'product';


    /**
     * Khai báo CÁC CỘT (column) MÀ BẠN CHO PHÉP
     * được "thêm" hoặc "sửa" hàng loạt từ bên ngoài (ví dụ: từ API).
     *
     * Đây là tính năng bảo mật Mass Assignment của Laravel.
     */
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

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    /**
     * Lấy tất cả các biến thể của sản phẩm.
     */
    public function variants()
    {
        return $this->hasMany(Variant::class, 'product_id', 'id');
    }

    /**
     * Lấy tất cả hình ảnh của sản phẩm.
     */
    public function images()
    {
        return $this->hasMany(ImageProduct::class, 'product_id', 'id');
    }

    /**
     * Lấy tất cả bình luận của sản phẩm.
     */
    public function comments()
    {
        return $this->hasMany(Comment::class, 'product_id', 'id');
    }

    /**
     * Lấy tất cả đánh giá của sản phẩm.
     */
    public function reviews()
    {
        return $this->hasMany(Review::class, 'product_id', 'id');
    }
}
