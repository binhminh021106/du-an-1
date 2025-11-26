<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\News;
use Illuminate\Validation\Rule;
// --- QUAN TRỌNG: Phải có 2 dòng này mới upload ảnh được ---
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminNewController extends Controller
{
    /**
     * Helper: Xử lý upload ảnh (Private function)
     */
    private function handleUploadImage(Request $request, $fieldName = 'image', $oldPath = null)
    {
        if ($request->hasFile($fieldName)) {
            // 1. Xóa ảnh cũ nếu có
            if ($oldPath) {
                // Parse URL để lấy path tương đối: http://domain/storage/news/a.jpg -> news/a.jpg
                $relativePath = str_replace('/storage/', '', parse_url($oldPath, PHP_URL_PATH));
                
                // Kiểm tra file có tồn tại trong disk public không rồi xóa
                if (Storage::disk('public')->exists($relativePath)) {
                    Storage::disk('public')->delete($relativePath);
                }
            }

            // 2. Upload ảnh mới
            $file = $request->file($fieldName);
            // Tạo tên file unique
            $filename = time() . '_' . Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)) . '.' . $file->getClientOriginalExtension();
            
            // Lưu vào thư mục 'news' trong disk 'public'
            $path = $file->storeAs('news', $filename, 'public');

            // 3. Trả về đường dẫn public đầy đủ
            return '/storage/' . $path;
        }

        return null;
    }

    /**
     * Lấy danh sách tin tức
     */
    public function index(Request $request)
    {
        $query = News::query();

        // Eager Load tác giả
        $query->with('author:id,fullName,avatar_url,email'); 

        // Sorting
        $sortField = $request->input('_sort', 'id');
        $sortOrder = $request->input('_order', 'desc');
        
        $allowedSorts = ['id', 'title', 'created_at', 'status'];
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortOrder);
        } else {
            $query->orderBy('id', 'desc');
        }

        $news = $query->get();

        return response()->json($news);
    }

    /**
     * Tạo tin tức mới
     */
    public function store(Request $request)
    {
        // 1. Validate (Chú ý image là file, không phải url string)
        $validated = $request->validate([
            'title'     => 'required|string|max:255',
            'slug'      => 'required|string|max:255|unique:news,slug',
            'excerpt'   => 'nullable|string',
            'content'   => 'required|string',
            'image'     => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048', // Validate File
            'author_id' => 'required|exists:users,id',
            'status'    => 'required|in:published,draft,pending',
        ], [
            'slug.unique' => 'Đường dẫn (Slug) này đã tồn tại.',
            'image.image' => 'File tải lên phải là hình ảnh.',
            'image.max'   => 'Kích thước ảnh tối đa là 2MB.'
        ]);

        // 2. Xử lý upload ảnh
        $imagePath = $this->handleUploadImage($request, 'image');
        
        // Chuẩn bị dữ liệu create
        $data = $validated;
        if ($imagePath) {
            $data['image_url'] = $imagePath; // Gán đường dẫn ảnh vào cột image_url
        }
        unset($data['image']); // Loại bỏ field image (file object) trước khi create

        // 3. Tạo record
        $news = News::create($data);
        $news->load('author:id,fullName,avatar_url');

        return response()->json($news, 201);
    }

    /**
     * Xem chi tiết
     */
    public function show(string $id)
    {
        $news = News::with('author:id,fullName,avatar_url')->findOrFail($id);
        return response()->json($news);
    }

    /**
     * Cập nhật tin tức
     */
    public function update(Request $request, string $id)
    {
        $news = News::findOrFail($id);

        // Validate
        $validated = $request->validate([
            'title'     => 'sometimes|required|string|max:255',
            'slug'      => ['sometimes', 'required', 'string', Rule::unique('news')->ignore($news->id)],
            'excerpt'   => 'nullable|string',
            'content'   => 'sometimes|required|string',
            'image'     => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'author_id' => 'sometimes|required|exists:users,id',
            'status'    => 'sometimes|required|in:published,draft,pending',
        ]);

        $data = $validated;

        // Xử lý upload ảnh mới (nếu có gửi lên)
        if ($request->hasFile('image')) {
            // Hàm này tự xóa ảnh cũ luôn
            $newImagePath = $this->handleUploadImage($request, 'image', $news->image_url);
            if ($newImagePath) {
                $data['image_url'] = $newImagePath;
            }
            unset($data['image']);
        }

        // Security: Loại bỏ timestamp nếu client lỡ gửi lên
        unset($data['updated_at']);

        $news->update($data);

        return response()->json($news);
    }

    /**
     * Xóa tin tức
     */
    public function destroy(string $id)
    {
        $news = News::findOrFail($id);
        
        // Tùy chọn: Xóa ảnh khỏi ổ cứng khi xóa bài viết (SoftDelete thì không nên xóa ảnh)
        // if ($news->image_url) { ...logic delete image... }

        $news->delete();
        return response()->json(['message' => 'Đã xóa tin tức thành công']);
    }
}