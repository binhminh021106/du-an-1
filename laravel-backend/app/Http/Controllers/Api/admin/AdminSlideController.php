<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Slide;
use Illuminate\Support\Facades\Storage;

class AdminSlideController extends Controller
{
    /**
     * Lấy danh sách Slide
     */
    public function index()
    {
        $slides = Slide::orderBy('order_number', 'asc')->get();

        // Map dữ liệu từ Database (snake_case) sang JSON cho Vue (camelCase)
        $data = $slides->map(function ($slide) {
            return [
                'id'          => $slide->id,
                'title'       => $slide->title,
                'description' => $slide->description,
                'imageUrl'    => $slide->image_url ? asset($slide->image_url) : null,
                'linkUrl'     => $slide->link_url,
                'order'       => $slide->order_number,
                'status'      => $slide->status,
                'created_at'  => $slide->created_at,
            ];
        });

        return response()->json($data);
    }

    /**
     * Tạo Slide mới (Có upload ảnh)
     */
    public function store(Request $request)
    {
        // 1. Validate dữ liệu
        $request->validate([
            'title' => 'required|string|max:255',
            'image' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:5120', 
            'order' => 'nullable|integer|min:0',
            'status' => 'required|in:published,draft',
        ], [
            'title.required'  => 'Vui lòng nhập tiêu đề slide.',
            'image.required'  => 'Vui lòng chọn hình ảnh.',
            'image.image'     => 'File tải lên phải là hình ảnh.',
            'image.max'       => 'Kích thước ảnh không được vượt quá 5MB.',
        ]);

        // 2. Xử lý upload ảnh
        $imagePath = null;
        if ($request->hasFile('image')) {
            // Lưu vào folder: storage/app/public/slides
            $path = $request->file('image')->store('slides', 'public');
            // Lưu đường dẫn tương đối vào DB: /storage/slides/ten_file.jpg
            $imagePath = '/storage/' . $path;
        }

        // 3. Lưu vào Database (Map tên biến từ Vue sang cột DB)
        $slide = Slide::create([
            'title'        => $request->title,
            'description'  => $request->description,
            'image_url'    => $imagePath,          // Cột DB: image_url
            'link_url'     => $request->linkUrl,   // Vue gửi: linkUrl -> DB: link_url
            'order_number' => $request->order ?? 0,// Vue gửi: order -> DB: order_number
            'status'       => $request->status,
        ]);

        return response()->json([
            'message' => 'Thêm slide thành công',
            'data'    => $slide
        ], 201);
    }

    /**
     * Xem chi tiết (Optional)
     */
    public function show(string $id)
    {
        $slide = Slide::findOrFail($id);
        return response()->json($slide);
    }

    /**
     * Cập nhật Slide (Có thay đổi ảnh hoặc không)
     */
    public function update(Request $request, string $id)
    {
        $slide = Slide::findOrFail($id);

        // 1. Validate
        $request->validate([
            'title' => 'required|string|max:255',
            // Ảnh không bắt buộc khi update (nullable)
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'order' => 'nullable|integer|min:0',
            'status' => 'required|in:published,draft',
        ]);

        // 2. Chuẩn bị dữ liệu update
        $updateData = [
            'title'        => $request->title,
            'description'  => $request->description,
            'link_url'     => $request->linkUrl,    // Map: linkUrl -> link_url
            'order_number' => $request->order ?? 0, // Map: order -> order_number
            'status'       => $request->status,
        ];

        // 3. Kiểm tra nếu có gửi ảnh mới lên
        if ($request->hasFile('image')) {
            // Xóa ảnh cũ đi cho đỡ rác server
            $this->deleteImageFromStorage($slide->image_url);

            // Upload ảnh mới
            $path = $request->file('image')->store('slides', 'public');
            $updateData['image_url'] = '/storage/' . $path;
        }

        // 4. Thực hiện update
        $slide->update($updateData);

        return response()->json([
            'message' => 'Cập nhật thành công',
            'data'    => $slide
        ]);
    }

    /**
     * Xóa Slide
     */
    public function destroy(string $id)
    {
        $slide = Slide::findOrFail($id);

        // Xóa file ảnh trong storage luôn (nếu muốn xóa sạch)
        // Nếu dùng SoftDeletes thì có thể cân nhắc không xóa file ngay
        $this->deleteImageFromStorage($slide->image_url);

        $slide->delete();

        return response()->json(['message' => 'Đã xóa slide thành công']);
    }

    /**
     * Hàm phụ: Xóa file ảnh khỏi đĩa cứng
     */
    private function deleteImageFromStorage($path)
    {
        if ($path) {
            // Đường dẫn trong DB: /storage/slides/abc.jpg
            // Cần chuyển thành: slides/abc.jpg để hàm delete hiểu
            $relativePath = str_replace('/storage/', '', $path);
            
            if (Storage::disk('public')->exists($relativePath)) {
                Storage::disk('public')->delete($relativePath);
            }
        }
    }
}