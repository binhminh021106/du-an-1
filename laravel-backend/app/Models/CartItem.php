<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

use App\Models\Cart;
use App\Models\Variant;

class CartItem extends Model
{
    /** @use HasFactory<\Database\Factories\CartItemFactory> */
    use HasFactory;

    protected $table = 'cart_items';

    protected $fillable = [
        'cart_id',
        'variant_id',
        'quantity',
    ];

    public function cart()
    {
        return $this->belongsTo(Cart::class, 'cart_id', 'id');
    }
    // Item này là của biến thể nào
    public function variant()
    {
        return $this->belongsTo(Variant::class, 'variant_id', 'id');
    }
}
