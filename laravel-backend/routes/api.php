<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// 1. IMPORT CONTROLLERS (CLIENT & ADMIN)

// --- CLIENT CONTROLLERS ---
use App\Http\Controllers\Api\Client\NewController;
use App\Http\Controllers\Api\Client\AuthController;
use App\Http\Controllers\Api\Client\CartController;
use App\Http\Controllers\Api\Client\RoleController;
use App\Http\Controllers\Api\Client\UserController;
use App\Http\Controllers\Api\Client\OrderController;
use App\Http\Controllers\Api\Client\SlideController;
use App\Http\Controllers\Api\Client\CouponController;
use App\Http\Controllers\Api\Client\ReviewController;
use App\Http\Controllers\Api\admin\AdminNewController;
use App\Http\Controllers\Api\Client\CommentController;
use App\Http\Controllers\Api\Client\PaymentController;
use App\Http\Controllers\Api\Client\ProductController;
use App\Http\Controllers\Api\Client\VariantController;
use App\Http\Controllers\Api\admin\AdminAuthController;
use App\Http\Controllers\Api\admin\AdminRoleController;
use App\Http\Controllers\Api\admin\AdminUserController;
use App\Http\Controllers\Api\Client\CategoryController;
use App\Http\Controllers\Api\Client\ChatbotController; // Import Chatbot Controller

// --- ADMIN CONTROLLERS ---
use App\Http\Controllers\Api\Client\CheckoutController;
use App\Http\Controllers\Api\admin\AdminSupportEmailController;
use App\Http\Controllers\Api\Client\WishlistController;
use App\Http\Controllers\Api\admin\AdminBrandController;
use App\Http\Controllers\Api\admin\AdminOrderController;
use App\Http\Controllers\Api\admin\AdminSlideController;
use App\Http\Controllers\Api\admin\AdminCouponController;
use App\Http\Controllers\Api\admin\AdminReviewController;
use App\Http\Controllers\Api\admin\AminAccountController;
use App\Http\Controllers\Api\Client\BrandSlideController;
use App\Http\Controllers\Api\admin\AdminCommentController;
use App\Http\Controllers\Api\admin\AdminProductController;
use App\Http\Controllers\Api\admin\AdminVariantController;
use App\Http\Controllers\Api\Client\NewPasswordController;
use App\Http\Controllers\Api\Client\UserAddressController;
use App\Http\Controllers\Api\admin\AdminCategoryController;
use App\Http\Controllers\Api\Client\ImageProductController;
use App\Http\Controllers\Api\admin\AdminAttributeController;
use App\Http\Controllers\Api\admin\AdminOrderItemController;
use App\Http\Controllers\Api\admin\AdminBrandSlideController;
use App\Http\Controllers\Api\admin\AdminPermissionController;
use App\Http\Controllers\Api\admin\AdminImageProductController;
use App\Http\Controllers\Api\Admin\AdminInventoryController; // Import Inventory Controller

// 2. AUTHENTICATION ROUTES (PUBLIC)

// --- CLIENT AUTH ---
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('auth/google', [AuthController::class, 'redirectToGoogle']);
Route::get('auth/google/callback', [AuthController::class, 'handleGoogleCallback']);

Route::get('auth/facebook', [AuthController::class, 'redirectToFacebook']);
Route::get('auth/facebook/callback', [AuthController::class, 'handleFacebookCallback']);

// --- ADMIN AUTH ---
Route::post('/admin/register', [AdminAuthController::class, 'register']);
Route::post('/admin/login', [AdminAuthController::class, 'login']);

// Route fallback cho unauthenticated request
Route::get('/login', function () {
    return response()->json(['message' => 'Unauthenticated.'], 401);
})->name('login');

// 3. PUBLIC DATA ROUTES (CLIENT - KHÔNG CẦN ĐĂNG NHẬP)

// --- CHATBOT ---
Route::get('/chatbot/search', [ChatbotController::class, 'search']); // Chatbot Search Route

