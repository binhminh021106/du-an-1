<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Review;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ReviewController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $reviews = Review::with(['user', 'product'])->latest()->get();
        return response()->json($reviews);
    }

    /**
     * Store a newly created resource in storage.
     * HOẶC Update nếu đã tồn tại.
     */
    public function store(Request $request)
    {
        // 1. VALIDATION
        $request->validate([
            'product_id' => 'required|integer|exists:product,id',
            // Bỏ validation order_id vì Model Review không có cột này
            'rating'     => 'required|integer|min:1|max:5',
            'content'    => 'nullable|string',
        ]);

        try {
            // 2. Lấy ID user hiện tại
            $userId = Auth::id();
            
            if (!$userId) {
                return response()->json(['message' => 'Vui lòng đăng nhập để đánh giá.'], 401);
            }

            // 3. XÁC ĐỊNH ĐIỀU KIỆN TÌM KIẾM
            // Vì bảng reviews không có order_id, ta xác định duy nhất bằng User + Product
            $matchConditions = [
                'user_id'    => $userId,
                'product_id' => $request->product_id,
            ];

            // 4. UPDATE HOẶC CREATE
            // Tìm review cũ của user cho sản phẩm này -> Update nội dung & Reset status
            // Nếu chưa có -> Tạo mới
            $review = Review::updateOrCreate(
                $matchConditions,
                [
                    'rating'  => $request->rating,
                    'content' => $request->content,
                    'status'  => 'pending', // [QUAN TRỌNG] Reset trạng thái về pending để Admin duyệt lại
                ]
            );

            $message = $review->wasRecentlyCreated 
                ? 'Đánh giá thành công!' 
                : 'Cập nhật đánh giá thành công! Đang chờ duyệt lại.';

            return response()->json([
                'message' => $message,
                'data'    => $review
            ], 200);

        } catch (\Exception $e) {
            Log::error("Lỗi ReviewController@store: " . $e->getMessage());
            
            return response()->json([
                'message' => 'Lỗi Server: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id)
    {
        $review = Review::findOrFail($id);
        return response()->json($review);
    }

    public function update(Request $request, string $id)
    {
        // Update logic if needed
    }

    public function destroy(string $id)
    {
        // Delete logic if needed
    }
}