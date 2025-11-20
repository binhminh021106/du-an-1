<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class BrandSlide extends Model
{
    /** @use HasFactory<\Database\Factories\SlidesFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $table = 'brand_slides';

    protected $fillable = [
        'name',
        'image_url', 
        'link_url', 
        'order_number',
        'status', 
    ];
}
