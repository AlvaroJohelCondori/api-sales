<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProductIndexTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 10, 2);
            $table->integer('stock');
            $table->string('image')->nullable();
            $table->timestamps();
        });
    }

    public function test_it_lists_products_with_the_existing_json_contract(): void
    {
        $product = Product::create([
            'name' => 'Mouse',
            'price' => '150.53',
            'stock' => 5,
            'image' => null,
        ]);

        $this->getJson('/api/products')
            ->assertOk()
            ->assertExactJson([[
                'id' => $product->id,
                'name' => 'Mouse',
                'price' => '150.53',
                'stock' => 5,
                'image' => null,
            ]]);
    }

    public function test_it_returns_an_empty_array_when_there_are_no_products(): void
    {
        $this->getJson('/api/products')
            ->assertOk()
            ->assertExactJson([]);
    }
}
