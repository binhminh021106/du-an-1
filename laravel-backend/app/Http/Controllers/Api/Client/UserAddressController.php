<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\UserAddress;

class UserAddressController extends Controller
{
    public function index(Request $request)
    {
        // Chỉ lấy địa chỉ của user đang đăng nhập
        $addresses = UserAddress::where('user_id', $request->user()->id)
            ->orderBy('is_default', 'desc')
            ->get();
        return response()->json($addresses);
    }

    public function store(Request $request)
    {
        $userId = $request->user()->id;

        $validated = $request->validate([
            'customer_name' => 'required|string',
            'customer_phone' => 'required|regex:/(0)[0-9]{9}/',
            'city'          => 'required',
            'district'      => 'required',
            'ward'          => 'required',
            'shipping_address' => 'required',
            'is_default'    => 'boolean'
        ]);

        // Nếu địa chỉ mới là mặc định, bỏ mặc định của các địa chỉ cũ
        if ($request->is_default) {
            UserAddress::where('user_id', $userId)->update(['is_default' => false]);
        }

        // Nếu đây là địa chỉ đầu tiên, bắt buộc set mặc định
        $count = UserAddress::where('user_id', $userId)->count();
        if ($count == 0) {
            $validated['is_default'] = true;
        }

        $validated['user_id'] = $userId;

        $address = UserAddress::create($validated);

        return response()->json($address, 201);
    }

    public function update(Request $request, string $id)
    {
        $userId = $request->user()->id;
        $address = UserAddress::where('id', $id)->where('user_id', $userId)->firstOrFail();

        $validated = $request->validate([
            'customer_name' => 'required|string',
            'customer_phone' => 'required|regex:/(0)[0-9]{9}/',
            'city'          => 'required',
            'district'      => 'required',
            'ward'          => 'required',
            'shipping_address' => 'required',
            'is_default'    => 'boolean'
        ]);

        if ($request->is_default) {
            UserAddress::where('user_id', $userId)->where('id', '!=', $id)->update(['is_default' => false]);
        }

        $address->update($validated);

        return response()->json($address);
    }

    public function destroy(Request $request, string $id)
    {
        $userId = $request->user()->id;
        $address = UserAddress::where('id', $id)->where('user_id', $userId)->firstOrFail();

        // Không cho phép xóa địa chỉ mặc định
        if ($address->is_default) {
            return response()->json(['message' => 'Không thể xóa địa chỉ mặc định'], 400);
        }

        $address->delete();

        return response()->json(['message' => 'Xóa địa chỉ thành công']);
    }
}
