<?php

use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\SaleController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/test', function () {
    return response()->json([
        'app' => config('app.name'),
        'env' => app()->environment(),
        'version' => app()->version(),
        'status' => 'ok',
    ]);
});

Route::get('/products', [ProductController::class, 'index']);
Route::apiResource('sales', SaleController::class)
    ->only(['index', 'store', 'destroy'])
    ->missing(function (Request $request) {
        return response()->json(['message' => 'Venta no encontrada.'], 404);
    });
