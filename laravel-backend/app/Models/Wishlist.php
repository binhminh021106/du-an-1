<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wishlist extends Model
{
    use HasFactory;

    protected $table = 'wishlists';

    protected $fillable = [
        'user_id',
        'product_id',
    ];

    /**
     * Relationship: Wishlist thuộc về User nào
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Relationship: Wishlist chứa Product nào
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}