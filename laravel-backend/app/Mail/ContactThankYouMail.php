<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactThankYouMail extends Mailable
{
    use Queueable, SerializesModels;

    public $name;

    // Nhận tên khách hàng để chào hỏi cho thân thiện
    public function __construct($name)
    {
        $this->name = $name;
    }

    public function build()
    {
        return $this->subject('Cảm ơn bạn đã liên hệ với ThinkHub') // Tiêu đề mail
                    ->markdown('emails.contact_thank_you'); // Trỏ đến file giao diện
    }
}