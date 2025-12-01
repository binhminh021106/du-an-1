<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', '*'], // Thêm '*' để chắc chắn match mọi path

    'allowed_methods' => ['*'], // Cho phép tất cả method: GET, POST, PUT, DELETE...

    // Cấu hình origins: Thêm cả frontend local của bạn vào đây
    'allowed_origins' => [
        'http://localhost:3000',
        'http://localhost:5173', // Vite default port
        'http://127.0.0.1:3000',
        'http://127.0.0.1:5173',
        env('FRONTEND_URL', 'http://localhost:3000'), // Lấy từ .env nếu có
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'], // Cho phép mọi header (Authorization, Content-Type...)

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true, // Quan trọng: Cho phép gửi cookie/token kèm request

];