<?php

namespace Tests\Feature\Http;

use App\Enums\ApiErrorCode;
use App\Enums\DiscountType;
use App\Enums\PromotionStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * End to end behaviour of the promotion endpoints.
 *
 * Mapping domain conflicts to 404 and 409 is a later task, so nothing here
 * asserts those statuses: today a rejected edit still surfaces as a 500.
 */
class PromotionEndpointsTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_listing_returns_both_target_variants_with_the_agreed_shape(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-15'));

        $product = Product::create(['name' => 'Café molido 500g']);
        $category = Category::create(['name' => 'Bebidas']);

        $forProduct = $this->promotion(
            'Promoción de temporada',
            productId: $product->id,
            startDate: '2026-09-01',
            endDate: '2026-09-30',
        );

        $forCategory = $this->promotion(
            'Bebidas de verano',
            categoryId: $category->id,
            discountType: DiscountType::FIXED_AMOUNT,
            discountValue: '1200.50',
            startDate: '2026-08-01',
            endDate: '2026-08-31',
            status: PromotionStatus::FINISHED,
        );

        $response = $this->getJson('/api/promotions');

        $response->assertOk();
        $response->assertExactJson([
            'data' => [
                [
                    'id' => $forProduct->id,
                    'name' => 'Promoción de temporada',
                    'target' => ['type' => 'product', 'id' => $product->id, 'name' => 'Café molido 500g'],
                    'discountType' => 'PERCENTAGE',
                    'discountValue' => '15.00',
                    'startDate' => '2026-09-01',
                    'endDate' => '2026-09-30',
                    'status' => 'SCHEDULED',
                    'validToday' => true,
                ],
                [
                    'id' => $forCategory->id,
                    'name' => 'Bebidas de verano',
                    'target' => ['type' => 'category', 'id' => $category->id, 'name' => 'Bebidas'],
                    'discountType' => 'FIXED_AMOUNT',
                    'discountValue' => '1200.50',
                    'startDate' => '2026-08-01',
                    'endDate' => '2026-08-31',
                    'status' => 'FINISHED',
                    'validToday' => false,
                ],
            ],
        ]);
    }

    /**
     * Both answers come from the same response, so a validity that ignored the
     * row and answered a constant could not pass.
     */
    public function test_valid_today_reflects_the_date_range_of_each_promotion(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-15'));

        $product = Product::create(['name' => 'Café molido 500g']);

        $running = $this->promotion('Vigente', productId: $product->id, startDate: '2026-09-01', endDate: '2026-09-30');
        $future = $this->promotion('Futura', productId: $product->id, startDate: '2026-10-01', endDate: '2026-10-31');

        $response = $this->getJson('/api/promotions');

        $response->assertOk();
        $byId = collect($response->json('data'))->keyBy('id');

        $this->assertTrue($byId[$running->id]['validToday']);
        $this->assertFalse($byId[$future->id]['validToday']);
    }

    public function test_the_summary_returns_the_four_counters(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-15'));

        $product = Product::create(['name' => 'Café molido 500g']);

        $this->promotion('Programada A', productId: $product->id, startDate: '2026-09-01', endDate: '2026-09-30');
        $this->promotion('Programada B', productId: $product->id, startDate: '2026-09-10', endDate: '2026-09-20');
        $this->promotion('Programada C', productId: $product->id, startDate: '2026-11-01', endDate: '2026-11-30');
        $this->promotion('Activa', productId: $product->id, startDate: '2026-09-01', endDate: '2026-09-30', status: PromotionStatus::ACTIVE);
        $this->promotion('Terminada', productId: $product->id, startDate: '2026-07-01', endDate: '2026-07-31', status: PromotionStatus::FINISHED);

        $response = $this->getJson('/api/promotions/summary');

        $response->assertOk();
        $response->assertExactJson([
            'data' => [
                'scheduled' => 3,
                'active' => 1,
                'finished' => 1,
                // Counted by dates alone, so the ACTIVE one joins the two
                // SCHEDULED ranges that cover 2026-09-15.
                'validToday' => 3,
            ],
        ]);
    }

    public function test_creating_a_promotion_persists_it_as_scheduled(): void
    {
        $product = Product::create(['name' => 'Café molido 500g']);

        $response = $this->postJson('/api/promotions', [
            'name' => 'Promoción de temporada',
            'product_id' => $product->id,
            'discount_type' => DiscountType::PERCENTAGE->value,
            'discount_value' => '15',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.name', 'Promoción de temporada');
        $response->assertJsonPath('data.target', ['type' => 'product', 'id' => $product->id, 'name' => 'Café molido 500g']);
        $response->assertJsonPath('data.discountValue', '15.00');
        $response->assertJsonPath('data.startDate', '2026-09-01');
        $response->assertJsonPath('data.status', 'SCHEDULED');

        $this->assertDatabaseHas('promotions', [
            'id' => $response->json('data.id'),
            'name' => 'Promoción de temporada',
            'product_id' => $product->id,
            'category_id' => null,
            'status' => PromotionStatus::SCHEDULED->value,
        ]);
    }

    /**
     * First check that the shared error envelope really reaches a client over
     * HTTP; until routes existed the Form Request could only be exercised
     * directly.
     */
    public function test_an_invalid_creation_payload_answers_the_error_envelope(): void
    {
        $response = $this->postJson('/api/promotions', [
            'name' => '',
            'discount_type' => 'HALF_OFF',
            'discount_value' => '0',
            'start_date' => '2026-09-30',
            'end_date' => '2026-09-01',
        ]);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonPath('error.code', ApiErrorCode::VALIDATION_FAILED->value);
        $response->assertJsonStructure([
            'error' => ['code', 'message', 'fields'],
        ]);
        $response->assertJsonStructure([
            'error' => ['fields' => ['name', 'product_id', 'category_id', 'discount_type', 'discount_value', 'end_date']],
        ]);
        // The framework default shape must not leak alongside ours.
        $response->assertJsonMissingPath('errors');

        $this->assertDatabaseCount('promotions', 0);
    }

    public function test_updating_a_promotion_returns_the_new_values(): void
    {
        $product = Product::create(['name' => 'Café molido 500g']);
        $category = Category::create(['name' => 'Bebidas']);
        $promotion = $this->promotion('Nombre viejo', productId: $product->id);

        $response = $this->patchJson("/api/promotions/{$promotion->id}", [
            'name' => 'Nombre nuevo',
            'category_id' => $category->id,
            'discount_type' => DiscountType::FIXED_AMOUNT->value,
            'discount_value' => '2500.75',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-31',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.id', $promotion->id);
        $response->assertJsonPath('data.name', 'Nombre nuevo');
        $response->assertJsonPath('data.target', ['type' => 'category', 'id' => $category->id, 'name' => 'Bebidas']);
        $response->assertJsonPath('data.discountType', 'FIXED_AMOUNT');
        $response->assertJsonPath('data.discountValue', '2500.75');
        $response->assertJsonPath('data.startDate', '2026-10-01');
        $response->assertJsonPath('data.endDate', '2026-10-31');
        // The update endpoint never moves the lifecycle forward.
        $response->assertJsonPath('data.status', 'SCHEDULED');

        $this->assertDatabaseHas('promotions', [
            'id' => $promotion->id,
            'name' => 'Nombre nuevo',
            'product_id' => null,
            'category_id' => $category->id,
        ]);
    }

    public function test_transitioning_the_status_moves_the_promotion_to_active(): void
    {
        $product = Product::create(['name' => 'Café molido 500g']);
        $promotion = $this->promotion('Programada', productId: $product->id);

        $response = $this->patchJson("/api/promotions/{$promotion->id}/status", [
            'status' => PromotionStatus::ACTIVE->value,
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.id', $promotion->id);
        $response->assertJsonPath('data.status', 'ACTIVE');

        $this->assertDatabaseHas('promotions', [
            'id' => $promotion->id,
            'status' => PromotionStatus::ACTIVE->value,
        ]);
    }

    public function test_an_unknown_status_answers_the_error_envelope(): void
    {
        $product = Product::create(['name' => 'Café molido 500g']);
        $promotion = $this->promotion('Programada', productId: $product->id);

        $response = $this->patchJson("/api/promotions/{$promotion->id}/status", ['status' => 'PAUSED']);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);
        $response->assertJsonPath('error.code', ApiErrorCode::VALIDATION_FAILED->value);
        $response->assertJsonStructure(['error' => ['code', 'message', 'fields' => ['status']]]);

        $this->assertDatabaseHas('promotions', [
            'id' => $promotion->id,
            'status' => PromotionStatus::SCHEDULED->value,
        ]);
    }

    public function test_deleting_a_scheduled_promotion_answers_no_content(): void
    {
        $product = Product::create(['name' => 'Café molido 500g']);
        $promotion = $this->promotion('Programada', productId: $product->id);

        $response = $this->deleteJson("/api/promotions/{$promotion->id}");

        $response->assertNoContent();
        $this->assertSame('', $response->getContent());

        $this->assertDatabaseMissing('promotions', ['id' => $promotion->id]);
    }

    /**
     * The action already eager loads both target relations; this proves the
     * HTTP layer does not undo it while resolving the target and the validity
     * of every row.
     */
    public function test_the_listing_does_not_grow_its_query_count_with_the_rows(): void
    {
        $this->seedPromotionsWithDistinctTargets(4);

        $forFour = $this->countListingQueries();

        $this->seedPromotionsWithDistinctTargets(4);

        $forEight = $this->countListingQueries();

        $this->assertCount(8, Promotion::all());
        $this->assertSame($forFour, $forEight, 'The listing runs one query per row.');
        // One for the promotions, one for the products, one for the categories.
        $this->assertSame(3, $forEight);
    }

    private function countListingQueries(): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson('/api/promotions')->assertOk();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        return count($queries);
    }

    /**
     * Every promotion points at a target of its own: sharing one would let an
     * N+1 hide behind a cache of already loaded models.
     */
    private function seedPromotionsWithDistinctTargets(int $count): void
    {
        for ($index = 0; $index < $count; $index++) {
            $suffix = uniqid();

            $index % 2 === 0
                ? $this->promotion("Producto {$suffix}", productId: Product::create(['name' => "Producto {$suffix}"])->id)
                : $this->promotion("Categoría {$suffix}", categoryId: Category::create(['name' => "Categoría {$suffix}"])->id);
        }
    }

    private function promotion(
        string $name,
        ?int $productId = null,
        ?int $categoryId = null,
        DiscountType $discountType = DiscountType::PERCENTAGE,
        string $discountValue = '15.00',
        string $startDate = '2026-09-01',
        string $endDate = '2026-09-30',
        PromotionStatus $status = PromotionStatus::SCHEDULED,
    ): Promotion {
        return Promotion::create([
            'name' => $name,
            'product_id' => $productId,
            'category_id' => $categoryId,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => $status,
        ]);
    }
}
