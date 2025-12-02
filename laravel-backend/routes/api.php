<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// --- CLIENT CONTROLLERS ---
use App\Http\Controllers\Api\Client\ProductController;
use App\Http\Controllers\Api\Client\CategoryController;
use App\Http\Controllers\Api\Client\VariantController;
use App\Http\Controllers\Api\Client\SlideController;
use App\Http\Controllers\Api\Client\CommentController;
use App\Http\Controllers\Api\Client\CouponController;
use App\Http\Controllers\Api\Client\ImageProductController;
use App\Http\Controllers\Api\Client\NewController;
use App\Http\Controllers\Api\Client\ReviewController;
use App\Http\Controllers\Api\Client\UserController;
use App\Http\Controllers\Api\Client\UserAddressController;
use App\Http\Controllers\Api\Client\RoleController;
use App\Http\Controllers\Api\Client\CartController;
use App\Http\Controllers\Api\Client\OrderController;
use App\Http\Controllers\Api\Client\AuthController;
use App\Http\Controllers\Api\Client\BrandSlideController;

// --- ADMIN CONTROLLERS (NAMESPACE CHUẨN: 'admin' viết thường) ---
// [FIX] Sửa lại namespace cho khớp với file controller thực tế
use App\Http\Controllers\Api\admin\AdminAuthController;
use App\Http\Controllers\Api\admin\AdminProductController;
use App\Http\Controllers\Api\admin\AdminCategoryController;
use App\Http\Controllers\Api\admin\AdminUserController;
use App\Http\Controllers\Api\admin\AdminVariantController;
use App\Http\Controllers\Api\admin\AdminCommentController;
use App\Http\Controllers\Api\admin\AdminCouponController;
use App\Http\Controllers\Api\admin\AdminImageProductController;
use App\Http\Controllers\Api\admin\AdminNewController;
use App\Http\Controllers\Api\admin\AdminOrderController;
use App\Http\Controllers\Api\admin\AdminReviewController;
use App\Http\Controllers\Api\admin\AdminRoleController;
use App\Http\Controllers\Api\admin\AdminSlideController;
use App\Http\Controllers\Api\admin\AminAccountController; // Note: Tên file gốc là Amin nên để nguyên (thiếu d)
use App\Http\Controllers\Api\admin\AdminBrandSlideController;
use App\Http\Controllers\Api\admin\AdminAttributeController;
use App\Http\Controllers\Api\admin\AdminPermissionController;

/* API Routes */

// --- AUTHENTICATION ---
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('auth/google', [AuthController::class, 'redirectToGoogle']);
Route::get('auth/google/callback', [AuthController::class, 'handleGoogleCallback']);

Route::post('/admin/register', [AdminAuthController::class, 'register']);
Route::post('/admin/login', [AdminAuthController::class, 'login']);


// --- PUBLIC DATA (CLIENT) ---
Route::get('/products', [ProductController::class, 'index']);
Route::get('/product/{id}', [ProductController::class, 'show']);

Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/category/{id}', [CategoryController::class, 'show']);

Route::get('/variants', [VariantController::class, 'index']);
Route::get('/variant/{id}', [VariantController::class, 'show']);

Route::get('/slides', [SlideController::class, 'index']);
Route::get('/slide/{id}', [SlideController::class, 'show']);

Route::get('/comments', [CommentController::class, 'index']);
Route::get('/comment/{id}', [CommentController::class, 'show']);

Route::get('/coupons', [CouponController::class, 'index']);
Route::get('/coupon/{id}', [CouponController::class, 'show']);

Route::get('/imageProducts', [ImageProductController::class, 'index']);
Route::get('/imageProduct/{id}', [ImageProductController::class, 'show']);

Route::get('/news', [NewController::class, 'index']);
Route::get('/news/{slug}', [NewController::class, 'show']);

Route::get('/reviews', [ReviewController::class, 'index']);
Route::get('/review/{id}', [ReviewController::class, 'show']);

Route::get('/users', [UserController::class, 'index']);
Route::get('/user/{id}', [UserController::class, 'show']);

Route::get('/useraddresses', [UserAddressController::class, 'index']);
Route::get('/useraddress/{id}', [UserAddressController::class, 'show']);

Route::get('/roles', [RoleController::class, 'index']);

Route::get('/carts', [CartController::class, 'index']);

Route::get('/orders', [OrderController::class, 'index']);
Route::get('/order/{id}', [OrderController::class, 'show']);

Route::get('/brands', [BrandSlideController::class, 'index']);
Route::get('/brand/{id}', [BrandSlideController::class, 'show']);

// --- ADMIN ROUTES ---
Route::group([
    'prefix' => 'admin',
    'middleware' => ['auth:sanctum', 'admin']
], function () {
    Route::apiResource('products', AdminProductController::class);

    // [FIX] Route custom update-order PHẢI đặt trước apiResource
    Route::post('categories/update-order', [AdminCategoryController::class, 'updateOrder']);
    Route::apiResource('categories', AdminCategoryController::class);
    
    Route::apiResource('users', AdminUserController::class);

    // Resource variants chuẩn (CRUD cơ bản)
    Route::apiResource('variants', AdminVariantController::class);

    // Route này ánh xạ POST /api/admin/variants/{id}/attributes -> hàm updateAttributes
    Route::post('variants/{id}/attributes', [AdminVariantController::class, 'updateAttributes']);

    Route::get('coupons/trashed', [AdminCouponController::class, 'trashed']);
    Route::post('coupons/{id}/restore', [AdminCouponController::class, 'restore']);
    Route::delete('coupons/{id}/force', [AdminCouponController::class, 'forceDelete']);
    Route::apiResource('comments', AdminCommentController::class);
    Route::apiResource('coupons', AdminCouponController::class);


    // --- XỬ LÝ ẢNH ---
    Route::post('imageProducts/bulk-delete', [AdminImageProductController::class, 'bulkDestroy']);
    Route::apiResource('imageProducts', AdminImageProductController::class);

    Route::apiResource('news', AdminNewController::class);
    Route::apiResource('orders', AdminOrderController::class);
    Route::apiResource('reviews', AdminReviewController::class);
    
    // --- QUẢN LÝ QUYỀN HẠN & VAI TRÒ (RBAC) ---
    // 1. Lấy danh sách tất cả quyền (Permissions) để hiển thị checkbox
    Route::get('permissions', [AdminPermissionController::class, 'index']);
    
    // 2. Gán quyền cho Role (Cập nhật bảng role_permissions)
    Route::post('roles/{id}/permissions', [AdminRoleController::class, 'assignPermissions']);
    
    // 3. CRUD Role cơ bản
    Route::apiResource('roles', AdminRoleController::class);

    // [NEW] Route sắp xếp Slide - Đặt trước apiResource
    Route::post('slides/update-order', [AdminSlideController::class, 'updateOrder']);
    Route::apiResource('slides', AdminSlideController::class);
    
    Route::apiResource('admins', AminAccountController::class); 
    
    // [NEW] Route sắp xếp Brand - Đặt trước apiResource
    Route::post('brands/update-order', [AdminBrandSlideController::class, 'updateOrder']);
    Route::apiResource('brands', AdminBrandSlideController::class);

    Route::apiResource('attributes', AdminAttributeController::class);
});

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});