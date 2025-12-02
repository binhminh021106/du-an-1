<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Slide;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;  // [NEW] Import Transaction
use Illuminate\Support\Facades\Log; // [NEW] Import Log

class AdminSlideController extends Controller
{
    /**
     * Lấy danh sách Slide
     */
    public function index()
    {
        // [UPDATE] Đảm bảo luôn sắp xếp theo order_number để hiển thị đúng thứ tự
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
        try {
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
        } catch (\Exception $e) {
            // Xóa ảnh nếu lưu DB thất bại để tránh rác
            $this->deleteImageFromStorage($imagePath);
            return response()->json(['message' => 'Lỗi server: ' . $e->getMessage()], 500);
        }
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
     * Cập nhật Slide
     */
    public function update(Request $request, string $id)
    {
        $slide = Slide::findOrFail($id);

        // 1. Validate
        $request->validate([
            'title' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'order' => 'nullable|integer|min:0',
            'status' => 'required|in:published,draft',
        ]);

        // 2. Cập nhật thông tin cơ bản
        $slide->title = $request->title;
        $slide->status = $request->status;

        if ($request->has('description')) {
            $slide->description = $request->description;
        }

        if ($request->has('linkUrl')) {
            $slide->link_url = $request->linkUrl;
        }

        if ($request->has('order')) {
            $slide->order_number = $request->order;
        }

        // 3. Xử lý ảnh mới (nếu có)
        if ($request->hasFile('image')) {
            // Xóa ảnh cũ
            $this->deleteImageFromStorage($slide->image_url);

            // Upload ảnh mới
            $path = $request->file('image')->store('slides', 'public');
            $slide->image_url = '/storage/' . $path;
        }

        // 4. Lưu
        try {
            $slide->save();
            return response()->json([
                'message' => 'Cập nhật thành công',
                'data'    => $slide
            ]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi cập nhật: ' . $e->getMessage()], 500);
        }
    }

    /**
     * [NEW] Cập nhật thứ tự sắp xếp Slide (Drag & Drop)
     */
    public function updateOrder(Request $request)
    {
        // 1. Validate chặt chẽ
        $validated = $request->validate([
            'ids'   => ['required', 'array'],
            'ids.*' => ['integer', 'exists:slides,id', 'distinct'], // Check tồn tại trong bảng slides
        ], [
            'ids.required'   => 'Danh sách sắp xếp không được để trống.',
            'ids.array'      => 'Dữ liệu phải là mảng ID.',
            'ids.*.exists'   => 'Phát hiện ID Slide không hợp lệ.',
            'ids.*.distinct' => 'Danh sách ID bị trùng lặp.',
        ]);

        $ids = $validated['ids'];

        // 2. Transaction
        DB::beginTransaction();

        try {
            foreach ($ids as $index => $id) {
                // index + 1 để bắt đầu từ 1
                Slide::where('id', $id)->update(['order_number' => $index + 1]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Cập nhật vị trí Slide thành công!'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Lỗi sắp xếp Slide: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Có lỗi xảy ra khi cập nhật thứ tự.',
                'detail'  => env('APP_DEBUG') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Xóa Slide
     */
    public function destroy(string $id)
    {
        $slide = Slide::findOrFail($id);

        // Xóa file ảnh trong storage
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
            $relativePath = str_replace('/storage/', '', $path);
            if (Storage::disk('public')->exists($relativePath)) {
                Storage::disk('public')->delete($relativePath);
            }
        }
    }
}   