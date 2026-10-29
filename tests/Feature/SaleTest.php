<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleDetail;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SaleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])->assertExitCode(0);
        User::factory()->create(['id' => 1]);
    }

    private function product(string $price = '150.53', int $stock = 10): Product
    {
        return Product::create(['name' => 'Producto de prueba', 'price' => $price, 'stock' => $stock]);
    }

    public function test_it_creates_a_sale_with_exact_totals_details_and_stock_changes(): void
    {
        $first = $this->product();
        $second = $this->product('0.10');

        $response = $this->postJson('/api/sales', ['user_id' => 1, 'items' => [
            ['product_id' => $first->id, 'quantity' => 2],
            ['product_id' => $second->id, 'quantity' => 3],
        ]])->assertCreated()->assertJsonPath('total', '301.36');

        $this->assertDatabaseHas('sales', ['id' => $response->json('sale_id'), 'user_id' => 1, 'total' => '301.36']);
        $this->assertDatabaseHas('sale_details', ['product_id' => $first->id, 'quantity' => 2, 'price' => '150.53', 'subtotal' => '301.06']);
        $this->assertDatabaseCount('sale_details', 2);
        $this->assertSame(8, $first->fresh()->stock);
        $this->assertSame(7, $second->fresh()->stock);
    }

    public function test_insufficient_stock_rolls_back_previously_processed_items(): void
    {
        $first = $this->product();
        $second = $this->product('20.00', 0);

        $this->postJson('/api/sales', ['items' => [
            ['product_id' => $first->id, 'quantity' => 2],
            ['product_id' => $second->id, 'quantity' => 1],
        ]])->assertUnprocessable()->assertJsonValidationErrors('items.1.product_id');

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_details', 0);
        $this->assertSame(10, $first->fresh()->stock);
    }

    public function test_unexpected_failure_also_rolls_back_stock_and_records(): void
    {
        $product = $this->product();
        SaleDetail::created(function () {
            throw new \RuntimeException('Simulated persistence failure');
        });

        try {
            $this->postJson('/api/sales', ['items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ]])->assertStatus(500);
        } finally {
            SaleDetail::flushEventListeners();
        }

        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_details', 0);
        $this->assertSame(10, $product->fresh()->stock);
    }

    public static function invalidRequests(): array
    {
        return [
            'missing items' => [[], 'items'],
            'empty items' => [['items' => []], 'items'],
            'missing quantity' => [['items' => [['product_id' => 1]]], 'items.0.quantity'],
            'zero quantity' => [['items' => [['product_id' => 1, 'quantity' => 0]]], 'items.0.quantity'],
            'negative quantity' => [['items' => [['product_id' => 1, 'quantity' => -1]]], 'items.0.quantity'],
            'fractional quantity' => [['items' => [['product_id' => 1, 'quantity' => 1.5]]], 'items.0.quantity'],
            'unknown product' => [['items' => [['product_id' => 9999, 'quantity' => 1]]], 'items.0.product_id'],
            'duplicate products' => [['items' => [['product_id' => 1, 'quantity' => 1], ['product_id' => 1, 'quantity' => 1]]], 'items.0.product_id'],
            'different user' => [['user_id' => 2, 'items' => [['product_id' => 1, 'quantity' => 1]]], 'user_id'],
            'client price' => [['items' => [['product_id' => 1, 'quantity' => 1, 'price' => 0.01]]], 'items.0'],
        ];
    }

    #[DataProvider('invalidRequests')]
    public function test_invalid_requests_do_not_change_the_database(array $payload, string $field): void
    {
        $product = $this->product();
        $this->postJson('/api/sales', $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_details', 0);
        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_it_rejects_amounts_outside_the_database_precision(): void
    {
        $product = $this->product('99999999.99');
        $this->postJson('/api/sales', ['items' => [
            ['product_id' => $product->id, 'quantity' => 2],
        ]])->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->assertDatabaseCount('sales', 0);
        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_it_lists_sales_with_their_product_details(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-10-28 23:46:43', 'UTC'));
        $product = $this->product();
        $product->update(['image' => 'https://example.com/product.jpg']);
        $response = $this->postJson('/api/sales', ['items' => [
            ['product_id' => $product->id, 'quantity' => 2],
        ]])->assertCreated();

        $product->update(['price' => '999.00']);

        $this->getJson('/api/sales')->assertOk()->assertExactJson([[
            'id' => $response->json('sale_id'),
            'user_id' => 1,
            'total' => '301.06',
            'created_at' => '2026-10-28T23:46:43.000Z',
            'details' => [[
                'product_id' => $product->id,
                'product' => $product->name,
                'image' => 'https://example.com/product.jpg',
                'price' => '150.53',
                'quantity' => 2,
                'subtotal' => '301.06',
            ]],
        ]]);
    }

    public function test_sale_details_include_null_for_products_without_an_image(): void
    {
        $product = $this->product();
        $this->postJson('/api/sales', ['items' => [
            ['product_id' => $product->id, 'quantity' => 1],
        ]])->assertCreated();

        $this->getJson('/api/sales')
            ->assertOk()
            ->assertJsonStructure(['*' => ['details' => ['*' => ['image']]]])
            ->assertJsonPath('0.details.0.image', null);
    }

    public function test_it_soft_deletes_sales_preserving_details_and_stock(): void
    {
        $product = $this->product();
        $id = $this->postJson('/api/sales', ['items' => [
            ['product_id' => $product->id, 'quantity' => 2],
        ]])->assertCreated()->json('sale_id');

        $this->deleteJson("/api/sales/$id")->assertOk()->assertExactJson(['message' => 'Venta eliminada correctamente']);
        $this->assertSoftDeleted('sales', ['id' => $id]);
        $this->assertDatabaseCount('sale_details', 1);
        $this->assertSame(8, $product->fresh()->stock);
        $this->getJson('/api/sales')->assertOk()->assertExactJson([]);
        config(['app.debug' => true]);
        $this->deleteJson("/api/sales/$id")
            ->assertNotFound()
            ->assertExactJson(['message' => 'Venta no encontrada.']);
        $this->assertSame(0, Sale::count());
    }

    public function test_deleting_a_missing_sale_returns_json_404(): void
    {
        config(['app.debug' => true]);
        $this->deleteJson('/api/sales/9999')
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson(['message' => 'Venta no encontrada.']);
    }
}
