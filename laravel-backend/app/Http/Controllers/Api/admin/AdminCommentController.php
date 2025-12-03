<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Comment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

class AdminCommentController extends Controller
{
    /**
     * Lấy danh sách bình luận (Có Filter & Pagination)
     * GET: /api/admin/comments
     */
    public function index(Request $request)
    {
        try {
            // Eager loading user và product để tối ưu query
            $query = Comment::with(['user:id,fullName,avatar_url,email', 'product:id,name,image_url'])
                ->orderBy('created_at', 'desc');

            // 1. Lọc theo Product ID
            if ($request->filled('product_id')) {
                $query->where('product_id', $request->product_id);
            }

            // 2. Lọc theo Trạng thái (pending, approved, rejected)
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            // 3. Tìm kiếm nội dung hoặc tên người dùng
            if ($request->filled('keyword')) {
                $keyword = $request->keyword;
                $query->where(function($q) use ($keyword) {
                    $q->where('content', 'like', "%{$keyword}%")
                      ->orWhereHas('user', function($subQ) use ($keyword) {
                          $subQ->where('fullName', 'like', "%{$keyword}%");
                      });
                });
            }

            // Trả về danh sách (Có thể dùng paginate thay vì get nếu dữ liệu lớn)
            $comments = $query->get(); // Hoặc $query->paginate(20);

            return response()->json([
                'status' => true,
                'message' => 'Lấy danh sách thành công',
                'data' => $comments
            ]);

        } catch (\Exception $e) {
            Log::error('Admin Index Comment Error: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Lỗi server khi tải danh sách',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Admin trả lời bình luận hoặc tạo mới
     * POST: /api/admin/comments
     */
    public function store(Request $request)
    {
        // 1. Check quyền (Middleware auth:sanctum hoặc auth:admin nên xử lý việc này, nhưng check thêm cho chắc)
        if (!Auth::check()) {
            return response()->json(['status' => false, 'message' => 'Unauthorized'], 401);
        }

        Log::info('--- ADMIN COMMENT STORE ---', ['user_id' => Auth::id(), 'data' => $request->all()]);

        // 2. Validate
        try {
            $validated = $request->validate([
                // Lưu ý: Kiểm tra kỹ tên bảng trong DB là 'product' hay 'products'
                'product_id' => 'required|integer|exists:product,id', 
                'content'    => 'required|string|max:2000',
                'parent_id'  => 'nullable|integer|exists:comments,id',
            ], [
                'product_id.exists' => 'Sản phẩm không tồn tại trong hệ thống.',
                'content.required'  => 'Nội dung phản hồi không được để trống.',
            ]);

        } catch (ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Dữ liệu đầu vào không hợp lệ',
                'errors' => $e->errors()
            ], 422);
        }

        // 3. Save Data (Transaction)
        DB::beginTransaction();
        try {
            $comment = new Comment();
            $comment->user_id    = Auth::id();
            $comment->product_id = $validated['product_id'];
            $comment->content    = trim($validated['content']);
            
            // Admin bình luận thì auto duyệt (approved)
            $comment->status     = 'approved'; 
            
            $comment->parent_id  = $validated['parent_id'] ?? null;

            $comment->save();

            // Load quan hệ để trả về frontend hiển thị ngay lập tức
            $comment->load(['user:id,fullName,avatar_url,role', 'product:id,name']);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'Đã gửi phản hồi thành công',
                'data' => $comment
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Admin Store Comment Failed: ' . $e->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Lỗi server khi lưu bình luận',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xem chi tiết 1 bình luận
     * GET: /api/admin/comments/{id}
     */
    public function show(string $id)
    {
        try {
            $comment = Comment::with(['user', 'product', 'replies.user'])->findOrFail($id);
            return response()->json(['status' => true, 'data' => $comment]);
        } catch (ModelNotFoundException $e) {
            return response()->json(['status' => false, 'message' => 'Không tìm thấy bình luận'], 404);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Lỗi hệ thống'], 500);
        }
    }

    /**
     * Cập nhật trạng thái bình luận (Duyệt / Ẩn)
     * PUT/PATCH: /api/admin/comments/{id}
     */
    public function update(Request $request, string $id)
    {
        try {
            $comment = Comment::findOrFail($id);
            
            $validated = $request->validate([
                'status' => 'required|in:pending,approved,rejected'
            ]);
            
            $comment->status = $validated['status'];
            $comment->save();
            
            return response()->json([
                'status' => true,
                'message' => "Cập nhật trạng thái thành: " . strtoupper($validated['status']),
                'data' => $comment
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json(['status' => false, 'message' => 'Bình luận không tồn tại'], 404);
        } catch (\Exception $e) {
            Log::error('Admin Update Status Error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Lỗi cập nhật'], 500);
        }
    }

    /**
     * Xóa bình luận
     * DELETE: /api/admin/comments/{id}
     */
    public function destroy(string $id)
    {
        try {
            $comment = Comment::findOrFail($id);
            
            // Tùy chọn: Xóa cả các câu trả lời con (nếu không dùng CASCADE trong DB)
            // Comment::where('parent_id', $id)->delete();

            $comment->delete();
            
            return response()->json([
                'status' => true,
                'message' => 'Đã xóa bình luận thành công'
            ]);

        } catch (ModelNotFoundException $e) {
            return response()->json(['status' => false, 'message' => 'Không tìm thấy bình luận cần xóa'], 404);
        } catch (\Exception $e) {
            Log::error('Admin Delete Comment Error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Lỗi server khi xóa'], 500);
        }
    }
}