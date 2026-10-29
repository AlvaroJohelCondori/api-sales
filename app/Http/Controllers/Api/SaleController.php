<?php

namespace App\Http\Controllers\Api;

use App\Actions\Sales\CreateSale;
use App\Actions\Sales\DeleteSale;
use App\Actions\Sales\ListSales;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSaleRequest;
use App\Http\Resources\SaleResource;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;

class SaleController extends Controller
{
    public function index(ListSales $listSales): JsonResponse
    {
        return response()->json(SaleResource::collection($listSales->handle()));
    }

    public function store(StoreSaleRequest $request, CreateSale $createSale): JsonResponse
    {
        $sale = $createSale->handle($request->validated());

        return response()->json([
            'message' => 'Venta creada correctamente',
            'sale_id' => $sale->id,
            'total' => $sale->total,
        ], 201);
    }

    public function destroy(Sale $sale, DeleteSale $deleteSale): JsonResponse
    {
        $deleteSale->handle($sale);

        return response()->json(['message' => 'Venta eliminada correctamente']);
    }
}
