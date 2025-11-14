<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Role extends Model
{
    /** @use HasFactory<\Database\Factories\RoleFactory> */
    use HasFactory;
    use SoftDeletes;

    protected $table = 'role';

    protected $fillable = [
        'value', // tên user hoặc admin 
        'label', // tên hiển thị
        'badgeClass'
    ];
}