// --- PRODUCTS ---
Route::get('/products', [ProductController::class, 'index']);
Route::get('/product/{id}', [ProductController::class, 'show']);

Route::post('/contact-submit', [AdminSupportEmailController::class, 'store']);
Route::post('/admin/support-emails/{id}/reply', [AdminSupportEmailController::class, 'reply']);

Route::post('/forgot-password', [NewPasswordController::class, 'store'])
    ->name('password.email');

Route::post('/reset-password', [NewPasswordController::class, 'update'])
    ->name('password.update');

Route::post('/payment/vnpay', [PaymentController::class, 'createPaymentUrl']);
Route::get('/payment/vnpay-return', [PaymentController::class, 'vnpayReturn']);

// --- CATEGORIES ---
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/category/{id}', [CategoryController::class, 'show']);

// --- VARIANTS ---
Route::get('/variants', [VariantController::class, 'index']);
Route::get('/variant/{id}', [VariantController::class, 'show']);

// --- SLIDES ---
Route::get('/slides', [SlideController::class, 'index']);
Route::get('/slide/{id}', [SlideController::class, 'show']);

// --- COMMENTS (Chỉ xem công khai) ---
Route::get('/comments', [CommentController::class, 'index']);
Route::get('/comment/{id}', [CommentController::class, 'show']);

// --- COUPONS ---
Route::get('/coupons', [CouponController::class, 'index']);
Route::get('/coupon/{id}', [CouponController::class, 'show']);

// --- IMAGE PRODUCTS ---
Route::get('/imageProducts', [ImageProductController::class, 'index']);
Route::get('/imageProduct/{id}', [ImageProductController::class, 'show']);

// --- NEWS ---
Route::get('/news/popular', [NewController::class, 'popular']);
Route::get('/news', [NewController::class, 'index']);
Route::get('/news/{slug}', [NewController::class, 'show']);

// --- REVIEWS ---
Route::get('/reviews', [ReviewController::class, 'index']);
Route::get('/review/{id}', [ReviewController::class, 'show']);

// --- ROLES ---
Route::get('/roles', [RoleController::class, 'index']);

// --- BRANDS ---
Route::get('/brands', [BrandSlideController::class, 'index']);
Route::get('/brand/{id}', [BrandSlideController::class, 'show']);

// 4. CLIENT PROTECTED ROUTES (YÊU CẦU ĐĂNG NHẬP - auth:sanctum)
Route::middleware(['auth:sanctum'])->group(function () {

    // --- USER INFO ---
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    Route::put('/user/profile', [UserController::class, 'updateProfile']);

    // --- USER ADDRESSES (QUẢN LÝ ĐỊA CHỈ) ---
    Route::get('/user/addresses', [UserAddressController::class, 'index']);
    Route::post('/user/addresses', [UserAddressController::class, 'store']);
    Route::put('/user/addresses/{id}', [UserAddressController::class, 'update']);
    Route::delete('/user/addresses/{id}', [UserAddressController::class, 'destroy']);

    // --- CART ---
    Route::get('/carts', [CartController::class, 'index']);
    Route::post('/cart/add', [CartController::class, 'addToCart']);
    Route::put('/cart/{id}', [CartController::class, 'update']);
    Route::delete('/cart/{id}', [CartController::class, 'destroy']);

    // --- WISHLIST ---
    Route::get('/wishlist', [WishlistController::class, 'index']);
    Route::post('/wishlist/toggle', [WishlistController::class, 'toggle']);
    Route::delete('/wishlist/{productId}', [WishlistController::class, 'destroy']);

    // --- COMMENTS (Gửi bình luận) ---
    Route::post('/comments', [CommentController::class, 'store']);
    Route::delete('/comments/{id}', [CommentController::class, 'destroy']);

    // --- ORDERS ---
    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/order/{id}', [OrderController::class, 'show']);
    Route::post('/orders', [CheckoutController::class, 'store']);
    Route::post('/orders/{id}/repurchase', [OrderController::class, 'repurchase']);
    
    // [FIX 404] Thêm route cho yêu cầu hoàn hàng
    Route::post('/orders/{id}/return', [OrderController::class, 'requestReturn']);
    
    Route::put('/order/{id}', [OrderController::class, 'update']);
    Route::delete('/order/{id}', [OrderController::class, 'destroy']);

    // --- REVIEWS (Đánh giá) ---
    Route::post('/reviews', [ReviewController::class, 'store']);
    Route::put('/review/{id}', [ReviewController::class, 'update']);
    Route::delete('/review/{id}', [ReviewController::class, 'destroy']);
});

