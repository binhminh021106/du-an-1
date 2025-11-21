<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

// --- THÊM 2 DÒNG NÀY ---
use App\Models\User;
use App\Models\Product;

class Review extends Model
{
    /** @use HasFactory<\Database\Factories\ReviewFactory> */
    use HasFactory;
    use SoftDeletes; // Nhớ thêm $table->softDeletes(); trong migration

    protected $table = 'reviews';

    protected $fillable = [
        'product_id',
        'user_id',
        'rating',
        'content',
        'status'
    ];

    /**
     * THÊM PHẦN NÀY: Ép kiểu dữ liệu
     */
    protected $casts = [
        'rating' => 'integer',
        'product_id' => 'integer',
        'user_id' => 'integer',
    ];

    // --- THÊM CÁC RELATIONSHIPS ---

    /**
     * Lấy thông tin user đã viết review.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }

    /**
     * Lấy thông tin sản phẩm được review.
     */
    public function product()
    {
        return $this->belongsTo(Product::class, 'product_id', 'id');
    }
}