<?php

namespace App\Http\Controllers\Api;

use App\Actions\Products\ListProducts;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function index(ListProducts $listProducts): JsonResponse
    {
        return response()->json(ProductResource::collection($listProducts->handle()));
    }
}
