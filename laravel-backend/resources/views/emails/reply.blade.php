<x-mail::message>
# Xin chào {{ $customerName }},

Cảm ơn bạn đã liên hệ với **ThinkHub**. Chúng tôi đã nhận được yêu cầu hỗ trợ của bạn.

Dưới đây là phản hồi từ đội ngũ hỗ trợ kỹ thuật của chúng tôi:

---

<x-mail::panel>
{{ $content }}
</x-mail::panel>

---

Nếu bạn cần hỗ trợ thêm, vui lòng trả lời trực tiếp email này hoặc liên hệ hotline **0909 123 456**.

<x-mail::button :url="config('app.url')" color="success">
Truy cập Website
</x-mail::button>

Trân trọng,<br>
**{{ config('app.name') }} Support Team**
</x-mail::message>