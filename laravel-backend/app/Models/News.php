<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User; 

class News extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'news';

    // Chỉ cho phép fill các trường dữ liệu, KHÔNG bao gồm timestamp
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
     * Relationship: Một tin tức thuộc về một tác giả (User)
     */
    public function author()
    {
        return $this->belongsTo(User::class, 'author_id', 'id');
    }
}