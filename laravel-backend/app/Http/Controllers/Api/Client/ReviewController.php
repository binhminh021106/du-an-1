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
        // Lấy danh sách review, kèm thông tin user và product để hiển thị nếu cần
        $reviews = Review::with(['user', 'product'])->latest()->get();
        return response()->json($reviews);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // 1. VALIDATION
        // Lưu ý: Đã sửa 'products' thành 'product' để khớp với tên bảng trong DB của bạn
        $request->validate([
            'product_id' => 'required|integer|exists:product,id', 
            'rating'     => 'required|integer|min:1|max:5',
            'content'    => 'nullable|string',
        ]);

        try {
            // 2. Lấy ID user hiện tại
            $userId = Auth::id();
            
            // Nếu $userId null (do chưa đăng nhập hoặc lỗi token), trả về lỗi 401 thay vì 500
            if (!$userId) {
                return response()->json(['message' => 'Vui lòng đăng nhập để đánh giá.'], 401);
            }

            // 3. Tạo review
            $review = Review::create([
                'user_id'    => $userId,
                'product_id' => $request->product_id,
                'rating'     => $request->rating,
                'content'    => $request->content,
                'status'     => 'pending',
            ]);

            return response()->json([
                'message' => 'Đánh giá thành công!',
                'data'    => $review
            ], 201);

        } catch (\Exception $e) {
            // Log lỗi ra file storage/logs/laravel.log để debug
            Log::error("Lỗi tạo review: " . $e->getMessage());
            
            return response()->json([
                'message' => 'Lỗi Server: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $review = Review::findOrFail($id);
        return response()->json($review);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        // Logic update (ví dụ cho phép sửa đánh giá của chính mình)
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        // Logic delete
    }
}