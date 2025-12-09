<x-mail::message>
# Xin chào,

Cảm ơn bạn đã liên hệ với **{{ config('app.name') }}**.

Về vấn đề của bạn, đội ngũ hỗ trợ xin phản hồi như sau:

<x-mail::panel>
{!! nl2br(e($content)) !!}
</x-mail::panel>

Nếu bạn cần hỗ trợ thêm, đừng ngần ngại trả lời email này nhé!

<x-mail::button :url="env('FRONTEND_URL')">
Truy cập Website
</x-mail::button>

Trân trọng,<br>
**Đội ngũ {{ config('app.name') }}**
</x-mail::message>