<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'comments';

    /**
     * KHẮC PHỤC LỖI 500 MASS ASSIGNMENT:
     * Khai báo danh sách các cột được phép thêm dữ liệu qua hàm ::create()
     */
    protected $fillable = [
        'user_id',
        'product_id',
        'content',
        'parent_id', // <-- QUAN TRỌNG: Để lưu ID bình luận cha khi reply
        'status',    // <-- QUAN TRỌNG: Để lưu trạng thái 'pending'
    ];

    /**
     * Relationship: Lấy thông tin người bình luận
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relationship: Lấy thông tin sản phẩm
     * Lưu ý: Laravel sẽ dựa vào Model Product để tìm bảng. 
     * Vì bạn đã sửa Model Product có $table = 'product', nên hàm này sẽ chạy đúng.
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    /**
     * Relationship: Lấy danh sách các câu trả lời (con)
     */
    public function replies()
    {
        return $this->hasMany(Comment::class, 'parent_id');
    }
}