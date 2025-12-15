<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Comment;
use Illuminate\Support\Facades\Log; // Thêm Log để debug

class CommentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $comments = Comment::with('user')->get();
        return response()->json($comments);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // 1. Kiểm tra đăng nhập thủ công (phòng trường hợp Route không có middleware)
        if (!auth()->check()) {
            return response()->json([
                'success' => false,
                'message' => 'Vui lòng đăng nhập để thực hiện chức năng này.'
            ], 401); // Trả về 401 để Frontend bắt được
        }

        // 2. Validate dữ liệu
        $validated = $request->validate([
            'product_id' => 'required|exists:product,id', 
            'content' => 'required|string|max:1000',
            'parent_id' => 'nullable|exists:comments,id'
        ]);

        try {
            // 3. Tạo comment
            $comment = Comment::create([
                'product_id' => $validated['product_id'],
                'user_id' => auth()->id(), // Chắc chắn có ID vì đã check ở trên
                'content' => $validated['content'],
                'parent_id' => $validated['parent_id'] ?? null,
                'status' => 'pending'
            ]);

            // Load user info cho comment vừa tạo
            $comment->load('user');

            return response()->json([
                'success' => true,
                'message' => 'Bình luận đã được gửi và đang chờ duyệt',
                'data' => $comment
            ], 201);

        } catch (\Exception $e) {
            // Log lỗi ra file laravel.log để kiểm tra
            Log::error('Lỗi tạo bình luận: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Lỗi Server: Không thể lưu bình luận.',
                'error' => $e->getMessage() // Chỉ bật dòng này khi dev, tắt khi production
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $comment = Comment::with('user')->findOrFail($id);
        return response()->json($comment);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $comment = Comment::findOrFail($id);

        if ($comment->user_id !== auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền chỉnh sửa bình luận này'
            ], 403);
        }

        $validated = $request->validate([
            'content' => 'required|string|max:1000'
        ]);

        $comment->update([
            'content' => $validated['content'],
            'status' => 'pending'
        ]);

        $comment->load('user');

        return response()->json([
            'success' => true,
            'message' => 'Bình luận đã được cập nhật',
            'data' => $comment
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $comment = Comment::findOrFail($id);

        // Cho phép admin xóa hoặc chính chủ xóa
        // Lưu ý: Cần logic check role admin chuẩn hơn tùy vào project của bạn
        $user = auth()->user();
        $isAdmin = $user && ($user->role === 'admin' || $user->type === 'admin');

        if ($comment->user_id !== auth()->id() && !$isAdmin) {
            return response()->json([
                'success' => false,
                'message' => 'Bạn không có quyền xóa bình luận này'
            ], 403);
        }

        $comment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Bình luận đã được xóa'
        ]);
    }

    public function getByProduct($productId)
    {
        $comments = Comment::with('user')
            ->where('product_id', $productId)
            // ->where('status', 'approved') // Tạm comment lại để test hiển thị ngay
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($comments);
    }
}