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
use App\Http\Controllers\Api\admin\AminAccountController;
use App\Http\Controllers\Api\admin\AdminBrandSlideController;
use App\Http\Controllers\Api\admin\AdminAttributeController;

/* API Routes */

// --- AUTHENTICATION ---
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

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
    Route::apiResource('products', AdminProductController::class);
    Route::apiResource('categories', AdminCategoryController::class);
    Route::apiResource('users', AdminUserController::class);
    
    // Resource variants chuẩn (CRUD cơ bản)
    Route::apiResource('variants', AdminVariantController::class);
    
    // --- KHẮC PHỤC LỖI 404: Thêm Route custom cho Attributes ---
    // Route này ánh xạ POST /api/admin/variants/{id}/attributes -> hàm updateAttributes
    Route::post('variants/{id}/attributes', [AdminVariantController::class, 'updateAttributes']);
    // -----------------------------------------------------------

    Route::apiResource('comments', AdminCommentController::class);
    Route::apiResource('coupons', AdminCouponController::class);
    Route::apiResource('imageProducts', AdminImageProductController::class);
    Route::apiResource('news', AdminNewController::class);
    Route::apiResource('orders', AdminOrderController::class);
    Route::apiResource('reviews', AdminReviewController::class);
    Route::apiResource('roles', AdminRoleController::class);
    Route::apiResource('slides', AdminSlideController::class);
    Route::apiResource('admins', AminAccountController::class);
    Route::apiResource('brands', AdminBrandSlideController::class);
    
    Route::apiResource('attributes', AdminAttributeController::class);
});

Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});