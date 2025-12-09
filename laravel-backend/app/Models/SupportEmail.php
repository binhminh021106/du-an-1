<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;


class SupportEmail extends Model
{
    use HasFactory;
    use SoftDeletes;

    // Tên bảng trong database của bạn (nếu tên bảng khác support_emails thì sửa lại ở đây)
    protected $table = 'support_emails'; 

    protected $fillable = [
        'sender_name',
        'sender_email',
        'sender_avatar',
        'subject',
        'content',
        'preview',
        'status',          // 'inbox', 'sent', 'trash'
        'is_read',
        'has_attachment',
        'to_email'         // Nếu bạn gửi đi thì lưu email người nhận vào đây
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'has_attachment' => 'boolean',
        'created_at' => 'datetime',
    ];
}