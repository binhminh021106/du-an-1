<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SupportEmail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail; // Import Mail Facade
use App\Mail\ReplyMail;              // Import Mail trả lời (Admin gửi khách)
use App\Mail\ContactThankYouMail;    // Import Mail cảm ơn (Auto gửi khi khách liên hệ)

class AdminSupportEmailController extends Controller
{
    // 1. API cho Khách hàng gửi liên hệ (PUBLIC)
    public function store(Request $request)
    {
        // Validate dữ liệu đầu vào
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'content' => 'required|string',
        ]);

        try {
            // Tạo bản ghi mới vào Database
            $email = SupportEmail::create([
                'sender_name' => $request->name,
                'sender_email' => $request->email,
                'sender_avatar' => null, 
                'subject' => 'Liên hệ từ khách hàng: ' . $request->name, 
                'content' => $request->content,
                'preview' => Str::limit($request->content, 100), // Tạo preview ngắn
                'status' => 'inbox',
                'is_read' => false,
                'has_attachment' => false,
            ]);

            // [NEW] Gửi mail cảm ơn tự động (Auto-reply)
            // Bọc try-catch để nếu lỗi mail thì vẫn lưu được contact
            try {
                Mail::to($request->email)->send(new ContactThankYouMail($request->name));
            } catch (\Exception $e) {
                // Có thể log lỗi ở đây nếu cần: \Log::error($e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Gửi liên hệ thành công!',
                'data' => $email
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi hệ thống: ' . $e->getMessage()
            ], 500);
        }
    }

    // 2. API cho Admin lấy danh sách (PROTECTED)
    public function index()
    {
        // Lấy tất cả email, sắp xếp mới nhất trước
        $emails = SupportEmail::orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $emails
        ]);
    }

    // 3. API Đếm số lượng thư chưa đọc (Hiển thị Badge đỏ trên Menu)
    public function countUnread()
    {
        $count = SupportEmail::where('status', 'inbox')
                             ->where('is_read', 0)
                             ->count();

        return response()->json([
            'success' => true,
            'count' => $count
        ]);
    }
    
    // 4. API xóa email (vào thùng rác hoặc xóa vĩnh viễn tùy logic model)
    public function destroy($id)
    {
        $email = SupportEmail::find($id);
        if ($email) {
            $email->delete(); 
            return response()->json(['message' => 'Đã xóa email']);
        }
        return response()->json(['message' => 'Không tìm thấy'], 404);
    }

    // 5. API Gửi phản hồi (Reply) từ Admin
    public function reply(Request $request, $id)
    {
        $originEmail = SupportEmail::findOrFail($id);

        $request->validate([
            'content' => 'required|string',
        ]);

        try {
            // Gửi Email phản hồi thật qua SMTP
            $subject = 'Re: ' . $originEmail->subject;
            Mail::to($originEmail->sender_email)->send(new ReplyMail($subject, $request->content));

            // Lưu bản ghi vào database (Để hiện trong tab "Đã gửi")
            SupportEmail::create([
                'sender_name' => 'ThinkHub Support Team',
                'sender_email' => config('mail.from.address'),
                'to_email' => $originEmail->sender_email,
                'subject' => $subject,
                'content' => $request->content,
                'preview' => Str::limit($request->content, 100),
                'status' => 'sent', // Đánh dấu là đã gửi
                'is_read' => true,
                'has_attachment' => false,
            ]);

            // Cập nhật trạng thái email gốc thành "đã đọc"
            if (!$originEmail->is_read) {
                $originEmail->update(['is_read' => true]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Đã gửi phản hồi thành công!'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Lỗi khi gửi mail: ' . $e->getMessage()
            ], 500);
        }
    }
}