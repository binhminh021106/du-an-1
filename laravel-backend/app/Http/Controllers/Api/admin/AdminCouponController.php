<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Coupon;
use Illuminate\Validation\Rule;

class AdminCouponController extends Controller
{
    /**
     * Lấy danh sách coupon (Chưa xóa)
     */
    public function index(Request $request)
    {
        // Mặc định chỉ lấy các bản ghi chưa bị xóa (Soft Delete)
        $query = Coupon::query();

        if ($request->has('_sort') && $request->has('_order')) {
            $query->orderBy($request->_sort, $request->_order);
        } else {
            $query->orderBy('id', 'desc');
        }

        $coupons = $query->get();
        return response()->json($coupons);
    }

    /**
     * Lấy danh sách coupon ĐÃ XÓA (Thùng rác)
     * API: GET /admin/coupons/trashed
     */
    public function trashed(Request $request)
    {
        // onlyTrashed() chỉ lấy các bản ghi đã bị soft delete
        $coupons = Coupon::onlyTrashed()->orderBy('deleted_at', 'desc')->get();
        return response()->json($coupons);
    }

    /**
     * Tạo mới Coupon
     */
    public function store(Request $request)
    {
        // 1. Validate dữ liệu
        $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|unique:coupons,code|max:50',
            'type' => 'required|in:percent,fixed',
            'value' => 'required|numeric|min:0',
            'expiresAt' => 'nullable|date',
            'usageLimit' => 'nullable|integer|min:0',
            'limitPerUser' => 'nullable|integer|min:1', // Chấp nhận null (không giới hạn)
        ], [
            'code.unique' => 'Mã giảm giá này đã tồn tại.',
        ]);

        // 2. Map dữ liệu
        $data = [
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'type' => $request->type,
            'value' => $request->value,
            'usage_count' => 0,
            'expires_at' => $request->expiresAt,
            'usage_limit' => $request->usageLimit,
            'usage_limit_per_user' => $request->limitPerUser,
            'min_spend' => $request->min_spend ?? 0,
        ];

        // 3. Lưu vào DB
        $coupon = Coupon::create($data);

        return response()->json([
            'message' => 'Tạo mã giảm giá thành công',
            'data' => $coupon
        ], 201);
    }

    /**
     * Xem chi tiết 1 Coupon
     */
    public function show(string $id)
    {
        $coupon = Coupon::findOrFail($id);
        return response()->json($coupon);
    }

    /**
     * Cập nhật Coupon
     */
    public function update(Request $request, string $id)
    {
        $coupon = Coupon::findOrFail($id);

        // 1. Validate
        $request->validate([
            'name' => 'required|string|max:255',
            // Code unique nhưng ignore ID hiện tại
            'code' => [
                'required', 
                'string', 
                'max:50', 
                Rule::unique('coupons', 'code')->ignore($coupon->id)
            ],
            'type' => 'required|in:percent,fixed',
            'value' => 'required|numeric|min:0',
            'expiresAt' => 'nullable|date',
            'usageLimit' => 'nullable|integer|min:0',
            'limitPerUser' => 'nullable|integer|min:1', // Chấp nhận null
            'usageCount' => 'nullable|integer|min:0',
        ], [
            'code.unique' => 'Mã giảm giá này đã tồn tại.',
        ]);

        // 2. Map dữ liệu Update
        $data = [
            'name' => $request->name,
            'code' => strtoupper($request->code),
            'type' => $request->type,
            'value' => $request->value,
            'expires_at' => $request->expiresAt,
            'usage_limit' => $request->usageLimit,
            'usage_limit_per_user' => $request->limitPerUser,
        ];

        // Nếu có gửi usageCount thì cập nhật
        if ($request->has('usageCount')) {
            $data['usage_count'] = $request->usageCount;
        }

        // 3. Update
        $coupon->update($data);

        return response()->json([
            'message' => 'Cập nhật mã giảm giá thành công',
            'data' => $coupon
        ]);
    }

    /**
     * Xóa mềm (Soft Delete) -> Đưa vào thùng rác
     */
    public function destroy(string $id)
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->delete(); // Yêu cầu Model dùng SoftDeletes

        return response()->json([
            'message' => 'Đã chuyển mã giảm giá vào thùng rác'
        ]);
    }

    /**
     * Khôi phục từ thùng rác
     * API: POST /admin/coupons/{id}/restore
     */
    public function restore(string $id)
    {
        // withTrashed() để tìm cả trong thùng rác
        $coupon = Coupon::withTrashed()->findOrFail($id);
        $coupon->restore();

        return response()->json([
            'message' => 'Khôi phục mã giảm giá thành công'
        ]);
    }

    /**
     * Xóa vĩnh viễn (Force Delete)
     * API: DELETE /admin/coupons/{id}/force
     */
    public function forceDelete(string $id)
    {
        $coupon = Coupon::withTrashed()->findOrFail($id);
        $coupon->forceDelete();

        return response()->json([
            'message' => 'Đã xóa vĩnh viễn mã giảm giá'
        ]);
    }
}