<?php

namespace Tests\Feature\Actions;

use App\Actions\Promotions\ListPromotions;
use App\Enums\DiscountType;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ListPromotionsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Model::preventLazyLoading(false);

        parent::tearDown();
    }

    public function test_it_returns_promotions_of_both_target_variants(): void
    {
        $product = Product::create(['name' => 'Café molido 500g']);
        $category = Category::create(['name' => 'Bebidas']);

        $this->promotion('Para producto', '2026-09-01', productId: $product->id);
        $this->promotion('Para categoría', '2026-09-02', categoryId: $category->id);

        $promotions = $this->action()();

        $this->assertCount(2, $promotions);
        $this->assertEqualsCanonicalizing(
            ['Para producto', 'Para categoría'],
            $promotions->pluck('name')->all(),
        );
    }

    public function test_it_resolves_the_target_of_each_promotion(): void
    {
        $product = Product::create(['name' => 'Café molido 500g']);
        $category = Category::create(['name' => 'Bebidas']);

        $this->promotion('Para producto', '2026-09-02', productId: $product->id);
        $this->promotion('Para categoría', '2026-09-01', categoryId: $category->id);

        $promotions = $this->action()()->keyBy('name');

        $this->assertInstanceOf(Product::class, $promotions['Para producto']->target);
        $this->assertSame($product->id, $promotions['Para producto']->target->id);

        $this->assertInstanceOf(Category::class, $promotions['Para categoría']->target);
        $this->assertSame($category->id, $promotions['Para categoría']->target->id);
    }

    public function test_walking_the_whole_list_runs_no_extra_queries(): void
    {
        $this->seedThreePromotions();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $promotions = $this->action()();

        foreach ($promotions as $promotion) {
            $this->assertNotNull($promotion->target);
        }

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // One for the promotions, one for the products, one for the
        // categories, regardless of how many rows come back.
        $this->assertCount(3, $queries);
    }

    public function test_reading_the_target_never_lazy_loads(): void
    {
        $this->seedThreePromotions();

        Model::preventLazyLoading();

        $promotions = $this->action()();

        // Would raise LazyLoadingViolationException if a relation were missing.
        foreach ($promotions as $promotion) {
            $this->assertNotNull($promotion->target);
        }

        $this->assertCount(4, $promotions);
    }

    public function test_it_orders_by_start_date_descending_then_id_descending(): void
    {
        $product = Product::create(['name' => 'Café molido 500g']);

        $first = $this->promotion('Septiembre', '2026-09-01', productId: $product->id);
        $second = $this->promotion('Octubre A', '2026-10-01', productId: $product->id);
        $third = $this->promotion('Octubre B', '2026-10-01', productId: $product->id);
        $fourth = $this->promotion('Agosto', '2026-08-01', productId: $product->id);

        $ids = $this->action()()->pluck('id')->all();

        // 2026-10-01 first, and between the two that share it the newer id
        // wins; then September, then August.
        $this->assertSame([$third->id, $second->id, $first->id, $fourth->id], $ids);
    }

    public function test_it_returns_an_empty_collection_when_there_are_no_promotions(): void
    {
        $promotions = $this->action()();

        $this->assertCount(0, $promotions);
        $this->assertTrue($promotions->isEmpty());
    }

    /**
     * Resolve the action from the container to prove autowiring needs no
     * manual binding.
     */
    private function action(): ListPromotions
    {
        return app(ListPromotions::class);
    }

    /**
     * Three promotions mixing both target variants.
     */
    private function seedThreePromotions(): void
    {
        // Distinct targets on purpose: sharing one would let an N+1 hide
        // behind fewer queries than there are rows.
        $this->promotion('Uno', '2026-09-01', productId: Product::create(['name' => 'Café molido 500g'])->id);
        $this->promotion('Dos', '2026-09-02', productId: Product::create(['name' => 'Pan integral'])->id);
        $this->promotion('Tres', '2026-09-03', categoryId: Category::create(['name' => 'Bebidas'])->id);
        $this->promotion('Cuatro', '2026-09-04', categoryId: Category::create(['name' => 'Lácteos'])->id);
    }

    private function promotion(
        string $name,
        string $startDate,
        ?int $productId = null,
        ?int $categoryId = null,
    ): Promotion {
        return Promotion::create([
            'name' => $name,
            'product_id' => $productId,
            'category_id' => $categoryId,
            'discount_type' => DiscountType::PERCENTAGE,
            'discount_value' => '15.00',
            'start_date' => $startDate,
            'end_date' => '2026-12-31',
        ]);
    }
}
