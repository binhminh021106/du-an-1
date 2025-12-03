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
    // Cấu hình URL gốc của server (PHẢI KHỚP VỚI CẤU HÌNH TRONG VUE)
    // Nếu bạn sử dụng /storage, hãy sửa lại đường dẫn trong getImageUrl
    const SERVER_URL = 'http://127.0.0.1:8000'; 
    const FALLBACK_IMAGE_URL = 'https://placehold.co/100x100?text=No+Img';
    
    // Hàm tiện ích để tạo URL ảnh đầy đủ
    private function getImageUrl($path)
    {
        if (!$path) return self::FALLBACK_IMAGE_URL;
        // Nếu path đã là URL (http/https/data:), trả về ngay
        if (str_starts_with($path, 'http') || str_starts_with($path, 'data:')) return $path;

        // Bỏ ký tự '/' ở đầu path nếu có
        $cleanPath = str_starts_with($path, '/') ? substr($path, 1) : $path;
        
        // Giả định ảnh được public trực tiếp từ thư mục gốc Laravel (hoặc /public)
        return self::SERVER_URL . '/' . $cleanPath;
    }

    // Thêm sản phẩm vào giỏ (DB)
    public function addToCart(Request $request)
    {
        // === SỬA ĐỔI 1: SỬA TÊN BẢNG TRONG VALIDATION ===
        // Đã sửa 'products' thành 'product' (số ít) để khớp với database của bạn
        $request->validate([
            'product_id' => 'required|exists:product,id',
            'variant_id' => 'required|exists:variants,id',
            'quantity' => 'required|integer|min:1'
        ]);

        // QUAN TRỌNG: Kiểm tra user (nếu người dùng chưa đăng nhập, dòng này sẽ bị lỗi)
        $user = $request->user(); 
        if (!$user) {
             return response()->json(['status' => 'error', 'message' => 'Người dùng chưa đăng nhập.'], 401);
        }
        
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

            // 3. Trả về danh sách giỏ hàng mới nhất
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
        return CartItem::with([
            'variant.product', 
            'variant.attributeValues.attribute'
        ])
            ->where('cart_id', $cartId)
            ->get()
            ->map(function($item) {
                $variant = $item->variant;
                $product = $variant ? $variant->product : null;

                // Xử lý an toàn: Nếu không tìm thấy product/variant, dùng giá trị mặc định
                return [
                    'id' => $item->id,
                    'cartId' => $item->id, 
                    'product_id' => $product ? $product->id : null,
                    'name' => $product ? $product->name : 'Sản phẩm đã bị xóa', 
                    'variant_id' => $item->variant_id,
                    'price' => $variant ? $variant->price : 0,
                    'qty' => $item->quantity, 
                    
                    // === SỬA ĐỔI 2: CHUYỂN PATH ẢNH THÀNH FULL URL ===
                    'thumbnail_url' => $product 
                        ? $this->getImageUrl($product->thumbnail_url) // <-- Áp dụng hàm getImageUrl
                        : self::FALLBACK_IMAGE_URL, 
                    // === KẾT THÚC SỬA ĐỔI ===
                    
                    'stock' => $variant ? $variant->stock : 0, 

                    // Format attributes (Sử dụng imploding an toàn hơn)
                    'variantName' => $variant && $variant->attributeValues->isNotEmpty()
                        ? $variant->attributeValues->map(fn($av) => ($av->attribute->name ?? '') . ': ' . ($av->value ?? ''))
                                                    ->implode(' - ')
                        : ($variant ? ($variant->name ?? 'Mặc định') : 'Mặc định'),
                ];
            });
    }
}