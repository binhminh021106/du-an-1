<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OrderItem;
use Illuminate\Support\Facades\Log;

class AdminOrderItemController extends Controller
{
    /**
     * Lấy danh sách sản phẩm của một đơn hàng cụ thể
     * GET: /api/admin/order_items?orderId=...&_expand=product
     */
    public function index(Request $request)
    {
        try {
            // Bắt buộc phải có orderId
            if (!$request->has('orderId')) {
                return response()->json(['message' => 'Thiếu tham số orderId'], 400);
            }

            $query = OrderItem::query();
            
            // Lọc theo Order ID
            $query->where('order_id', $request->orderId);

            // Eager Load quan hệ để lấy thông tin sản phẩm
            // Cấu trúc: OrderItem -> Variant -> Product
            // Cấu trúc: OrderItem -> Variant -> AttributeValues -> Attribute (Màu/Size)
            if ($request->has('_expand') && $request->_expand == 'product') {
                $query->with(['variant.product', 'variant.attributeValues.attribute']); 
            }

            $items = $query->get();

            // Transform dữ liệu để Frontend dễ hiển thị
            // Mục đích: Đưa thông tin product ra root của item để dễ truy cập (item.product.name)
            if ($request->has('_expand') && $request->_expand == 'product') {
                $items->transform(function ($item) {
                    $productData = null;
                    
                    // Logic lấy thông tin product từ quan hệ variant
                    if ($item->variant && $item->variant->product) {
                        $productData = $item->variant->product;
                    } 
                    // Fallback: Nếu order_item lưu trực tiếp product_id (tùy cấu trúc DB cũ/mới)
                    elseif ($item->product) {
                        $productData = $item->product;
                    }

                    // Gán relation ảo 'product'
                    $item->setRelation('product', $productData);
                    
                    return $item;
                });
            }

            return response()->json([
                'status' => 'success',
                'data' => $items
            ]);

        } catch (\Exception $e) {
            Log::error("Lỗi lấy chi tiết Order Items: " . $e->getMessage());
            return response()->json([
                'message' => 'Lỗi Server khi lấy chi tiết đơn hàng.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}