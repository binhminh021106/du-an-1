<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\News;
use Illuminate\Validation\Rule;

class AdminNewController extends Controller
{
    /**
     * Lấy danh sách tin tức
     * GET /api/news
     */
    public function index(Request $request)
    {
        // 1. Khởi tạo query
        $query = News::query();

        // 2. Eager load 'author' để lấy thông tin người đăng (tránh N+1 query)
        $query->with('author:id,fullName,avatar_url'); 

        // 3. Xử lý sắp xếp (Frontend gửi _sort và _order)
        if ($request->has('_sort') && $request->has('_order')) {
            $sortField = $request->_sort;
            $sortOrder = $request->_order;
            $query->orderBy($sortField, $sortOrder);
        } else {
            $query->orderBy('id', 'desc');
        }

        $news = $query->get();

        return response()->json($news);
    }

    /**
     * Tạo tin tức mới
     * POST /api/news
     */
    public function store(Request $request)
    {
        // 1. Validate dữ liệu đầu vào
        $validated = $request->validate([
            'title'     => 'required|string|max:255',
            'slug'      => 'required|string|unique:news,slug', 
            'excerpt'   => 'nullable|string',
            'content'   => 'required|string',
            'image_url' => 'nullable|url', 
            'author_id' => 'required|exists:users,id', 
            'status'    => 'required|in:published,draft,pending',
        ], [
            'slug.unique' => 'Đường dẫn này đã tồn tại, vui lòng chọn tiêu đề khác.',
            'author_id.exists' => 'Tác giả không hợp lệ.'
        ]);

        // 2. Tạo mới
        $news = News::create($validated);

        return response()->json($news, 201);
    }

    /**
     * Xem chi tiết 1 tin tức
     * GET /api/news/{id}
     */
    public function show(string $id)
    {
        $news = News::with('author:id,fullName,avatar_url')->findOrFail($id);
        return response()->json($news);
    }

    /**
     * Cập nhật tin tức (Dùng cho cả Edit full và Toggle Status)
     * PUT/PATCH /api/news/{id}
     */
    public function update(Request $request, string $id)
    {
        $news = News::findOrFail($id);

        // 1. Validate dữ liệu
        $validated = $request->validate([
            'title'     => 'sometimes|required|string|max:255',
            'slug'      => ['sometimes', 'required', 'string', Rule::unique('news')->ignore($news->id)],
            'excerpt'   => 'nullable|string',
            'content'   => 'sometimes|required|string',
            'image_url' => 'nullable|url',
            'author_id' => 'sometimes|required|exists:users,id',
            'status'    => 'sometimes|required|in:published,draft,pending',
            'updated_at'=> 'nullable'
        ]);

        // 2. Update
        $news->update($validated);

        return response()->json($news);
    }

    /**
     * Xóa tin tức (Soft Delete vì Model có use SoftDeletes)
     * DELETE /api/news/{id}
     */
    public function destroy(string $id)
    {
        $news = News::findOrFail($id);
        $news->delete();

        return response()->json(['message' => 'Xóa tin tức thành công']);
    }
}