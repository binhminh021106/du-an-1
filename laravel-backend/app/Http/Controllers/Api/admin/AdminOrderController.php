<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class AdminOrderController extends Controller
{
    /**
     * Lấy danh sách toàn bộ đơn hàng
     * GET: /api/admin/orders
     */
    public function index(Request $request)
    {
        try {
            // Lấy danh sách đơn hàng, sắp xếp mới nhất trước
            $orders = Order::orderBy('created_at', 'desc')->get();

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
     * Cập nhật trạng thái đơn hàng (Duyệt, Giao hàng, Hủy...)
     * PATCH: /api/admin/orders/{id}
     */
    public function update(Request $request, $id)
    {
        try {
            $order = Order::find($id);

            if (!$order) {
                return response()->json(['message' => 'Đơn hàng không tồn tại'], 404);
            }

            // Validate dữ liệu gửi lên
            $request->validate([
                'status' => 'required|string|in:pending,approved,shipping,completed,cancelled,returning,returned'
            ]);

            $oldStatus = $order->status;
            $newStatus = $request->status;

            // Cập nhật trạng thái
            $order->status = $newStatus;
            $order->save();

            Log::info("Admin cập nhật đơn hàng #{$id}: {$oldStatus} -> {$newStatus}");

            return response()->json([
                'message' => 'Cập nhật trạng thái thành công',
                'data' => $order
            ]);

        } catch (\Exception $e) {
            Log::error("Lỗi cập nhật đơn hàng #{$id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Lỗi Server khi cập nhật đơn hàng.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Xóa đơn hàng
     * DELETE: /api/admin/orders/{id}
     */
    public function destroy($id)
    {
        try {
            $order = Order::find($id);

            if (!$order) {
                return response()->json(['message' => 'Đơn hàng không tồn tại'], 404);
            }

            // Xóa mềm hoặc xóa cứng tùy vào logic Model của bạn (ở đây dùng delete chuẩn)
            $order->delete(); 
            // Nếu bạn muốn xóa cả order_items liên quan, hãy đảm bảo trong Database có set ON DELETE CASCADE 
            // hoặc xử lý xóa items ở đây: $order->items()->delete();

            return response()->json([
                'message' => 'Xóa đơn hàng thành công'
            ]);

        } catch (\Exception $e) {
            Log::error("Lỗi xóa đơn hàng #{$id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Lỗi Server khi xóa đơn hàng.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}