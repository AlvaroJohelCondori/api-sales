<?php

namespace App\Actions\Sales;

use App\Models\Sale;

class DeleteSale
{
    public function handle(Sale $sale): void
    {
        $sale->delete();
    }
}
