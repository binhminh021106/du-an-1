<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\News;

class NewController extends Controller
{
    public function index()
    {
        // Lấy danh sách tin tức đã xuất bản (published)
        // author_name đã có sẵn trong bảng news nên không cần with('author')
        $data = News::where('status', 'published')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($data);
    }

    public function show($slug)
    {
        // Lấy chi tiết tin tức theo slug
        $post = News::where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail(); // Trả về lỗi 404 nếu không tìm thấy

        return response()->json($post);
    }
}