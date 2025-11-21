<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Client Controllers
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

// Admin Controllers
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
use App\Http\Controllers\Api\admin\AminAccountController; // Giữ nguyên tên class như bạn khai báo (Amin...)
use App\Http\Controllers\Api\admin\AdminBrandSlideController;

/* API Routes */

// --- AUTHENTICATION ---
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::post('/admin/register', [AdminAuthController::class, 'register']);
Route::post('/admin/login', [AdminAuthController::class, 'login']);

// --- PUBLIC DATA (CLIENT) ---
// Giữ nguyên phần Client vì thường Client chỉ cần GET hoặc logic đặc thù
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
Route::get('/new/{id}', [NewController::class, 'show']);

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

    Route::get('/products', [AdminProductController::class, 'index']);
    Route::get('/product/{id}', [AdminProductController::class, 'show']);

    // Category dùng apiResource cho gọn
    Route::apiResource('categories', AdminCategoryController::class);

    Route::get('/users', [AdminUserController::class, 'index']);
    Route::get('/user/{id}', [AdminUserController::class, 'show']);
    // User routes thủ công
    Route::post('/users', [AdminUserController::class, 'store']);
    Route::patch('/users/{id}', [AdminUserController::class, 'update']);
    Route::delete('/users/{id}', [AdminUserController::class, 'destroy']);

    Route::get('/variants', [AdminVariantController::class, 'index']);
    Route::get('/variant/{id}', [AdminVariantController::class, 'show']);

    Route::get('/comments', [AdminCommentController::class, 'index']);
    Route::get('/comment/{id}', [AdminCommentController::class, 'show']);
    Route::apiResource('comments', AdminCommentController::class)
        ->only(['index', 'show', 'update', 'destroy']);

    Route::get('/coupons', [AdminCouponController::class, 'index']);
    Route::get('/coupon/{id}', [AdminCouponController::class, 'show']);
    Route::apiResource('coupons', AdminCouponController::class);

    Route::get('/imageProducts', [AdminImageProductController::class, 'index']);
    Route::get('/imageProduct/{id}', [AdminImageProductController::class, 'show']);

    Route::get('/news', [AdminNewController::class, 'index']);
    Route::get('/new/{id}', [AdminNewController::class, 'show']);
    Route::apiResource('news', AdminNewController::class);

    Route::get('/orders', [AdminOrderController::class, 'index']);
    Route::get('/order/{id}', [AdminOrderController::class, 'show']);

    Route::get('/reviews', [AdminReviewController::class, 'index']);
    Route::get('/review/{id}', [AdminReviewController::class, 'show']);
    Route::apiResource('reviews', AdminReviewController::class)
        ->only(['index', 'show', 'update', 'destroy']);

    Route::apiResource('roles', AdminRoleController::class);

    Route::apiResource('slides', AdminSlideController::class);

    // Route cho Admins Account
    Route::apiResource('admins', AminAccountController::class);

    Route::apiResource('brands', AdminBrandSlideController::class);
});

// Route lấy thông tin user hiện tại
Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});
