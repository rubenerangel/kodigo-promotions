<?php

namespace Tests\Feature\Models;

use App\Enums\DiscountType;
use App\Enums\PromotionStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PromotionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_casts_the_discount_type_to_an_enum(): void
    {
        $promotion = $this->promotionForProduct(['discount_type' => DiscountType::FIXED_AMOUNT->value]);

        $this->assertInstanceOf(DiscountType::class, $promotion->discount_type);
        $this->assertSame(DiscountType::FIXED_AMOUNT, $promotion->discount_type);
    }

    public function test_it_casts_the_status_to_an_enum_and_defaults_to_scheduled(): void
    {
        $promotion = $this->promotionForProduct();

        $this->assertArrayNotHasKey('status', $promotion->getAttributes());

        // PostgreSQL applies the column default, so read the persisted row back.
        $persisted = $promotion->fresh();

        $this->assertInstanceOf(PromotionStatus::class, $persisted->status);
        $this->assertSame(PromotionStatus::SCHEDULED, $persisted->status);
    }

    public function test_it_casts_the_dates_to_date_objects(): void
    {
        $promotion = $this->promotionForProduct()->fresh();

        $this->assertInstanceOf(Carbon::class, $promotion->start_date);
        $this->assertInstanceOf(Carbon::class, $promotion->end_date);
        $this->assertSame('2026-09-01', $promotion->start_date->toDateString());
        $this->assertSame('2026-09-30', $promotion->end_date->toDateString());
    }

    public function test_it_resolves_a_product_target(): void
    {
        $product = Product::create(['name' => 'Cafe molido']);

        $promotion = Promotion::create($this->attributes(['product_id' => $product->id]))->fresh();

        $this->assertInstanceOf(Product::class, $promotion->product);
        $this->assertSame($product->id, $promotion->product->id);
        $this->assertNull($promotion->category);

        $this->assertInstanceOf(Product::class, $promotion->target);
        $this->assertSame($product->id, $promotion->target->id);
    }

    public function test_it_resolves_a_category_target(): void
    {
        $category = Category::create(['name' => 'Bebidas']);

        $promotion = Promotion::create($this->attributes(['category_id' => $category->id]))->fresh();

        $this->assertInstanceOf(Category::class, $promotion->category);
        $this->assertSame($category->id, $promotion->category->id);
        $this->assertNull($promotion->product);

        $this->assertInstanceOf(Category::class, $promotion->target);
        $this->assertSame($category->id, $promotion->target->id);
    }

    public function test_a_product_exposes_its_promotions(): void
    {
        $product = Product::create(['name' => 'Cafe molido']);
        $promotion = Promotion::create($this->attributes(['product_id' => $product->id]));

        $promotions = $product->promotions()->get();

        $this->assertCount(1, $promotions);
        $this->assertSame($promotion->id, $promotions->first()->id);
    }

    public function test_a_category_exposes_its_promotions(): void
    {
        $category = Category::create(['name' => 'Bebidas']);
        $promotion = Promotion::create($this->attributes(['category_id' => $category->id]));

        $promotions = $category->promotions()->get();

        $this->assertCount(1, $promotions);
        $this->assertSame($promotion->id, $promotions->first()->id);
    }

    /**
     * Create a promotion targeting a freshly created product.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function promotionForProduct(array $overrides = []): Promotion
    {
        $product = Product::create(['name' => 'Cafe molido']);

        return Promotion::create($this->attributes(['product_id' => $product->id] + $overrides));
    }

    /**
     * Attributes that satisfy every CHECK constraint on the promotions table.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function attributes(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Promocion de prueba',
            'product_id' => null,
            'category_id' => null,
            'discount_type' => DiscountType::PERCENTAGE->value,
            'discount_value' => 15,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
        ];
    }
}
