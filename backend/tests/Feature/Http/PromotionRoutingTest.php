<?php

namespace Tests\Feature\Http;

use App\Enums\DiscountType;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Router;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Guards the routing table itself, apart from what the endpoints answer.
 */
class PromotionRoutingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The literal segment must win over the parameter, with promotions in the
     * table so the case is the realistic one.
     */
    public function test_the_summary_route_is_not_captured_by_the_model_bound_route(): void
    {
        $product = Product::create(['name' => 'Café molido 500g']);
        $this->promotion('Vigente', productId: $product->id);

        $response = $this->getJson('/api/promotions/summary');

        $response->assertOk();
        $response->assertJsonPath('data.scheduled', 1);
    }

    /**
     * Asserted on the route table rather than on a response, because the
     * hazard is dormant: the router only compares routes of the incoming verb,
     * and today no GET route carries {promotion}, so a reorder would still
     * answer 200. It stops being dormant the moment a `GET
     * promotions/{promotion}` is added, and by then a reorder would be a
     * silent 404. This is the assertion that fails first instead.
     */
    public function test_the_summary_route_is_declared_before_every_model_bound_route(): void
    {
        $uris = [];

        foreach ($this->app->make(Router::class)->getRoutes() as $route) {
            $uris[] = $route->uri();
        }

        $summary = array_search('api/promotions/summary', $uris, true);
        $modelBound = array_keys(array_filter(
            $uris,
            fn (string $uri): bool => str_contains($uri, '{promotion}'),
        ));

        $this->assertNotFalse($summary, 'The summary route is not registered.');
        $this->assertNotEmpty($modelBound, 'No model bound route is registered.');
        $this->assertLessThan(
            min($modelBound),
            $summary,
            'promotions/summary must be declared before any promotions/{promotion} route.',
        );
    }

    public function test_every_endpoint_answers_its_own_method(): void
    {
        $product = Product::create(['name' => 'Café molido 500g']);
        $editable = $this->promotion('Editable', productId: $product->id);
        $removable = $this->promotion('Eliminable', productId: $product->id);

        $accepted = [
            ['GET', '/api/promotions', []],
            ['GET', '/api/promotions/summary', []],
            ['POST', '/api/promotions', $this->payload($product->id)],
            ['PATCH', "/api/promotions/{$editable->id}", $this->payload($product->id)],
            ['PATCH', "/api/promotions/{$editable->id}/status", ['status' => 'ACTIVE']],
            ['DELETE', "/api/promotions/{$removable->id}", []],
        ];

        foreach ($accepted as [$method, $uri, $payload]) {
            $status = $this->json($method, $uri, $payload)->getStatusCode();

            $this->assertNotSame(Response::HTTP_NOT_FOUND, $status, "{$method} {$uri} is not routed");
            $this->assertNotSame(Response::HTTP_METHOD_NOT_ALLOWED, $status, "{$method} {$uri} rejects its own method");
        }
    }

    public function test_no_endpoint_answers_a_method_it_does_not_declare(): void
    {
        $category = Category::create(['name' => 'Bebidas']);
        $promotion = $this->promotion('Intacta', categoryId: $category->id);

        $rejected = [
            // The summary is read only; the collection has no replace.
            // PATCH and DELETE on that same URI are left out on purpose: they
            // do match promotions/{promotion} and are therefore an unknown
            // identifier, not an unsupported method.
            ['POST', '/api/promotions/summary'],
            ['PUT', '/api/promotions'],
            ['DELETE', '/api/promotions'],
            // No single promotion endpoint is exposed, and PUT was never an
            // alias of the partial update.
            ['GET', "/api/promotions/{$promotion->id}"],
            ['PUT', "/api/promotions/{$promotion->id}"],
            // The status sub resource only moves forward through PATCH.
            ['GET', "/api/promotions/{$promotion->id}/status"],
            ['POST', "/api/promotions/{$promotion->id}/status"],
            ['DELETE', "/api/promotions/{$promotion->id}/status"],
        ];

        foreach ($rejected as [$method, $uri]) {
            $this->json($method, $uri)
                ->assertStatus(Response::HTTP_METHOD_NOT_ALLOWED);
        }

        // Nothing above was allowed to touch the row.
        $this->assertDatabaseHas('promotions', ['id' => $promotion->id, 'name' => 'Intacta']);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(int $productId): array
    {
        return [
            'name' => 'Promoción de temporada',
            'product_id' => $productId,
            'discount_type' => DiscountType::PERCENTAGE->value,
            'discount_value' => '15.00',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ];
    }

    private function promotion(string $name, ?int $productId = null, ?int $categoryId = null): Promotion
    {
        return Promotion::create([
            'name' => $name,
            'product_id' => $productId,
            'category_id' => $categoryId,
            'discount_type' => DiscountType::PERCENTAGE,
            'discount_value' => '15.00',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ]);
    }
}
