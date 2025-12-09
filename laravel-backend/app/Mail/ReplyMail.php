<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public $content;
    public $subjectText;

    public function __construct($subject, $content)
    {
        $this->subjectText = $subject;
        $this->content = $content;
    }

    public function build()
    {
        // SỬA LẠI: Đổi thành 'emails.reply' để khớp với folder 'resources/views/emails' của m
        return $this->subject($this->subjectText)
                    ->markdown('emails.reply'); 
    }
}