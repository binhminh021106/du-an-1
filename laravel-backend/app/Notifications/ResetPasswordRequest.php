<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordRequest extends Notification
{
    use Queueable;

    public $token;

    /**
     * Create a new notification instance.
     */
    public function __construct($token)
    {
        $this->token = $token;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        // 1. Xử lý URL frontend (giống logic trong AppServiceProvider cũ)
        $frontendUrl = env('FRONTEND_URL', 'http://localhost:5173');
        
        // Cắt bỏ đuôi /google-callback nếu có để lấy domain gốc
        $baseUrl = str_replace('/google-callback', '', $frontendUrl);
        
        // Tạo link đầy đủ kèm token và email
        $url = $baseUrl . '/reset-password?token=' . $this->token . '&email=' . $notifiable->getEmailForPasswordReset();

        // 2. Trả về nội dung Email tiếng Việt
        return (new MailMessage)
            ->subject('Yêu cầu đặt lại mật khẩu') // Tiêu đề Email
            ->greeting('Xin chào!') // Lời chào đầu thư
            ->line('Bạn nhận được email này vì chúng tôi đã nhận được yêu cầu đặt lại mật khẩu cho tài khoản của bạn.')
            ->action('Đặt lại mật khẩu', $url) // Nút bấm và Link
            ->line('Link đặt lại mật khẩu này sẽ hết hạn sau 60 phút.')
            ->line('Nếu bạn không yêu cầu thay đổi mật khẩu, vui lòng bỏ qua email này.')
            ->salutation('Trân trọng, ThinkHub'); // Lời chào kết thúc
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}