<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Coupon;

class CouponController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Lấy thời gian hiện tại
        $now = now();

        $coupons = Coupon::query()
            // 1. Lọc theo ngày hết hạn (expires_at):
            // Lấy các mã có expires_at là NULL (không bao giờ hết hạn) 
            // HOẶC expires_at lớn hơn hoặc bằng thời điểm hiện tại.
            ->where(function ($query) use ($now) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>=', $now);
            })
            
            // 2. Lọc theo giới hạn sử dụng (usage_limit):
            // Lấy các mã có usage_limit là NULL (không giới hạn)
            // HOẶC số lần đã dùng (usage_count) nhỏ hơn giới hạn (usage_limit).
            ->where(function ($query) {
                $query->whereNull('usage_limit')
                      ->orWhereColumn('usage_count', '<', 'usage_limit');
            })
            
            // Sắp xếp mã mới nhất lên đầu
            ->latest() 
            ->get();

        return response()->json($coupons);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $coupon = Coupon::findOrFail($id);

        // Cũng nên kiểm tra khi xem chi tiết 1 coupon
        // if ($coupon->expiry_date < now() || !$coupon->is_active) { ... }

        return response()->json($coupon);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}