<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Review;
use Illuminate\Support\Facades\Log;

class AdminReviewController extends Controller
{
    /**
     * Lấy danh sách đánh giá
     */
    public function index()
    {
        try {
            $reviews = Review::with(['user:id,fullName,avatar_url', 'product:id,name'])
                ->with(['product.images'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($reviews);
        } catch (\Exception $e) {
            Log::error("Lỗi lấy Reviews: " . $e->getMessage());
            return response()->json(['message' => 'Lỗi server', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Xem chi tiết 1 đánh giá
     */
    public function show(string $id)
    {
        $review = Review::with(['user', 'product.images'])->findOrFail($id);
        return response()->json($review);
    }

    /**
     * Cập nhật trạng thái (Duyệt / Từ chối)
     */
    public function update(Request $request, string $id)
    {
        $review = Review::findOrFail($id);

        $request->validate([
            'status' => 'required|in:pending,approved,rejected',
        ]);

        $review->update(['status' => $request->status]);

        return response()->json([
            'message' => 'Cập nhật trạng thái đánh giá thành công',
            'data'    => $review
        ]);
    }

    /**
     * Xóa đánh giá
     */
    public function destroy(string $id)
    {
        try {
            $review = Review::findOrFail($id);
            $review->delete();
            return response()->json(['message' => 'Đã xóa đánh giá thành công']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi xóa review', 'error' => $e->getMessage()], 500);
        }
    }
}