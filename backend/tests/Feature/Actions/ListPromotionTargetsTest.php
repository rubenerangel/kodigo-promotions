<?php

namespace Tests\Feature\Actions;

use App\Actions\Promotions\ListPromotionTargets;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ListPromotionTargetsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_every_product_and_every_category(): void
    {
        Product::factory()->count(3)->create();
        Category::factory()->count(2)->create();

        $catalog = $this->action()();

        $this->assertCount(3, $catalog->products);
        $this->assertCount(2, $catalog->categories);
    }

    /**
     * Names are pinned instead of left to the factory, whose sequence would
     * hand out alphabetically ascending names on its own and let an unordered
     * query pass this assertion.
     */
    public function test_it_orders_both_catalogues_by_name_ascending(): void
    {
        foreach (['Yogur natural', 'Agua mineral', 'Pan de molde'] as $name) {
            Product::factory()->create(['name' => $name]);
        }

        // Accented names on purpose. Under the database default collation the
        // comparison is made on bytes, where an accented letter encodes above
        // every unaccented one, so Lácteos would land after Limpieza and this
        // assertion would fail. Without an accent anywhere in the fixture the
        // Spanish collation could be dropped and nothing here would notice.
        foreach (['Limpieza', 'Lácteos', 'Bebidas', 'Snacks'] as $name) {
            Category::factory()->create(['name' => $name]);
        }

        $catalog = $this->action()();

        // The whole sequence, not a subset: a partial assertion would hold
        // even if only the first entry happened to land in place.
        $this->assertSame(
            ['Agua mineral', 'Pan de molde', 'Yogur natural'],
            $catalog->products->pluck('name')->all(),
        );
        $this->assertSame(
            ['Bebidas', 'Lácteos', 'Limpieza', 'Snacks'],
            $catalog->categories->pluck('name')->all(),
        );
    }

    public function test_it_resolves_the_whole_catalogue_in_two_queries(): void
    {
        Product::factory()->count(4)->create();
        Category::factory()->count(4)->create();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $catalog = $this->action()();

        // Touched on purpose: a lazily resolved collection would otherwise
        // move its query outside the window being measured.
        $this->assertCount(4, $catalog->products);
        $this->assertCount(4, $catalog->categories);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // One per table, no matter how many rows come back. This is what
        // stops anyone from rewriting the catalogue as a query inside a loop.
        $this->assertCount(2, $queries);
    }

    public function test_empty_tables_yield_two_empty_collections(): void
    {
        $catalog = $this->action()();

        $this->assertTrue($catalog->products->isEmpty());
        $this->assertTrue($catalog->categories->isEmpty());
    }

    /**
     * Resolve the action from the container to prove autowiring needs no
     * manual binding.
     */
    private function action(): ListPromotionTargets
    {
        return app(ListPromotionTargets::class);
    }
}
