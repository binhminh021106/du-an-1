<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Image_product extends Model
{
    /** @use HasFactory<\Database\Factories\ImageProductFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $table = 'image_product';

    protected $fillable = [
        'product_id',
        'image_url',
    ];
}
