<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Coupon;
use Illuminate\Validation\Rule; // Cần import để validate unique khi update

class AdminCouponController extends Controller
{
    /**
     * Lấy danh sách coupon
     * Hỗ trợ sắp xếp từ Vue: ?_sort=id&_order=desc
     */
    public function index(Request $request)
    {
        $query = Coupon::query();

        // Xử lý sắp xếp nếu Frontend có gửi params
        if ($request->has('_sort') && $request->has('_order')) {
            $query->orderBy($request->_sort, $request->_order);
        } else {
            $query->orderBy('id', 'desc');
        }

        $coupons = $query->get();

        // Map lại dữ liệu trả về cho khớp với Frontend (nếu cần thiết)
        // Tuy nhiên, ở Vue bạn đang dùng trực tiếp snake_case từ Model trả về hoặc 
        // camelCase từ form. Để tiện nhất, tôi sẽ trả về raw model, 
        // nhưng ở hàm store/update tôi sẽ hứng đúng tên biến Vue gửi lên.
        
        // Lưu ý: Vue đang gọi coupon.usageLimit, coupon.limitPerUser ở hàm openEditModal
        // Laravel Model trả về usage_limit, usage_limit_per_user.
        // Để khớp 100%, ta dùng transform hoặc Vue phải tự map. 
        // Cách nhanh nhất: Laravel trả về JSON gốc, Vue map lúc openModal (Bạn đã làm đúng ở Vue).

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
            'code' => 'required|string|unique:coupons,code|max:50', // Code không được trùng
            'type' => 'required|in:percent,fixed',
            'value' => 'required|numeric|min:0',
            'expiresAt' => 'nullable|date', // Vue gửi lên là expiresAt
            'usageLimit' => 'nullable|integer|min:0', // Vue gửi lên là usageLimit
            'limitPerUser' => 'required|integer|min:1', // Vue gửi lên là limitPerUser
        ], [
            'code.unique' => 'Mã giảm giá này đã tồn tại.',
            'limitPerUser.required' => 'Giới hạn lượt dùng mỗi người là bắt buộc.'
        ]);

        // 2. Map dữ liệu từ Frontend (camelCase) sang Database (snake_case)
        $data = [
            'name' => $request->name,
            'code' => strtoupper($request->code), // Luôn viết hoa Code
            'type' => $request->type,
            'value' => $request->value,
            'usage_count' => 0, // Mặc định ban đầu là 0
            'expires_at' => $request->expiresAt, // Map: expiresAt -> expires_at
            'usage_limit' => $request->usageLimit, // Map: usageLimit -> usage_limit
            'usage_limit_per_user' => $request->limitPerUser, // Map: limitPerUser -> usage_limit_per_user
            'min_spend' => $request->min_spend ?? 0, // Nếu có thêm trường này
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
            // Code unique nhưng phải trừ ID hiện tại ra (Rule::unique(...)->ignore(...))
            'code' => ['required', 'string', 'max:50', Rule::unique('coupons')->ignore($coupon->id)],
            'type' => 'required|in:percent,fixed',
            'value' => 'required|numeric|min:0',
            'expiresAt' => 'nullable|date',
            'usageLimit' => 'nullable|integer|min:0',
            'limitPerUser' => 'required|integer|min:1',
            'usageCount' => 'nullable|integer|min:0', // Cho phép sửa số lần đã dùng nếu cần
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

        // Nếu có gửi usageCount (số lần đã dùng) thì cập nhật luôn (Admin quyền lực)
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
     * Xóa Coupon (Soft Delete)
     */
    public function destroy(string $id)
    {
        $coupon = Coupon::findOrFail($id);
        $coupon->delete();

        return response()->json([
            'message' => 'Đã xóa mã giảm giá thành công'
        ]);
    }
}