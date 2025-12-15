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
            // Load thêm quan hệ để lấy tên biến thể và ĐÁNH GIÁ của user
            ->with([
                'orderDetails.variant.product', 
                'orderDetails.variant.attributeValues.attribute',
                // Điều này giúp tránh việc lấy phải các bản ghi cũ bị rỗng/lỗi trong quá khứ
                'orderDetails.variant.product.reviews' => function($query) use ($userId) {
                    $query->where('user_id', $userId)->orderBy('id', 'desc');
                }
            ]) 
            ->orderBy('created_at', 'desc')
            ->get();

        $formattedOrders = $orders->map(function ($order) {
            $orderData = $order->toArray();
            
            // Map lại items để đưa review ra ngoài cho frontend dễ lấy
            $items = $order->orderDetails->map(function ($detail) {
                $itemData = $detail->toArray();
                
                // Lấy review từ quan hệ đã eager load ở trên
                // orderDetail -> variant -> product -> reviews (collection)
                $product = $detail->variant->product ?? null;
                
                // Lấy review đầu tiên (đã được sort desc ở query nên đây là review mới nhất)
                $userReview = ($product && $product->reviews && $product->reviews->isNotEmpty()) 
                    ? $product->reviews->first() 
                    : null;

                // Gán vào item để Vuejs sử dụng: item.review
                $itemData['review'] = $userReview;
                
                return $itemData;
            });

            $orderData['items'] = $items;
            
            // Xóa key cũ để gọn data (tuỳ chọn)
            if (isset($orderData['order_details'])) {
                unset($orderData['order_details']);
            }
            
            return $orderData;
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
        $userId = Auth::id();

        $order = Order::with([
                'orderDetails.variant.product',
                'orderDetails.variant.attributeValues.attribute',
                'orderDetails.variant.product.reviews' => function($query) use ($userId) {
                    $query->where('user_id', $userId)->orderBy('id', 'desc');
                }
            ])
            ->where('user_id', $userId)
            ->findOrFail($id);

        $orderData = $order->toArray();
        
        // Logic map items tương tự như index để lấy review
        $items = $order->orderDetails->map(function ($detail) {
            $itemData = $detail->toArray();
            
            $product = $detail->variant->product ?? null;
            $userReview = ($product && $product->reviews && $product->reviews->isNotEmpty()) 
                ? $product->reviews->first() 
                : null;
                
            $itemData['review'] = $userReview;
            return $itemData;
        });

        $orderData['items'] = $items;
        
        if (isset($orderData['order_details'])) {
            unset($orderData['order_details']);
        }

        return response()->json(['data' => $orderData]);
    }

    public function update(Request $request, string $id)
    {
        $order = Order::where('user_id', Auth::id())->findOrFail($id);
        
        // Chỉ cho phép hủy khi đang pending
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
     * Tính năng Mua lại (Re-purchase)
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

    /**
     * Tính năng Yêu cầu hoàn hàng (Return Order)
     * Chuyển trạng thái đơn hàng sang 'returning' để Admin duyệt
     */
    public function requestReturn(Request $request, string $id)
    {
        $order = Order::where('user_id', Auth::id())->findOrFail($id);

        // Kiểm tra trạng thái hợp lệ để được hoàn hàng.
        // Dựa trên yêu cầu của bạn, cho phép hoàn hàng khi status là 'completed'.
        // Thêm 'delivered' vào nếu hệ thống của bạn cho phép hoàn ngay khi vừa giao xong.
        $allowedStatuses = ['completed', 'delivered'];

        if (in_array($order->status, $allowedStatuses)) {
            // Chuyển sang trạng thái 'returning' (Đang trả hàng/Chờ duyệt hoàn hàng)
            // Trạng thái này khớp với filter returnsList bên Admin: ['returning', 'returned']
            $order->status = 'returning';
            $order->save();

            return response()->json([
                'message' => 'Đã gửi yêu cầu hoàn hàng. Vui lòng chờ Admin xử lý.',
                'data' => $order
            ]);
        }

        return response()->json([
            'message' => 'Đơn hàng không đủ điều kiện để hoàn hàng (Chỉ áp dụng cho đơn đã hoàn thành).'
        ], 400);
    }
}