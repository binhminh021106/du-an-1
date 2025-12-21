<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Wishlist;
use Illuminate\Support\Facades\Auth;

class WishlistController extends Controller
{
    /**
     * Lấy danh sách sản phẩm yêu thích của User
     */
    public function index(Request $request)
    {
        $user = $request->user();
        
        // Lấy wishlist kèm thông tin sản phẩm để hiển thị
        $wishlist = Wishlist::with('product') 
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $wishlist
        ]);
    }

    /**
     * Toggle: Thêm hoặc Xóa yêu thích
     */
    public function toggle(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:product,id'
        ]);

        $user = $request->user();
        $productId = $request->product_id;

        // Kiểm tra xem đã có trong wishlist chưa
        $exists = Wishlist::where('user_id', $user->id)
            ->where('product_id', $productId)
            ->first();

        if ($exists) {
            // Nếu có rồi -> Xóa
            $exists->delete();
            return response()->json([
                'status' => 'success',
                'message' => 'Đã xóa khỏi danh sách yêu thích',
                'action' => 'removed'
            ]);
        } else {
            // Nếu chưa -> Thêm mới
            Wishlist::create([
                'user_id' => $user->id,
                'product_id' => $productId
            ]);
            return response()->json([
                'status' => 'success',
                'message' => 'Đã thêm vào danh sách yêu thích',
                'action' => 'added'
            ]);
        }
    }
}