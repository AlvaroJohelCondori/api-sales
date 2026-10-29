<?php

namespace App\Actions\Sales;

use App\Models\Product;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CreateSale
{
    public function handle(array $data): Sale
    {
        return DB::transaction(function () use ($data) {
            if (! User::whereKey(1)->exists()) {
                throw ValidationException::withMessages(['user_id' => 'El usuario de prueba no existe.']);
            }

            // Lock in a consistent order to reduce deadlocks between simultaneous sales.
            $products = Product::query()
                ->whereIn('id', array_column($data['items'], 'product_id'))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $sale = Sale::create(['user_id' => 1, 'total' => '0.00']);
            $totalCents = 0;

            foreach ($data['items'] as $index => $item) {
                $product = $products->get($item['product_id']);
                $quantity = (int) $item['quantity'];

                if (! $product || $product->stock < $quantity) {
                    throw ValidationException::withMessages([
                        "items.$index.product_id" => 'El producto no existe o no tiene stock suficiente.',
                    ]);
                }

                // Work in integer cents to avoid floating-point rounding errors.
                $priceCents = (int) str_replace('.', '', $product->price);
                $remainingCents = 9999999999 - $totalCents;

                if ($priceCents < 0 || $priceCents > intdiv($remainingCents, $quantity)) {
                    throw ValidationException::withMessages([
                        'items' => 'El importe de la venta excede el límite permitido.',
                    ]);
                }

                $subtotalCents = $priceCents * $quantity;
                $totalCents += $subtotalCents;

                $sale->details()->create([
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'price' => $product->price,
                    'subtotal' => $this->decimal($subtotalCents),
                ]);

                $product->decrement('stock', $quantity);
            }

            $sale->update(['total' => $this->decimal($totalCents)]);

            return $sale;
        }, 3);
    }

    private function decimal(int $cents): string
    {
        return intdiv($cents, 100).'.'.str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }
}
