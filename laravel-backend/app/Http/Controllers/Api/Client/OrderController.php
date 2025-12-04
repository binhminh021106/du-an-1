<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    /**
     * Lấy danh sách đơn hàng của User đang đăng nhập
     */
    public function index(Request $request)
    {
        $userId = $request->user()->id;

        $orders = Order::where('user_id', $userId)
            // [FIX] Load thêm quan hệ 'attributeValues.attribute' để lấy tên biến thể (Màu, Size...)
            // Cấu trúc: Order -> OrderDetails -> Variant -> AttributeValues -> Attribute
            ->with([
                'orderDetails.variant.product', 
                'orderDetails.variant.attributeValues.attribute'
            ]) 
            ->orderBy('created_at', 'desc')
            ->get();

        $formattedOrders = $orders->map(function ($order) {
            $data = $order->toArray();
            if (isset($data['order_details'])) {
                $data['items'] = $data['order_details'];
            }
            return $data;
        });

        return response()->json(['data' => $formattedOrders]);
    }

    public function store(Request $request)
    {
        return response()->json(['message' => 'Use CheckoutController for storing orders'], 405);
    }

    /**
     * Xem chi tiết 1 đơn hàng cụ thể
     */
    public function show(string $id)
    {
        $order = Order::with([
                'orderDetails.variant.product',
                'orderDetails.variant.attributeValues.attribute' // [FIX] Load sâu để hiển thị chi tiết trong Popup
            ])
            ->where('user_id', Auth::id())
            ->findOrFail($id);

        $data = $order->toArray();
        
        if (isset($data['order_details'])) {
            $data['items'] = $data['order_details'];
        }

        return response()->json(['data' => $data]);
    }

    public function update(Request $request, string $id)
    {
        $order = Order::where('user_id', Auth::id())->findOrFail($id);
        
        if ($request->status === 'cancelled' && $order->status === 'pending') {
            $order->update(['status' => 'cancelled']);
            return response()->json(['message' => 'Đã hủy đơn hàng thành công', 'data' => $order]);
        }

        return response()->json(['message' => 'Không thể cập nhật đơn hàng này'], 403);
    }

    public function destroy(string $id)
    {
        $order = Order::where('user_id', Auth::id())->findOrFail($id);
        
        if ($order->status === 'pending') {
            $order->status = 'cancelled';
            $order->save();
            return response()->json(['message' => 'Đã hủy đơn hàng']);
        }

        return response()->json(['message' => 'Không thể hủy đơn hàng đã xử lý'], 400);
    }

    /**
     * [NEW] Tính năng Mua lại (Re-purchase)
     * Copy các sản phẩm từ đơn hàng cũ vào bảng giỏ hàng (cart_items)
     */
    public function repurchase(Request $request, string $id)
    {
        // 1. Tìm đơn hàng cũ
        $order = Order::with('orderDetails')->where('user_id', Auth::id())->findOrFail($id);

        // 2. Lấy hoặc Tạo giỏ hàng cho user hiện tại
        $cart = Cart::firstOrCreate(
            ['user_id' => Auth::id()]
        );

        // 3. Duyệt qua từng sản phẩm trong đơn cũ để thêm vào giỏ
        foreach ($order->orderDetails as $detail) {
            // Kiểm tra xem biến thể này đã có trong giỏ chưa
            $existingItem = CartItem::where('cart_id', $cart->id)
                ->where('variant_id', $detail->variant_id)
                ->first();

            if ($existingItem) {
                // Nếu có rồi -> Cộng dồn số lượng
                $existingItem->increment('quantity', $detail->quantity);
            } else {
                // Nếu chưa có -> Tạo mới
                CartItem::create([
                    'cart_id' => $cart->id,
                    'variant_id' => $detail->variant_id,
                    'quantity' => $detail->quantity
                ]);
            }
        }

        return response()->json(['message' => 'Đã thêm sản phẩm vào giỏ hàng thành công']);
    }
}