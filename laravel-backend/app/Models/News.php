<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
// use App\Models\User; // Không cần import nữa vì đã bỏ quan hệ

class News extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'news';

    // Chỉ cho phép fill các trường dữ liệu, KHÔNG bao gồm timestamp
    protected $fillable = [
        'title',
        'category',
        'excerpt', 
        'content', 
        'image_url',
        'slug', 
        'status',
        'views',
        'author_name',
        'meta_title',
        'meta_description',
        'meta_keywords',
    ];

    /**
     * Relationship: Đã vô hiệu hóa vì chuyển sang dùng author_name trực tiếp
     * Giữ lại code comment để tham khảo nếu sau này cần tracking ngược lại user thật
     */
    // public function author()
    // {
    //     return $this->belongsTo(User::class, 'author_id', 'id');
    // }
}