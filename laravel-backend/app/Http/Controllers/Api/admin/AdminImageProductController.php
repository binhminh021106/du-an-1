<?php
namespace App\Http\Controllers\Api\admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ImageProduct;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AdminImageProductController extends Controller
{
    public function index()
    {
        $imageProducts = ImageProduct::all();
        return response()->json($imageProducts);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|exists:product,id',
            'image_url' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $imageProduct = ImageProduct::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Image product created successfully',
            'data' => $imageProduct
        ], 201);
    }

    public function show(string $id)
    {
        $imageProduct = ImageProduct::findOrFail($id);
        return response()->json($imageProduct);
    }

    public function update(Request $request, string $id)
    {
        $validator = Validator::make($request->all(), [
            'image_url' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $imageProduct = ImageProduct::findOrFail($id);
        
        // Xóa ảnh cũ nếu có
        if ($request->has('image_url') && $imageProduct->image_url) {
            Storage::delete($imageProduct->image_url);
        }

        $imageProduct->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Image product updated successfully',
            'data' => $imageProduct
        ]);
    }

    public function destroy(string $id)
    {
        $imageProduct = ImageProduct::findOrFail($id);
        
        // Xóa file ảnh
        if ($imageProduct->image_url) {
            Storage::delete($imageProduct->image_url);
        }
        
        $imageProduct->delete();

        return response()->json([
            'success' => true,
            'message' => 'Image product deleted successfully'
        ]);
    }
}