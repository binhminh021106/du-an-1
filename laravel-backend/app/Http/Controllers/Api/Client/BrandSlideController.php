<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\BrandSlide; // <--- Dùng đúng Model này

class BrandSlideController extends Controller
{
    public function index()
    {
        // Chỉ lấy brand đang 'published'
        $brands = BrandSlide::where('status', 'published')
            ->orderBy('order_number', 'asc')
            ->get();

        // Map dữ liệu để có link ảnh đầy đủ
        $data = $brands->map(function ($brand) {
            return [
                'id' => $brand->id,
                'name' => $brand->name,
                // Tạo link đầy đủ: https://domain.app/storage/brands/abc.jpg
                'imageUrl' => $brand->image_url ? asset($brand->image_url) : null,
                'linkUrl' => $brand->link_url,
                'order' => $brand->order_number,
            ];
        });

        return response()->json($data);
    }

    public function show(string $id)
    {
        $brand = BrandSlide::findOrFail($id);
        return response()->json($brand);
    }
}
