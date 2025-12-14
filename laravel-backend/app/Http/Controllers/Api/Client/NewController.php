<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\News;

class NewController extends Controller
{
    public function index(Request $request)
    {
        $query = $request->input('q');
        $author = $request->input('author');
        $category = $request->input('category');

        $news = News::where('status', 'published');

        if ($query) {
            $news->where(function ($q) use ($query) {
                $q->where('title', 'like', '%' . $query . '%')
                  ->orWhere('content', 'like', '%' . $query . '%');
            });
        }

        if ($author) {
            $news->where('author_name', '=', $author);
        }

        if ($category) {
            $news->where('category', '=', $category);
        }

        // Mặc định sắp xếp bài mới nhất
        $data = $news->orderBy('created_at', 'desc')
                     ->paginate(10); 

        return response()->json($data);
    }

    // [MỚI] API lấy bài viết phổ biến (Top views)
    public function popular()
    {
        // Lấy 5 bài viết có status là published, sắp xếp theo views giảm dần
        $popularNews = News::where('status', 'published')
            ->orderBy('views', 'desc')
            ->take(4)
            ->get();

        return response()->json($popularNews);
    }

    public function show($id)
    {
        $post = News::where('id', $id)
            ->where('status', 'published')
            ->firstOrFail();

        // [MỚI] Tăng lượt xem mỗi khi có người đọc bài
        $post->increment('views');

        return response()->json($post);
    }
}