<?php

namespace Tests\Feature\Http;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The catalogue as the promotion form receives it over HTTP.
 */
class PromotionTargetEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_both_catalogues_under_the_data_envelope(): void
    {
        $product = Product::factory()->create(['name' => 'Café molido 500g']);
        $category = Category::factory()->create(['name' => 'Bebidas']);

        $this->getJson('/api/promotion-targets')
            ->assertOk()
            // Exact, so the shape the form reads is pinned whole: an extra
            // key anywhere in the payload is a contract change.
            ->assertExactJson([
                'data' => [
                    'products' => [
                        ['id' => $product->id, 'name' => 'Café molido 500g'],
                    ],
                    'categories' => [
                        ['id' => $category->id, 'name' => 'Bebidas'],
                    ],
                ],
            ]);
    }

    public function test_every_entry_carries_exactly_an_id_and_a_name(): void
    {
        Product::factory()->count(2)->create();
        Category::factory()->count(2)->create();

        $data = $this->getJson('/api/promotion-targets')->assertOk()->json('data');

        // Asserted on the decoded keys rather than trusted to the select in
        // the query: narrowing the columns decides what is read, not what is
        // written, and the form has no business receiving a column it did
        // not ask for.
        foreach (['products', 'categories'] as $catalogue) {
            $this->assertNotEmpty($data[$catalogue]);

            foreach ($data[$catalogue] as $entry) {
                $keys = array_keys($entry);
                sort($keys);

                $this->assertSame(['id', 'name'], $keys);
            }
        }
    }

    public function test_empty_tables_answer_two_empty_lists(): void
    {
        // A catalogue with nothing in it is a valid answer, not a missing
        // resource: the form must render its two empty selects, not an error.
        $this->getJson('/api/promotion-targets')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'products' => [],
                    'categories' => [],
                ],
            ]);
    }

    public function test_the_alphabetical_order_survives_the_response(): void
    {
        foreach (['Yogur natural', 'Agua mineral', 'Pan de molde'] as $name) {
            Product::factory()->create(['name' => $name]);
        }

        foreach (['Limpieza', 'Bebidas', 'Snacks'] as $name) {
            Category::factory()->create(['name' => $name]);
        }

        $data = $this->getJson('/api/promotion-targets')->assertOk()->json('data');

        $this->assertSame(
            ['Agua mineral', 'Pan de molde', 'Yogur natural'],
            array_column($data['products'], 'name'),
        );
        $this->assertSame(
            ['Bebidas', 'Limpieza', 'Snacks'],
            array_column($data['categories'], 'name'),
        );
    }
}
