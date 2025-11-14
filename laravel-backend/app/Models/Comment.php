<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Comment extends Model
{
    /** @use HasFactory<\Database\Factories\CommentFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $table = 'comments';

    protected $fillable = [
        'product_id',
        'user_id',
        'content',
        'parent_id', // Trả lời
        'status'
    ];

    // --- CÁC "LỆNH NỐI" (RELATIONSHIP) RẤT QUAN TRỌNG ---

    /**
     * "Nối" để biết bình luận này THUỘC VỀ User nào.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * "Nối" để biết bình luận này THUỘC VỀ Product nào.
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * "Nối" để lấy TẤT CẢ các bình luận con (trả lời) của nó.
     * (Quan hệ 1-Nhiều: Một bình luận "cha" có nhiều "replies")
     */
    public function replies()
    {
        // Nối tới chính nó (Comment::class) qua cột 'parent_id'
        return $this->hasMany(Comment::class, 'parent_id');
    }

    /**
     * "Nối" để lấy bình luận "cha" (nếu nó là bình luận trả lời).
     */
    public function parent()
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }
}