<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class Slides extends Model
{
    /** @use HasFactory<\Database\Factories\SlidesFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $table = 'slides';

    protected $fillable = [
        'title', // Tiêu đề
        'image_url', // Banner
        'link_url', // Địa chỉ 
        'order_number', // Thứ tự
        'status', 
    ];
}
