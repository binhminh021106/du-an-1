<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

use App\Models\Category;
use App\Models\Brand;
use App\Models\Variant;
use App\Models\ImageProduct;
use App\Models\Comment;
use App\Models\Review;

class Product extends Model
{
    use HasFactory;
    use SoftDeletes; 

    protected $table = 'product';

    protected $fillable = [
        'name',
        'category_id',
        'brand_id',
        'thumbnail_url',
        'sold_count',
        'favorite_count',
        'review_count',
        'average_rating',
        'status',
        'description',
    ];

    protected static function booted()
    {
        static::deleting(function ($product) {
            $product->variants()->delete(); 
            $product->images()->delete();
        });
        
        static::restored(function ($product) {
             $product->variants()->restore();
             $product->images()->restore(); 
        });
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id', 'id');
    }

    public function brand()
    {
        return $this->belongsTo(Brand::class, 'brand_id', 'id');
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