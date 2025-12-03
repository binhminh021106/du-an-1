<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// ==============================================================================
// 1. IMPORT CONTROLLERS (CLIENT & ADMIN)
// ==============================================================================

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
// [NEW] Import Wishlist Controller
use App\Http\Controllers\Api\Client\WishlistController;

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
use App\Http\Controllers\Api\admin\AdminBrandController; // [ADD] Import controller Brand Admin

/* API Routes */

// --- AUTHENTICATION ---
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('auth/google', [AuthController::class, 'redirectToGoogle']);
Route::get('auth/google/callback', [AuthController::class, 'handleGoogleCallback']);

Route::post('/admin/register', [AdminAuthController::class, 'register']);
Route::post('/admin/login', [AdminAuthController::class, 'login']);

// [FIX] Thêm route login giả để tránh lỗi 500 "Route [login] not defined" khi token hết hạn
Route::get('/login', function () {
    return response()->json(['message' => 'Unauthenticated.'], 401);
})->name('login');


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


// ==============================================================================
// 4. CLIENT PROTECTED ROUTES (Phải đăng nhập mới dùng được)
// ==============================================================================
Route::middleware(['auth:sanctum'])->group(function () {
    
    // --- GIỎ HÀNG (DATABASE) ---
    // Route này giúp FE lưu sản phẩm vào bảng cart_items
    Route::post('/cart/add', [CartController::class, 'addToCart']);
    
    // --- YÊU THÍCH (WISHLIST) [MỚI] ---
    // Route này để xem danh sách yêu thích
    Route::get('/wishlist', [WishlistController::class, 'index']);
    // Route này để thêm/xóa yêu thích (Toggle)
    Route::post('/wishlist/toggle', [WishlistController::class, 'toggle']);

    // --- USER INFO ---
    // Lấy thông tin chính chủ đang đăng nhập (để hiển thị lên Header, Profile...)
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});


// --- ADMIN ROUTES ---
Route::group([
    'prefix' => 'admin',
    'middleware' => ['auth:sanctum', 'admin']
], function () {
    Route::apiResource('products', AdminProductController::class);

    // Route custom update-order PHẢI đặt trước apiResource
    Route::post('categories/update-order', [AdminCategoryController::class, 'updateOrder']);
    Route::apiResource('categories', AdminCategoryController::class);
    
    Route::apiResource('users', AdminUserController::class);

    Route::apiResource('variants', AdminVariantController::class);
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
    Route::get('permissions', [AdminPermissionController::class, 'index']);
    Route::post('roles/{id}/permissions', [AdminRoleController::class, 'assignPermissions']);
    Route::apiResource('roles', AdminRoleController::class);

    Route::post('slides/update-order', [AdminSlideController::class, 'updateOrder']);
    Route::apiResource('slides', AdminSlideController::class);
    
    Route::apiResource('admins', AminAccountController::class); 
    
    // --- QUẢN LÝ THƯƠNG HIỆU (BRAND SLIDE) ---
    // [ADD] Bổ sung route cho Brand Slide (dùng bảng brand_slides)
    Route::post('brand-slides/update-order', [AdminBrandSlideController::class, 'updateOrder']);
    Route::apiResource('brand-slides', AdminBrandSlideController::class);

    // --- QUẢN LÝ THƯƠNG HIỆU SẢN PHẨM (BRAND PRODUCT) ---
    // [KEEP] Giữ lại route này nếu bạn vẫn cần quản lý Brand cho sản phẩm (dùng bảng brands)
    Route::get('brands/trashed', [AdminBrandController::class, 'trashed']); // Thùng rác
    Route::post('brands/{id}/restore', [AdminBrandController::class, 'restore']); // Khôi phục
    Route::delete('brands/{id}/force', [AdminBrandController::class, 'forceDelete']); // Xóa vĩnh viễn
    Route::apiResource('brands', AdminBrandController::class); 

    Route::apiResource('attributes', AdminAttributeController::class);
});