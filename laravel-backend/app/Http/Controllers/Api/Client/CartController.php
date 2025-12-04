<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Variant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CartController extends Controller
{
    /**
     * [GET] /api/carts
     * Lấy danh sách sản phẩm trong giỏ hàng
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) {
                return response()->json(['data' => []], 200);
            }

            $cart = Cart::firstOrCreate(['user_id' => $user->id]);
            $cartItems = $this->getCartItems($cart->id);

            return response()->json([
                'status' => 'success',
                'data' => $cartItems
            ], 200);

        } catch (\Exception $e) {
            Log::error("Lỗi lấy giỏ hàng: " . $e->getMessage());
            return response()->json(['message' => 'Lỗi server'], 500);
        }
    }

    /**
     * [POST] /api/cart/add
     * Thêm sản phẩm vào giỏ
     */
    public function addToCart(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:product,id', // [FIX] Sửa lại thành 'product' (số ít) khớp với DB cũ
            'variant_id' => 'nullable|exists:variants,id',
            'quantity'   => 'required|integer|min:1'
        ]);

        $user = $request->user();
        
        try {
            DB::beginTransaction();

            // 1. Tìm hoặc tạo giỏ hàng
            $cart = Cart::firstOrCreate(['user_id' => $user->id]);

            // 2. Xác định Variant ID
            $variantId = $request->variant_id;
            
            // Nếu không có variant_id, thử tìm variant mặc định
            if (!$variantId) {
                $defaultVariant = Variant::where('product_id', $request->product_id)->first();
                if ($defaultVariant) $variantId = $defaultVariant->id;
            }

            if (!$variantId) {
                return response()->json(['message' => 'Sản phẩm không hợp lệ (thiếu biến thể)'], 400);
            }

            // 3. Kiểm tra item đã có chưa
            $cartItem = CartItem::where('cart_id', $cart->id)
                ->where('variant_id', $variantId)
                ->first();

            if ($cartItem) {
                $cartItem->quantity += $request->quantity;
                $cartItem->save();
            } else {
                // [FIX] Bỏ cột 'product_id' vì bảng cart_items thường chỉ liên kết qua variant_id
                CartItem::create([
                    'cart_id'    => $cart->id,
                    'variant_id' => $variantId,
                    'quantity'   => $request->quantity
                ]);
            }

            DB::commit();

            // Trả về dữ liệu giỏ hàng mới nhất
            return response()->json([
                'status' => 'success',
                'message' => 'Đã thêm vào giỏ hàng',
                'data' => $this->getCartItems($cart->id)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Lỗi thêm giỏ hàng: " . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * [PUT] /api/cart/{id}
     * Cập nhật số lượng item
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1'
        ]);

        try {
            $user = $request->user();
            // Tìm item thuộc về user đó (thông qua cart) để bảo mật
            $cart = Cart::where('user_id', $user->id)->first();
            
            if (!$cart) return response()->json(['message' => 'Giỏ hàng không tồn tại'], 404);

            // $id ở đây là id của bảng cart_items
            $cartItem = CartItem::where('cart_id', $cart->id)->where('id', $id)->first();

            if ($cartItem) {
                $cartItem->quantity = $request->quantity;
                $cartItem->save();
                return response()->json([
                    'status' => 'success',
                    'message' => 'Cập nhật thành công',
                    'data' => $this->getCartItems($cart->id)
                ]);
            }

            return response()->json(['message' => 'Sản phẩm không tìm thấy'], 404);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi cập nhật'], 500);
        }
    }

    /**
     * [DELETE] /api/cart/{id}
     * Xóa sản phẩm khỏi giỏ
     */
    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();
            $cart = Cart::where('user_id', $user->id)->first();
            
            if (!$cart) return response()->json(['message' => 'Giỏ hàng trống'], 404);

            $deleted = CartItem::where('cart_id', $cart->id)->where('id', $id)->delete();

            if ($deleted) {
                return response()->json([
                    'status' => 'success',
                    'message' => 'Đã xóa sản phẩm',
                    'data' => $this->getCartItems($cart->id)
                ]);
            }

            return response()->json(['message' => 'Không tìm thấy sản phẩm để xóa'], 404);

        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi xóa sản phẩm'], 500);
        }
    }

    // --- HELPER FUNCTION ---
    private function getCartItems($cartId)
    {
        // Eager loading: variant -> product, variant -> attributes
        $items = CartItem::with(['variant.product', 'variant.attributeValues.attribute'])
            ->where('cart_id', $cartId)
            ->get();

        // Format lại dữ liệu cho khớp với Store.js Frontend
        return $items->map(function($item) {
            $product = $item->variant->product ?? null;
            if (!$product) return null; // Bỏ qua nếu sản phẩm gốc bị xóa

            return [
                'id'           => $item->id, // ID của Cart Item (dùng để xóa/sửa)
                'product_id'   => $product->id,
                'name'         => $product->name, // Store.js cần key 'name'
                'variant_id'   => $item->variant_id,
                'price'        => $item->variant->price,
                'quantity'     => $item->quantity, // Store.js map quantity -> qty
                'qty'          => $item->quantity, // Thêm key này cho chắc ăn
                'stock'        => $item->variant->stock ?? 100,
                'image_url'    => $product->thumbnail_url ?? $product->image_url ?? '',
                'variant_name' => $this->formatVariantName($item->variant),
                'category_id'  => $product->category_id
            ];
        })->filter(); // Loại bỏ các giá trị null
    }

    // Format tên biến thể (Ví dụ: Màu: Đỏ - Size: L)
    private function formatVariantName($variant)
    {
        if (!$variant || $variant->attributeValues->isEmpty()) {
            return 'Mặc định';
        }
        return $variant->attributeValues->map(function($av) {
            return $av->attribute->name . ': ' . $av->value;
        })->implode(' - ');
    }
}