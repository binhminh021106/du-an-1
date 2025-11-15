<?php

use App\Http\Controllers\admin\ProductController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PdfController;
use App\Http\Controllers\HomeController;

Route::get('/', function () {
    return view('welcome');
});

// Route::controller(ProductController::class)->group(function () {
//     Route::get('/product', 'soLuong');
//     Route::get('/product/{soLuong}', 'soLuong');
// });

// Route::get('/tin-tuc', [HomeController::class, 'index2']);
// Route::post('/tin-tuc', [HomeController::class, 'index']);
