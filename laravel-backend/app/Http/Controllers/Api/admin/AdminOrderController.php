<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Variant; 
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class AdminOrderController extends Controller
{
    /**
     * Lấy danh sách toàn bộ đơn hàng
     * GET: /api/admin/orders
     */
    public function index(Request $request)
    {
        try {
            // SỬA: Dùng 'orderDetails' thay vì 'items' để khớp với Model Order
            $orders = Order::with(['orderDetails', 'user'])->orderBy('created_at', 'desc')->get();

            return response()->json([
                'status' => 'success',
                'data' => $orders
            ]);
        } catch (\Exception $e) {
            Log::error("Lỗi lấy danh sách đơn hàng: " . $e->getMessage());
            return response()->json([
                'message' => 'Lỗi Server khi lấy danh sách đơn hàng.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cập nhật trạng thái đơn hàng (Duyệt, Giao hàng, Hủy, Hoàn trả...)
     * Tự động cộng/trừ tồn kho dựa trên trạng thái.
     * PATCH: /api/admin/orders/{id}
     */
    public function update(Request $request, $id)
    {
        Log::info("AdminOrderController::update - Bắt đầu cập nhật đơn hàng ID: " . $id);

        DB::beginTransaction();
        try {
            // 1. Tìm đơn hàng
            $order = Order::find($id);

            if (!$order) {
                return response()->json(['message' => 'Đơn hàng không tồn tại'], 404);
            }

            // 2. Validate dữ liệu
            $request->validate([
                'status' => 'required|string|in:pending,approved,shipping,completed,cancelled,returning,returned'
            ]);

            $oldStatus = $order->status;
            $newStatus = $request->status;

            Log::info("Trạng thái cũ: $oldStatus -> Mới: $newStatus");

            if ($oldStatus === $newStatus) {
                DB::rollBack();
                return response()->json([
                    'message' => 'Trạng thái không thay đổi',
                    'data' => $order
                ]);
            }

            // --- LOGIC CẬP NHẬT KHO (STOCK) ---
            $restockStatuses = ['cancelled', 'returned'];

            // Điều kiện kích hoạt logic kho:
            // 1. Restock: Khi Hủy hoặc Trả hàng xong (Đang trừ -> Cộng lại)
            $shouldRestock = !in_array($oldStatus, $restockStatuses) && in_array($newStatus, $restockStatuses);
            
            // 2. Deduct: Khi Khôi phục đơn từ đã hủy/trả (Đang cộng -> Trừ lại)
            $shouldDeduct = in_array($oldStatus, $restockStatuses) && !in_array($newStatus, $restockStatuses);

            if ($shouldRestock || $shouldDeduct) {
                // SỬA QUAN TRỌNG: Dùng 'orderDetails' thay vì 'items'
                $order->load('orderDetails'); 

                // Kiểm tra danh sách sản phẩm
                if ($order->orderDetails && $order->orderDetails->count() > 0) {
                    foreach ($order->orderDetails as $item) {
                        
                        // Tìm biến thể sản phẩm
                        $variant = Variant::find($item->variant_id);
                        
                        if ($variant) {
                            $oldStock = $variant->stock;

                            if ($shouldRestock) {
                                // Cộng lại kho
                                $variant->stock += $item->quantity;
                                Log::info("Order #{$id} Restock (Hủy/Trả): Variant #{$variant->id} Stock {$oldStock} -> {$variant->stock}");
                            } elseif ($shouldDeduct) {
                                // Trừ kho lại (nếu lỡ tay hủy nhầm rồi khôi phục)
                                $variant->stock -= $item->quantity;
                                Log::info("Order #{$id} Deduct (Khôi phục): Variant #{$variant->id} Stock {$oldStock} -> {$variant->stock}");
                            }
                            $variant->save();
                        } else {
                            Log::warning("Không tìm thấy Variant ID: {$item->variant_id} trong đơn hàng #{$id}. Bỏ qua cập nhật kho.");
                        }
                    }
                } else {
                    Log::warning("Đơn hàng #{$id} không có sản phẩm (orderDetails rỗng).");
                }
            }

            // --- Cập nhật trạng thái ---
            $order->status = $newStatus;
            $order->save();

            DB::commit();

            return response()->json([
                'message' => 'Cập nhật trạng thái thành công',
                'data' => $order
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Lỗi cập nhật đơn hàng #{$id}: " . $e->getMessage());
            Log::error($e->getTraceAsString());
            
            return response()->json([
                'message' => 'Lỗi Server: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ], 500);
        }
    }

    /**
     * Xóa đơn hàng
     * DELETE: /api/admin/orders/{id}
     */
    public function destroy($id)
    {
        DB::beginTransaction();
        try {
            $order = Order::find($id);

            if (!$order) {
                return response()->json(['message' => 'Đơn hàng không tồn tại'], 404);
            }

            // Xóa items liên quan (Dùng 'orderDetails' nếu Model định nghĩa như vậy)
            if (method_exists($order, 'orderDetails')) {
                $order->orderDetails()->delete();
            } else {
                DB::table('order_items')->where('order_id', $id)->delete();
            }

            $order->delete();

            DB::commit();

            return response()->json([
                'message' => 'Xóa đơn hàng thành công'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Lỗi xóa đơn hàng #{$id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Lỗi Server khi xóa đơn hàng.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}