<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WishlistController extends Controller
{
    /**
     * Lấy danh sách sản phẩm yêu thích của user hiện tại
     */
    public function index(Request $request)
    {
        $user = $request->user(); 

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Unauthenticated'
            ], 401);
        }

        $wishlistItems = Wishlist::with(['product'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

    
        
        return response()->json([
            'status' => 'success',
            'data' => $wishlistItems
        ]);
    }

    /**
     * Thêm sản phẩm vào wishlist (Toggle: Có rồi thì xóa, chưa có thì thêm)
     */
    public function toggle(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:product,id', // Kiểm tra product_id tồn tại trong bảng product
        ]);

        $user = $request->user();
        $productId = $request->product_id;

        $wishlistItem = Wishlist::where('user_id', $user->id)
            ->where('product_id', $productId)
            ->first();

        if ($wishlistItem) {
            // Nếu đã có -> Xóa
            $wishlistItem->delete();
            return response()->json([
                'status' => 'success',
                'message' => 'Removed from wishlist',
                'action' => 'removed'
            ]);
        } else {
            // Nếu chưa có -> Thêm
            Wishlist::create([
                'user_id' => $user->id,
                'product_id' => $productId
            ]);
            return response()->json([
                'status' => 'success',
                'message' => 'Added to wishlist',
                'action' => 'added'
            ]);
        }
    }

    /**
     * Xóa sản phẩm khỏi wishlist
     */
    public function destroy(Request $request, $productId)
    {
        $user = $request->user();

        $deleted = Wishlist::where('user_id', $user->id)
            ->where('product_id', $productId)
            ->delete();

        if ($deleted) {
            return response()->json([
                'status' => 'success',
                'message' => 'Product removed from wishlist'
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Product not found in wishlist'
        ], 404);
    }
}