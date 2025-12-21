<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SupportEmail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\ReplyMail;
use App\Mail\ContactThankYouMail;

class AdminSupportEmailController extends Controller
{
    // 1. API cho Khách hàng gửi liên hệ (PUBLIC)
    public function store(Request $request)
    {
        // [CẬP NHẬT] Validate thêm file ảnh (tối đa 5MB, định dạng ảnh)
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'content' => 'required|string',
            'attachment' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120' // Max 5MB
        ]);

        try {
            $attachmentPath = null;
            $hasAttachment = false;

            // [MỚI] Xử lý upload file
            if ($request->hasFile('attachment')) {
                // Lưu file vào thư mục storage/app/public/supports
                $path = $request->file('attachment')->store('supports', 'public');
                $attachmentPath = '/storage/' . $path; // Đường dẫn để truy cập từ frontend
                $hasAttachment = true;
            }

            // Tạo bản ghi mới vào Database
            $email = SupportEmail::create([
                'sender_name' => $request->name,
                'sender_email' => $request->email,
                'sender_avatar' => null, 
                'subject' => 'Liên hệ từ khách hàng: ' . $request->name, 
                'content' => $request->content,
                'preview' => Str::limit($request->content, 100),
                'status' => 'inbox',
                'is_read' => false,
                'has_attachment' => $hasAttachment,     // [CẬP NHẬT]
                'attachment_path' => $attachmentPath,   // [MỚI]
            ]);

            // Gửi mail cảm ơn tự động
            try {
                Mail::to($request->email)->send(new ContactThankYouMail($request->name));
            } catch (\Exception $e) {
                // Log lỗi mail
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

    // ... (Giữ nguyên các hàm index, countUnread, destroy, reply cũ của bạn) ...
    // ... Copy lại hàm reply đã sửa ở bước trước vào đây nhé ...
    public function index()
    {
        $emails = SupportEmail::orderBy('created_at', 'desc')->get();
        return response()->json(['success' => true, 'data' => $emails]);
    }

    public function countUnread()
    {
        $count = SupportEmail::where('status', 'inbox')->where('is_read', 0)->count();
        return response()->json(['success' => true, 'count' => $count]);
    }
    
    public function destroy($id)
    {
        $email = SupportEmail::find($id);
        if ($email) {
            $email->delete(); 
            return response()->json(['message' => 'Đã xóa email']);
        }
        return response()->json(['message' => 'Không tìm thấy'], 404);
    }

    public function reply(Request $request, $id)
    {
        $originEmail = SupportEmail::findOrFail($id);
        $request->validate(['content' => 'required|string']);

        try {
            $subject = 'Re: ' . $originEmail->subject;
            Mail::to($originEmail->sender_email)->send(new ReplyMail($subject, $request->content, $originEmail->sender_name));

            SupportEmail::create([
                'sender_name' => 'ThinkHub Support Team',
                'sender_email' => config('mail.from.address'),
                'to_email' => $originEmail->sender_email,
                'subject' => $subject,
                'content' => $request->content,
                'preview' => Str::limit($request->content, 100),
                'status' => 'sent',
                'is_read' => true,
                'has_attachment' => false,
            ]);

            if (!$originEmail->is_read) {
                $originEmail->update(['is_read' => true]);
            }

            return response()->json(['success' => true, 'message' => 'Đã gửi phản hồi thành công!']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Lỗi khi gửi mail: ' . $e->getMessage()], 500);
        }
    }
}