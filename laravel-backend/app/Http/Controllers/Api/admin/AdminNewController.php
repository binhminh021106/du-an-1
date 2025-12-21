<?php

namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\News;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AdminNewController extends Controller
{
    private function deletePhysicalImage($fullPath)
    {
        if (!$fullPath) return;
        $path = parse_url($fullPath, PHP_URL_PATH);
        $relativePath = str_replace('/storage/', '', $path);
        if (Storage::disk('public')->exists($relativePath)) {
            Storage::disk('public')->delete($relativePath);
        }
    }

    private function handleUploadImage(Request $request, $fieldName = 'image', $oldPath = null, $id = null)
    {
        if ($request->hasFile($fieldName)) {
            if ($oldPath) {
                $this->deletePhysicalImage($oldPath);
            }
            $file = $request->file($fieldName);
            $randomNum = mt_rand(100000, 999999);
            $prefix = $id ? 'news_' . $id : 'news_temp';
            $filename = $prefix . '_' . $randomNum . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('news', $filename, 'public');
            return '/storage/' . $path;
        }
        return null;
    }

    public function index(Request $request)
    {
        $query = News::query();

        // Sorting
        $sortField = $request->input('_sort', 'id');
        $sortOrder = $request->input('_order', 'desc');
        
        $allowedSorts = ['id', 'title', 'created_at', 'status', 'author_name', 'category']; // [UPDATED] Thêm category vào sort
        if (in_array($sortField, $allowedSorts)) {
            $query->orderBy($sortField, $sortOrder);
        } else {
            $query->orderBy('id', 'desc');
        }

        $news = $query->get();

        return response()->json($news);
    }

    public function store(Request $request)
    {
        // 1. Validate
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'category'    => 'nullable|string|max:100', // [UPDATED] Validate Category
            'slug'        => 'required|string|max:255|unique:news,slug',
            'excerpt'     => 'nullable|string',
            'content'     => 'required|string',
            'image'       => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'author_name' => 'required|string|max:100', 
            'status'      => 'required|in:published,draft,pending',
            'meta_title'       => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'meta_keywords'    => 'nullable|string|max:255',
        ], [
            'slug.unique' => 'Đường dẫn (Slug) này đã tồn tại.',
            'image.image' => 'File tải lên phải là hình ảnh.',
            'image.max'   => 'Kích thước ảnh tối đa là 2MB.',
        ]);

        $data = $validated;
        unset($data['image']);
        $data['image_url'] = null;
        $data['author_id'] = null;

        $news = News::create($data);

        if ($request->hasFile('image')) {
            $imagePath = $this->handleUploadImage($request, 'image', null, $news->id);
            if ($imagePath) {
                $news->image_url = $imagePath;
                $news->save();
            }
        }
        
        return response()->json($news, 201);
    }

    public function show(string $id)
    {
        $news = News::findOrFail($id);
        return response()->json($news);
    }

    public function update(Request $request, string $id)
    {
        $news = News::findOrFail($id);

        $validated = $request->validate([
            'title'       => 'sometimes|required|string|max:255',
            'category'    => 'nullable|string|max:100', // [UPDATED] Validate Category
            'slug'        => ['sometimes', 'required', 'string', Rule::unique('news')->ignore($news->id)],
            'excerpt'     => 'nullable|string',
            'content'     => 'sometimes|required|string',
            'image'       => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:2048',
            'author_name' => 'sometimes|required|string|max:100',
            'status'      => 'sometimes|required|in:published,draft,pending',
            'meta_title'       => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'meta_keywords'    => 'nullable|string|max:255',
        ]);

        $data = $validated;

        if ($request->hasFile('image')) {
            $newImagePath = $this->handleUploadImage($request, 'image', $news->image_url, $news->id);
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

    public function destroy(string $id)
    {
        $news = News::findOrFail($id);
        if ($news->image_url) {
             $this->deletePhysicalImage($news->image_url);
        }
        $news->delete();
        return response()->json(['message' => 'Đã xóa tin tức và hình ảnh thành công']);
    }
}