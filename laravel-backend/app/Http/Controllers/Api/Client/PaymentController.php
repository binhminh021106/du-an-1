<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function createPaymentUrl(Request $request)
    {
        $request->validate([
            'order_id' => 'required|integer|exists:order,id'
        ]);

        // Cưỡng chế múi giờ
        date_default_timezone_set('Asia/Ho_Chi_Minh');

        $orderId = $request->input('order_id');
        $order = Order::findOrFail($orderId);

        // Lấy cấu hình & Trim sạch sẽ
        $vnp_TmnCode = trim(env("VNP_TMN_CODE"));
        $vnp_HashSecret = trim(env("VNP_HASH_SECRET"));
        $vnp_Url = env("VNP_URL");
        $vnp_Returnurl = env("VNP_RETURN_URL");

        $vnp_TxnRef = $order->id . "_" . date('His'); // Mã đơn có timestamp để tránh trùng
        $vnp_OrderInfo = "Thanh toan don hang " . $order->id;
        $vnp_OrderType = 'billpayment';
        $vnp_Amount = (int)($order->total_amount * 100);
        $vnp_Locale = 'vn';
        $vnp_IpAddr = "127.0.0.1";

        $inputData = array(
            "vnp_Version" => "2.1.0",
            "vnp_TmnCode" => $vnp_TmnCode,
            "vnp_Amount" => $vnp_Amount,
            "vnp_Command" => "pay",
            "vnp_CreateDate" => date('YmdHis'),
            "vnp_CurrCode" => "VND",
            "vnp_IpAddr" => $vnp_IpAddr,
            "vnp_Locale" => $vnp_Locale,
            "vnp_OrderInfo" => $vnp_OrderInfo,
            "vnp_OrderType" => $vnp_OrderType,
            "vnp_ReturnUrl" => $vnp_Returnurl,
            "vnp_TxnRef" => $vnp_TxnRef
        );

        ksort($inputData);
        $query = "";
        $i = 0;
        $hashdata = "";
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashdata .= urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
            $query .= urlencode($key) . "=" . urlencode($value) . '&';
        }

        $vnp_Url = $vnp_Url . "?" . $query;
        if (isset($vnp_HashSecret)) {
            $vnpSecureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);
            $vnp_Url .= 'vnp_SecureHash=' . $vnpSecureHash;
        }

        return response()->json([
            'message' => 'Tạo URL thanh toán thành công',
            'data' => $vnp_Url
        ]);
    }

    /**
     * Xử lý kết quả trả về từ VNPay
     */
    public function vnpayReturn(Request $request)
    {
        // [QUAN TRỌNG] Cũng phải Trim key ở đây y hệt hàm trên
        $vnp_HashSecret = trim(env("VNP_HASH_SECRET"));
        
        $inputData = array();
        
        // Lấy tất cả tham số vnp_
        foreach ($request->all() as $key => $value) {
            if (substr($key, 0, 4) == "vnp_") {
                $inputData[$key] = $value;
            }
        }
        
        $vnp_SecureHash = $inputData['vnp_SecureHash'] ?? '';
        unset($inputData['vnp_SecureHash']); // Loại bỏ hash để tính lại
        ksort($inputData);
        
        $i = 0;
        $hashdata = "";
        foreach ($inputData as $key => $value) {
            if ($i == 1) {
                $hashdata .= '&' . urlencode($key) . "=" . urlencode($value);
            } else {
                $hashdata .= urlencode($key) . "=" . urlencode($value);
                $i = 1;
            }
        }

        $secureHash = hash_hmac('sha512', $hashdata, $vnp_HashSecret);

        if ($secureHash == $vnp_SecureHash) {
            // Checksum đúng -> Lấy mã đơn hàng
            $vnp_TxnRef = $inputData['vnp_TxnRef'];
            // Tách timestamp ra (VD: 123_145600 -> lấy 123)
            $parts = explode('_', $vnp_TxnRef);
            $orderId = $parts[0];
            
            $order = Order::find($orderId);

            if ($inputData['vnp_ResponseCode'] == '00') {
                // Code 00 = Thành công
                if ($order) {
                    // Chỉ update nếu chưa paid để tránh update chồng chéo
                    if ($order->payment_status != 'paid') {
                        $order->payment_status = 'paid';
                        $order->payment_method = 'VNPAY';
                        $order->save();
                    }
                    return response()->json([
                        'status' => 'success', 
                        'message' => 'Giao dịch thành công',
                        'data' => $order
                    ]);
                }
                return response()->json(['status' => 'error', 'message' => 'Không tìm thấy đơn hàng']);
            } else {
                // Giao dịch thất bại / Hủy
                if ($order) {
                    $order->payment_status = 'failed';
                    $order->save();
                }
                return response()->json([
                    'status' => 'error', 
                    'message' => 'Giao dịch thất bại', 
                    'code' => $inputData['vnp_ResponseCode']
                ]);
            }
        } else {
            return response()->json(['status' => 'error', 'message' => 'Chữ ký không hợp lệ'], 400);
        }
    }
}