<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Comment;

class AdminCommentController extends Controller
{
    /**
     * Lấy danh sách bình luận (Kèm thông tin User và Product)
     */
    public function index()
    {
        $comments = Comment::with(['user', 'product'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($comments);
    }

    /**
     * Xem chi tiết 1 bình luận
     */
    public function show(string $id)
    {
        // Lấy hết thông tin user/product luôn cho chắc
        $comment = Comment::with(['user', 'product'])->findOrFail($id);

        return response()->json($comment);
    }

    /**
     * Cập nhật trạng thái bình luận (Duyệt / Từ chối)
     */
    public function update(Request $request, string $id)
    {
        $comment = Comment::findOrFail($id);

        // Validate trạng thái gửi lên
        $request->validate([
            'status' => 'required|in:pending,approved,rejected',
        ]);

        $comment->update(['status' => $request->status]);

        return response()->json([
            'message' => 'Cập nhật trạng thái thành công',
            'data'    => $comment
        ]);
    }

    /**
     * Xóa bình luận
     */
    public function destroy(string $id)
    {
        $comment = Comment::findOrFail($id);
        $comment->delete();

        return response()->json(['message' => 'Đã xóa bình luận thành công']);
    }
}