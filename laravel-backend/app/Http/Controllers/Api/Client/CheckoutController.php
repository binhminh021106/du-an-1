<?php

namespace App\Http\Controllers\Api\Client; 

use App\Http\Controllers\Controller; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Coupon;
use App\Models\Variant; 

class CheckoutController extends Controller
{
    public function store(Request $request)
    {
        // 1. VALIDATION
        $validatedData = $request->validate([
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'required|string|max:20',
            'shipping_address' => 'required|string|max:500',
            'payment_method' => 'required|string|in:COD,BANK,CARD',
            'shipping_fee' => 'required|numeric|min:0',
            'discount_amount' => 'required|numeric|min:0',
            'coupon_code' => 'nullable|string|max:50',
            'total_amount' => 'required|numeric|min:0',
            'items' => 'required|array|min:1',
            
            // [ĐÃ SỬA] Đổi 'products' thành 'product' (số ít) để khớp với tên bảng của bạn
            'items.*.product_id' => 'required|exists:product,id',
            
            // [LƯU Ý] Nếu bảng biến thể của bạn tên là 'variant' (số ít), hãy sửa 'variants' thành 'variant' ở dòng dưới
            'items.*.variant_id' => 'nullable|exists:variants,id', 
            
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();

        try {
            // 2. XỬ LÝ COUPON
            $coupon = null;
            $finalDiscountAmount = (int) $validatedData['discount_amount'];

            if ($validatedData['coupon_code']) {
                $coupon = Coupon::where('code', $validatedData['coupon_code'])->first();
                if (!$coupon) throw new \Exception("Mã giảm giá không tồn tại.");
                
                $this->validateCoupon($coupon, $validatedData);
            }

            $subtotal = $this->calculateSubtotal($validatedData['items']);

            // 3. TẠO ORDER
            $order = Order::create([
                'user_id' => Auth::id(),
                'customer_name' => $validatedData['customer_name'],
                'customer_email' => $validatedData['customer_email'],
                'customer_phone' => $validatedData['customer_phone'],
                'shipping_address' => $validatedData['shipping_address'],
                'payment_method' => $validatedData['payment_method'],
                'payment_status' => 'pending',
                'status' => 'pending',
                'shipping_fee' => (int) $validatedData['shipping_fee'],
                'discount_amount' => $finalDiscountAmount,
                'subtotal_amount' => $subtotal,
                'total_amount' => (int) $validatedData['total_amount'],
                'coupon_id' => $coupon ? $coupon->id : null,
            ]);

            // 4. TẠO ORDER ITEMS & TRỪ KHO
            foreach ($validatedData['items'] as $item) {
                $this->checkAndUpdateStock($item);

                OrderItem::create([
                    'order_id' => $order->id,
                    'variant_id' => $item['variant_id'] ?? null, 
                    'price' => (int) $item['price'],
                    'quantity' => (int) $item['quantity'],
                ]);
            }

            if ($coupon) {
                $coupon->increment('usage_count');
            }

            DB::commit();

            return response()->json([
                'message' => 'Đơn hàng thành công.',
                'data' => ['id' => $order->id]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Lỗi Checkout: " . $e->getMessage());
            return response()->json(['message' => $e->getMessage(), 'error' => true], 400);
        }
    }

    protected function checkAndUpdateStock(array $item)
    {
        $qty = (int) $item['quantity'];
        if ($item['variant_id']) {
            $variant = Variant::find($item['variant_id']);
            if (!$variant || $variant->stock < $qty) {
                throw new \Exception("Sản phẩm (Biến thể ID: {$item['variant_id']}) không đủ hàng.");
            }
            $variant->decrement('stock', $qty);
        }
    }

    protected function validateCoupon(Coupon $coupon, array $validatedData)
    {
        $now = now();
        $subtotal = $this->calculateSubtotal($validatedData['items']);

        if ($coupon->expires_at && $coupon->expires_at < $now) throw new \Exception("Mã hết hạn.");
        if ($coupon->usage_limit !== null && $coupon->usage_count >= $coupon->usage_limit) throw new \Exception("Mã hết lượt dùng.");
        if ($coupon->min_spend && $subtotal < $coupon->min_spend) throw new \Exception("Chưa đủ giá trị đơn hàng.");
    }

    protected function calculateSubtotal(array $items) : int
    {
        $total = collect($items)->sum(function ($item) {
            return ((int) $item['price']) * ((int) $item['quantity']);
        });
        return (int) $total;
    }
}