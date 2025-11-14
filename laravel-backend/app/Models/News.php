<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
// Nhớ use cả model User
use App\Models\User; 

class News extends Model
{
    /** @use HasFactory<\Database\Factories\NewsFactory> */ // <-- Đã sửa
    use HasFactory;
    use SoftDeletes;

    protected $table = 'news';

    protected $fillable = [
        'title',
        'excerpt', 
        'content', 
        'image_url',
        'slug', 
        'author_id', 
        'status'
    ];

    /**
     * Lấy thông tin tác giả (author) của bài viết.
     */
    public function author()
    {
        // Giả sử cột 'author_id' liên kết với 'id' trên bảng 'users'
        return $this->belongsTo(User::class, 'author_id', 'id');
    }
}