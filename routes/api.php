<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ProductController;

Route::get('/test', function () {
  return response()->json([
    'app' => config('app.name'),
    'env' => app()->environment(),
    'version' => app()->version(),
    'status' => 'ok',
  ]);
});

Route::get('/products', [ProductController::class, 'index']);