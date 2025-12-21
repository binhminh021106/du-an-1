<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatbotSession extends Model
{
    use HasFactory;

    /**
     * Tên bảng trong database
     */
    protected $table = 'chatbot_sessions';

    /**
     * Cấu hình khóa chính (Primary Key)
     * Laravel mặc định coi PK là 'id' (int, auto-increment),
     * nên cần khai báo lại vì chúng ta dùng 'session_id' (string).
     */
    protected $primaryKey = 'session_id';
    public $incrementing = false;
    protected $keyType = 'string';

    /**
     * Các trường được phép gán giá trị (Mass Assignment)
     */
    protected $fillable = [
        'session_id',
        'user_id',
        'last_query',
        'current_context',
        'state'
    ];

    /**
     * Ép kiểu dữ liệu tự động (Attribute Casting)
     * - current_context: Tự động encode/decode JSON <-> Array
     * - created_at, updated_at: Đảm bảo là đối tượng Carbon/DateTime
     */
    protected $casts = [
        'current_context' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Giá trị mặc định cho các thuộc tính khi khởi tạo model mới
     */
    protected $attributes = [
        'state' => 'IDLE',
    ];

    /**
     * Quan hệ với bảng User (nếu user đã đăng nhập)
     * Giả sử model User nằm cùng namespace App\Models
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}