// 5. ADMIN PROTECTED ROUTES (YÊU CẦU QUYỀN ADMIN + PREFIX 'admin')
Route::group([
    'prefix' => 'admin',
    'middleware' => ['auth:sanctum', 'admin']
], function () {

    // --- INVENTORY (QUẢN LÝ KHO) ---
    Route::get('inventory', [AdminInventoryController::class, 'index']);
    Route::put('inventory/variants/{id}/stock', [AdminInventoryController::class, 'updateStock']);

    // --- PRODUCTS ---
    Route::apiResource('products', AdminProductController::class);

    // --- CATEGORIES ---
    Route::post('categories/update-order', [AdminCategoryController::class, 'updateOrder']);
    Route::apiResource('categories', AdminCategoryController::class);

    // --- USERS ---
    Route::apiResource('users', AdminUserController::class);

    // --- VARIANTS ---
    Route::post('variants/{id}/attributes', [AdminVariantController::class, 'updateAttributes']);
    Route::apiResource('variants', AdminVariantController::class);

    // --- COMMENTS ---
    Route::apiResource('comments', AdminCommentController::class);

    Route::get('/support-emails', [AdminSupportEmailController::class, 'index']);
    Route::delete('/support-emails/{id}', [AdminSupportEmailController::class, 'destroy']);

    // --- COUPONS ---
    Route::get('coupons/trashed', [AdminCouponController::class, 'trashed']);
    Route::post('coupons/{id}/restore', [AdminCouponController::class, 'restore']);
    Route::delete('coupons/{id}/force', [AdminCouponController::class, 'forceDelete']);
    Route::apiResource('coupons', AdminCouponController::class);

    // --- IMAGE PRODUCTS ---
    Route::post('imageProducts/bulk-delete', [AdminImageProductController::class, 'bulkDestroy']);
    Route::apiResource('imageProducts', AdminImageProductController::class);

    // --- NEWS ---
    Route::apiResource('news', AdminNewController::class);

    // --- ORDERS (Chuẩn RESTful của Admin) ---
    Route::apiResource('orders', AdminOrderController::class);
    Route::get('/order_items', [AdminOrderItemController::class, 'index']);

    // --- REVIEWS ---
    Route::apiResource('reviews', AdminReviewController::class);

    // --- ROLES & PERMISSIONS ---
    Route::get('permissions', [AdminPermissionController::class, 'index']);
    Route::post('roles/{id}/permissions', [AdminRoleController::class, 'assignPermissions']);
    Route::apiResource('roles', AdminRoleController::class);

    // --- SLIDES ---
    Route::post('slides/update-order', [AdminSlideController::class, 'updateOrder']);
    Route::apiResource('slides', AdminSlideController::class);

    // --- ADMIN ACCOUNTS ---
    Route::apiResource('admins', AminAccountController::class);

    // --- BRAND SLIDES ---
    Route::post('brand-slides/update-order', [AdminBrandSlideController::class, 'updateOrder']);
    Route::apiResource('brand-slides', AdminBrandSlideController::class);

    // --- BRANDS ---
    Route::get('brands/trashed', [AdminBrandController::class, 'trashed']);
    Route::post('brands/{id}/restore', [AdminBrandController::class, 'restore']);
    Route::delete('brands/{id}/force', [AdminBrandController::class, 'forceDelete']);
    Route::apiResource('brands', AdminBrandController::class);

    // --- ATTRIBUTES ---
    Route::apiResource('attributes', AdminAttributeController::class);
});