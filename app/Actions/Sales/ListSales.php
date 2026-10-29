<?php

namespace App\Actions\Sales;

use App\Models\Sale;
use Illuminate\Database\Eloquent\Collection;

class ListSales
{
    /** @return Collection<int, Sale> */
    public function handle(): Collection
    {
        return Sale::with('details.product')->orderByDesc('id')->get();
    }
}
