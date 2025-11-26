<?php

namespace App\Http\Controllers\Api\Client; // <--- CHỮ C VIẾT HOA

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\News;

class NewController extends Controller
{
    public function index()
    {
        // Lấy tin tức published, mới nhất lên đầu
        $data = News::with('author:id,fullName,avatar_url')
            ->where('status', 'published')
            ->orderBy('created_at', 'desc')
            ->get();

        // Trả về đúng format mà Frontend đang chờ
        return response()->json($data); 
    }

    public function show($slug)
    {
        $post = News::with('author:id,fullName,avatar_url')
            ->where('slug', $slug)
            ->where('status', 'published')
            ->firstOrFail();

        return response()->json($post);
    }
}