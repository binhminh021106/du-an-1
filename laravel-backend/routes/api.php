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

// Admin Controllers
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

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/


// Auth
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']); 

// Public Data
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

// --- ADMIN ROUTES ---
Route::group([
    'prefix' => 'admin',
    // 'middleware' => ['auth:sanctum', 'admin']
], function () {

    Route::get('/products', [AdminProductController::class, 'index']);
    Route::get('/product/{id}', [AdminProductController::class, 'show']);

    Route::get('/categories', [AdminCategoryController::class, 'index']);
    Route::get('/category/{id}', [AdminCategoryController::class, 'show']);

    Route::get('/users', [AdminUserController::class, 'index']);
    Route::get('/user/{id}', [AdminUserController::class, 'show']);

    Route::get('/variants', [AdminVariantController::class, 'index']);
    Route::get('/variant/{id}', [AdminVariantController::class, 'show']);

    Route::get('/comments', [AdminCommentController::class, 'index']);
    Route::get('/comment/{id}', [AdminCommentController::class, 'show']);

    Route::get('/coupons', [AdminCouponController::class, 'index']);
    Route::get('/coupon/{id}', [AdminCouponController::class, 'show']);

    Route::get('/imageProducts', [AdminImageProductController::class, 'index']);
    Route::get('/imageProduct/{id}', [AdminImageProductController::class, 'show']);

    Route::get('/news', [AdminNewController::class, 'index']);
    Route::get('/new/{id}', [AdminNewController::class, 'show']);

    Route::get('/orders', [AdminOrderController::class, 'index']);
    Route::get('/order/{id}', [AdminOrderController::class, 'show']);

    Route::get('/reviews', [AdminReviewController::class, 'index']);
    Route::get('/review/{id}', [AdminReviewController::class, 'show']);

    Route::get('/roles', [AdminRoleController::class, 'index']);
    Route::get('/role/{id}', [AdminRoleController::class, 'show']);

    Route::get('/slides', [AdminSlideController::class, 'index']);
    Route::get('/slide/{id}', [AdminSlideController::class, 'show']);

    Route::get('/admins', [AminAccountController::class, 'index']);
    Route::get('/admin/{id}', [AminAccountController::class, 'show']);
});

// Route lấy thông tin user hiện tại (cần token)
Route::middleware(['auth:sanctum'])->get('/user', function (Request $request) {
    return $request->user();
});
