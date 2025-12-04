<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Sanctum\HasApiTokens; 
use Illuminate\Notifications\Notifiable;
use App\Notifications\ResetPasswordRequest;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;
    use SoftDeletes;

    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'fullName',
        'email',
        'phone',
        'sex',
        'password',
        'avatar_url',
        'status',
        'email_verified_at',
        'remember_token',
        'google_id'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // Một user có nhiều đơn hàng
    public function orders()
    {
        return $this->hasMany(Order::class, 'user_id', 'id');
    }
    // Một user có nhiều review
    public function reviews()
    {
        return $this->hasMany(Review::class, 'user_id', 'id');
    }
    // Một user có nhiều comment
    public function comments()
    {
        return $this->hasMany(Comment::class, 'user_id', 'id');
    }
    // Một user có một giỏ hàng
    public function cart()
    {
        return $this->hasOne(Cart::class, 'user_id', 'id');
    }
    // Một user có nhiều địa chỉ
    public function addresses()
    {
        return $this->hasMany(UserAddress::class, 'user_id', 'id');
    }
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id');
    }
     public function sendPasswordResetNotification($token)
    {
        // Gọi class Notification tiếng Việt của chúng ta
        $this->notify(new ResetPasswordRequest($token));
    }
}
