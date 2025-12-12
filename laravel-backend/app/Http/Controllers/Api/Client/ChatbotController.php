<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Product;
use App\Models\ChatbotSession;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class ChatbotController extends Controller
{
    /**
     * API Tìm kiếm sản phẩm dành cho Chatbot
     * Phương thức: GET
     * Param: ?q={từ_khóa}&session_id={uuid_nếu_có}
     */
    public function search(Request $request)
    {
        try {
            $keyword = $request->input('q');
            
            // 1. Xử lý Session ID: Nếu client gửi lên thì dùng, không thì tạo mới
            $sessionId = $request->input('session_id');
            if (!$sessionId || $sessionId === 'null' || $sessionId === 'undefined') {
                $sessionId = (string) Str::uuid();
            }
            
            // Lấy ID user nếu request này đến từ người dùng đã đăng nhập
            // Code chỉ lấy ID, không kiểm tra role hay quyền hạn, phù hợp với bảng users của bạn
            $userId = $request->user() ? $request->user()->id : null;

            if (!$keyword) {
                return response()->json([
                    'status' => 'success',
                    'messages' => [['text' => 'Bạn muốn tìm sản phẩm gì? Hãy gõ tên, hãng hoặc loại sản phẩm nhé.']],
                    'results' => [],
                    'session_id' => $sessionId
                ]);
            }

            // 2. LƯU LỊCH SỬ SESSION VÀO DB
            // Bọc trong try-catch riêng để lỗi lưu log không ảnh hưởng đến việc tìm kiếm
            try {
                $this->saveSession($sessionId, $userId, $keyword);
            } catch (\Throwable $e) {
                Log::error("Lỗi lưu session chatbot: " . $e->getMessage());
                // Không return lỗi ở đây để vẫn trả về kết quả tìm kiếm cho user
            }

            // 3. Query tìm kiếm đa điều kiện
            $products = Product::with(['category', 'brand', 'variants'])
                ->where('status', 'active')
                ->where(function ($query) use ($keyword) {
                    $query->where('name', 'like', "%{$keyword}%")
                    ->orWhereHas('category', function ($q) use ($keyword) {
                        $q->where('name', 'like', "%{$keyword}%");
                    })
                    ->orWhereHas('brand', function ($q) use ($keyword) {
                        $q->where('name', 'like', "%{$keyword}%");
                    });
                })
                ->take(5)
                ->get();

            // 4. Format dữ liệu trả về
            if ($products->isEmpty()) {
                return response()->json([
                    'status' => 'success',
                    'messages' => [['text' => "Hmm, mình không tìm thấy sản phẩm nào liên quan đến '{$keyword}'. Bạn thử từ khóa khác xem sao?"]],
                    'results' => [],
                    'session_id' => $sessionId
                ]);
            }

            $results = $products->map(function ($product) {
                $minPrice = 0;
                if ($product->variants && $product->variants->isNotEmpty()) {
                    $minPrice = $product->variants->min('price');
                }

                $imageUrl = $product->thumbnail_url;
                if ($imageUrl && !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                    $imageUrl = asset($imageUrl);
                }

                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'category' => $product->category->name ?? 'Khác',
                    'brand' => $product->brand->name ?? '',
                    'price' => number_format($minPrice, 0, ',', '.') . ' đ',
                    'image' => $imageUrl,
                    'url' => env('FRONTEND_URL', 'http://localhost:5173') . "/product/{$product->id}",
                ];
            });

            return response()->json([
                'status' => 'success',
                'messages' => [['text' => "Đây là một số sản phẩm mình tìm thấy cho từ khóa '{$keyword}':"]],
                'results' => $results,
                'session_id' => $sessionId
            ]);

        } catch (\Throwable $e) {
            Log::error("Lỗi API Chatbot Search: " . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Đã xảy ra lỗi máy chủ nội bộ.',
                'error_detail' => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Helper: Lưu hoặc Cập nhật Session Chat
     */
    private function saveSession($sessionId, $userId, $query)
    {
        // Tìm session cũ
        $session = ChatbotSession::find($sessionId);

        // Lấy context cũ (nếu có)
        // Lưu ý: current_context đã được cast sang array trong Model
        $context = ($session && $session->current_context) ? $session->current_context : [];
        
        // Append câu hỏi mới vào context
        $context[] = [
            // 'role' ở đây nghĩa là vai trò trong hội thoại (người hỏi), KHÔNG phải quyền trong DB
            // Giá trị 'user' giúp Frontend biết tin nhắn này nằm bên phải (của người dùng)
            'role' => 'user',
            'content' => $query,
            'timestamp' => now()->toIso8601String()
        ];

        // Giới hạn lưu 10 tin nhắn gần nhất
        if (count($context) > 10) {
            $context = array_slice($context, -10);
        }

        if ($session) {
            // Update session cũ
            $session->user_id = $userId ?? $session->user_id;
            $session->last_query = $query;
            $session->current_context = $context;
            $session->updated_at = now();
            $session->save();
        } else {
            // Tạo session mới
            ChatbotSession::create([
                'session_id' => $sessionId,
                'user_id' => $userId,
                'last_query' => $query,
                'current_context' => $context,
                'state' => 'ACTIVE'
            ]);
        }
    }
}