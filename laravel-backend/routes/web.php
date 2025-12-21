<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['Laravel' => app()->version()];
});

// Route::get('/generate-pdf', [PdfController::class, 'generatePDF']);

