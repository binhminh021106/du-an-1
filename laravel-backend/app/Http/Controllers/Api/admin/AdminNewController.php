<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\News;
use Illuminate\Validation\Rule;
// --- QUAN TRỌNG: Phải có 2 dòng này mới upload/xóa ảnh được ---
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminNewController extends Controller
{
    /**
     * Helper: Xóa ảnh vật lý khỏi ổ cứng (Private function)
     * Tránh lặp code giữa hàm update và destroy
     */
    private function deletePhysicalImage($fullPath)
    {
        if (!$fullPath) return;

        // Parse URL để lấy path: http://domain/storage/news/a.jpg -> /storage/news/a.jpg
        $path = parse_url($fullPath, PHP_URL_PATH);

        // Xóa prefix '/storage/' để lấy path relative trong disk public: news/a.jpg
        // Lưu ý: Cấu hình mặc định của Laravel thường map public/storage -> storage/app/public
        $relativePath = str_replace('/storage/', '', $path);

        // Kiểm tra file có tồn tại trong disk 'public' không rồi xóa
        if (Storage::disk('public')->exists($relativePath)) {
            Storage::disk('public')->delete($relativePath);
        }
    }

    /**
     * Helper: Xử lý upload ảnh (Private function)
     */
    private function handleUploadImage(Request $request, $fieldName = 'image', $oldPath = null)
    {
        if ($request->hasFile($fieldName)) {
            // 1. Nếu có ảnh cũ -> Xóa ảnh cũ đi để tránh rác
            if ($oldPath) {
                $this->deletePhysicalImage($oldPath);
            }

            // 2. Upload ảnh mới
            $file = $request->file($fieldName);
            // Tạo tên file unique: time_slug-ten-anh.jpg
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

        // Sorting
        $sortField = $request->input('_sort', 'id');
        $sortOrder = $request->input('_order', 'desc');
        
        $allowedSorts = ['id', 'title', 'created_at', 'status', 'author_name'];
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
        // 1. Validate
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'slug'        => 'required|string|max:255|unique:news,slug',
            'excerpt'     => 'nullable|string',
            'content'     => 'required|string',
            'image'       => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'author_name' => 'required|string|max:100', 
            'status'      => 'required|in:published,draft,pending',
        ], [
            'slug.unique' => 'Đường dẫn (Slug) này đã tồn tại.',
            'image.image' => 'File tải lên phải là hình ảnh.',
            'image.max'   => 'Kích thước ảnh tối đa là 2MB.',
            'author_name.required' => 'Tên tác giả là bắt buộc.',
            'author_name.max' => 'Tên tác giả không được quá 100 ký tự.'
        ]);

        // 2. Xử lý upload ảnh
        $imagePath = $this->handleUploadImage($request, 'image');
        
        // Chuẩn bị dữ liệu create
        $data = $validated;
        if ($imagePath) {
            $data['image_url'] = $imagePath;
        }
        unset($data['image']);

        // Đảm bảo author_id là null
        $data['author_id'] = null;

        // 3. Tạo record
        $news = News::create($data);
        
        return response()->json($news, 201);
    }

    /**
     * Xem chi tiết
     */
    public function show(string $id)
    {
        $news = News::findOrFail($id);
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
            'title'       => 'sometimes|required|string|max:255',
            'slug'        => ['sometimes', 'required', 'string', Rule::unique('news')->ignore($news->id)],
            'excerpt'     => 'nullable|string',
            'content'     => 'sometimes|required|string',
            'image'       => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'author_name' => 'sometimes|required|string|max:100',
            'status'      => 'sometimes|required|in:published,draft,pending',
        ]);

        $data = $validated;

        // Xử lý upload ảnh mới (nếu có gửi lên)
        if ($request->hasFile('image')) {
            // Hàm này sẽ tự động gọi deletePhysicalImage để xóa ảnh cũ của $news
            $newImagePath = $this->handleUploadImage($request, 'image', $news->image_url);
            if ($newImagePath) {
                $data['image_url'] = $newImagePath;
            }
            unset($data['image']);
        }

        unset($data['updated_at']);
        
        if (isset($data['author_id'])) {
            unset($data['author_id']);
        }

        $news->update($data);

        return response()->json($news);
    }

    /**
     * Xóa tin tức
     */
    public function destroy(string $id)
    {
        $news = News::findOrFail($id);
        
        // [QUAN TRỌNG] Xóa ảnh vật lý trước khi xóa record trong DB
        if ($news->image_url) {
             $this->deletePhysicalImage($news->image_url);
        }

        $news->delete();
        return response()->json(['message' => 'Đã xóa tin tức và hình ảnh thành công']);
    }
}