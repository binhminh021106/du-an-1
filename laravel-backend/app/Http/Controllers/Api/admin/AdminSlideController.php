<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Slide;
use Illuminate\Support\Facades\File; // [CHANGED] Dùng File thay vì Storage
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminSlideController extends Controller
{
    /**
     * Lấy danh sách Slide
     */
    public function index()
    {
        // Luôn sắp xếp theo order_number
        $slides = Slide::orderBy('order_number', 'asc')->get();

        $data = $slides->map(function ($slide) {
            return [
                'id'          => $slide->id,
                'title'       => $slide->title,
                'description' => $slide->description,
                // Kiểm tra URL để hiển thị đúng
                'imageUrl'    => $slide->image_url ? (filter_var($slide->image_url, FILTER_VALIDATE_URL) ? $slide->image_url : asset($slide->image_url)) : null,
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

        DB::beginTransaction();
        try {
            // 1. Tạo Slide trước để lấy ID (Chưa có ảnh)
            $slide = Slide::create([
                'title'        => $request->title,
                'description'  => $request->description,
                'image_url'    => '', // Tạm thời để trống
                'link_url'     => $request->linkUrl,
                'order_number' => $request->order ?? 0,
                'status'       => $request->status,
            ]);

            // 2. Xử lý upload ảnh sau khi có ID -> Đổi tên thành slide_{ID}_{RANDOM}
            if ($request->hasFile('image')) {
                $file = $request->file('image');
                $extension = $file->getClientOriginalExtension();
                
                // --- THAY ĐỔI: Thêm số random vào tên file ---
                $randomNum = mt_rand(100000, 999999);
                $fileName = 'slide_' . $slide->id . '_' . $randomNum . '.' . $extension;
                
                // Đường dẫn lưu: public/uploads/slides
                $path = public_path('uploads/slides');

                if (!File::exists($path)) {
                    File::makeDirectory($path, 0755, true);
                }

                $file->move($path, $fileName);
                
                // Cập nhật lại đường dẫn
                $slide->image_url = '/uploads/slides/' . $fileName;
                $slide->save();
            }

            DB::commit();

            return response()->json([
                'message' => 'Thêm slide thành công',
                'data'    => $slide
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Lỗi server: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Xem chi tiết
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

        $request->validate([
            'title' => 'required|string|max:255',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:5120',
            'order' => 'nullable|integer|min:0',
            'status' => 'required|in:published,draft',
        ]);

        try {
            $slide->title = $request->title;
            $slide->status = $request->status;

            if ($request->has('description')) $slide->description = $request->description;
            if ($request->has('linkUrl')) $slide->link_url = $request->linkUrl;
            if ($request->has('order')) $slide->order_number = $request->order;

            // Xử lý ảnh mới (nếu có)
            if ($request->hasFile('image')) {
                // 1. Xóa ảnh cũ
                if ($slide->image_url) {
                    $oldPath = public_path($slide->image_url);
                    if (File::exists($oldPath)) {
                        File::delete($oldPath);
                    }
                }

                // 2. Upload ảnh mới chuẩn tên slide_{ID}_{RANDOM}
                $file = $request->file('image');
                $extension = $file->getClientOriginalExtension();
                
                // --- THAY ĐỔI: Thêm số random vào tên file ---
                $randomNum = mt_rand(100000, 999999);
                $fileName = 'slide_' . $slide->id . '_' . $randomNum . '.' . $extension;
                
                $path = public_path('uploads/slides');

                if (!File::exists($path)) {
                    File::makeDirectory($path, 0755, true);
                }

                $file->move($path, $fileName);
                $slide->image_url = '/uploads/slides/' . $fileName;
            }

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
     * Cập nhật thứ tự sắp xếp Slide (Drag & Drop)
     */
    public function updateOrder(Request $request)
    {
        $validated = $request->validate([
            'ids'   => ['required', 'array'],
            'ids.*' => ['integer', 'exists:slides,id', 'distinct'],
        ], [
            'ids.required'   => 'Danh sách sắp xếp không được để trống.',
            'ids.array'      => 'Dữ liệu phải là mảng ID.',
            'ids.*.exists'   => 'Phát hiện ID Slide không hợp lệ.',
            'ids.*.distinct' => 'Danh sách ID bị trùng lặp.',
        ]);

        $ids = $validated['ids'];

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
        try {
            $slide = Slide::findOrFail($id);

            // Xóa file ảnh vật lý
            if ($slide->image_url) {
                $path = public_path($slide->image_url);
                if (File::exists($path)) {
                    File::delete($path);
                }
            }

            $slide->delete();

            return response()->json(['message' => 'Đã xóa slide thành công']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Lỗi: ' . $e->getMessage()], 500);
        }
    }
}