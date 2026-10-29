<?php

namespace App\Actions\Products;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

class ListProducts
{
    /**
     * @return Collection<int, Product>
     */
    public function handle(): Collection
    {
        return Product::query()
            ->select(['id', 'name', 'price', 'stock', 'image'])
            ->get();
    }
}
