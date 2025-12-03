<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\News;

class NewController extends Controller
{
    // FIX: Nhận Request để đọc tham số tìm kiếm (q, author)
    public function index(Request $request)
    {
        $query = $request->input('q');
        $author = $request->input('author');

        // 1. Khởi tạo truy vấn và lọc theo trạng thái 'published'
        $news = News::where('status', 'published');

        // 2. Xử lý tìm kiếm theo từ khóa (q)
        if ($query) {
            // Lọc theo title HOẶC content (sử dụng closure để nhóm điều kiện WHERE)
            $news->where(function ($q) use ($query) {
                $q->where('title', 'like', '%' . $query . '%')
                  ->orWhere('content', 'like', '%' . $query . '%');
            });
        }

        // 3. Xử lý lọc theo tác giả (author)
        if ($author) {
            $news->where('author_name', '=', $author);
        }

        // 4. BƯỚC QUAN TRỌNG: Sắp xếp theo ngày tạo MỚI NHẤT
        // Đảm bảo kết quả liên quan/mới nhất nằm ở vị trí đầu [0]
        $data = $news->orderBy('created_at', 'desc')
                     ->paginate(10); // Dùng paginate(10) hoặc get() tùy nhu cầu Frontend

        return response()->json($data);
    }

    // Phương thức show($id) giữ nguyên (tìm kiếm theo ID)
    public function show($id)
    {
        // Tìm bài viết theo ID và đảm bảo status là published
        $post = News::where('id', $id)
            ->where('status', 'published')
            ->firstOrFail(); // Trả về lỗi 404 nếu không tìm thấy ID này

        return response()->json($post);
    }
}