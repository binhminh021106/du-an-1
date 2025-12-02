<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\Variant;
use Illuminate\Support\Facades\DB;

class CartController extends Controller
{
    // Thêm sản phẩm vào giỏ (DB)
    public function addToCart(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:product,id',
            'variant_id' => 'required|exists:variants,id',
            'quantity' => 'required|integer|min:1'
        ]);

        $user = $request->user(); // Lấy user đang đăng nhập
        
        try {
            DB::beginTransaction();

            // 1. Tìm hoặc tạo giỏ hàng cho User
            $cart = Cart::firstOrCreate(
                ['user_id' => $user->id]
            );

            // 2. Kiểm tra xem sản phẩm biến thể này đã có trong giỏ chưa
            $cartItem = CartItem::where('cart_id', $cart->id)
                ->where('variant_id', $request->variant_id)
                ->first();

            if ($cartItem) {
                // Nếu có rồi -> Cộng dồn số lượng
                $cartItem->quantity += $request->quantity;
                $cartItem->save();
            } else {
                // Nếu chưa -> Tạo mới
                CartItem::create([
                    'cart_id' => $cart->id,
                    'variant_id' => $request->variant_id,
                    'quantity' => $request->quantity
                ]);
            }

            // 3. (Tuỳ chọn) Trả về danh sách giỏ hàng mới nhất để Frontend cập nhật
            // Cần load quan hệ để hiển thị tên, ảnh, giá...
            $updatedItems = $this->getCartItems($cart->id);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Đã thêm vào giỏ hàng',
                'cart_items' => $updatedItems
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    // Hàm phụ trợ để lấy danh sách item đầy đủ thông tin
    private function getCartItems($cartId)
    {
        // Join các bảng để lấy thông tin sản phẩm
        return CartItem::with(['variant.product', 'variant.attributeValues.attribute'])
            ->where('cart_id', $cartId)
            ->get()
            ->map(function($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->variant->product_id,
                    'product_name' => $item->variant->product->name,
                    'variant_id' => $item->variant_id,
                    'price' => $item->variant->price,
                    'quantity' => $item->quantity,
                    'image' => $item->variant->product->thumbnail_url, // Hoặc logic lấy ảnh variant
                    // Format attributes để hiển thị (VD: Màu: Đỏ, Size: M)
                    'attributes' => $item->variant->attributeValues->map(function($av) {
                        return $av->attribute->name . ': ' . $av->value;
                    })->implode(', ')
                ];
            });
    }
}