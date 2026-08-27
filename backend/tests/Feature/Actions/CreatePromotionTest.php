<?php

namespace Tests\Feature\Actions;

use App\Actions\Promotions\CreatePromotion;
use App\DataTransferObjects\CreatePromotionData;
use App\Domain\Targets\CategoryTarget;
use App\Domain\Targets\ProductTarget;
use App\Domain\Targets\PromotionTarget;
use App\Enums\DiscountType;
use App\Enums\PromotionStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreatePromotionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_promotion_for_a_product_target(): void
    {
        $product = Product::create(['name' => 'Café molido 500g']);

        $promotion = $this->action()($this->data(new ProductTarget($product->id)));

        $persisted = $promotion->fresh();

        $this->assertSame($product->id, $persisted->product_id);
        $this->assertNull($persisted->category_id);
    }

    public function test_it_creates_a_promotion_for_a_category_target(): void
    {
        $category = Category::create(['name' => 'Bebidas']);

        $promotion = $this->action()($this->data(new CategoryTarget($category->id)));

        $persisted = $promotion->fresh();

        $this->assertSame($category->id, $persisted->category_id);
        $this->assertNull($persisted->product_id);
    }

    public function test_it_always_starts_a_product_promotion_as_scheduled(): void
    {
        $product = Product::create(['name' => 'Café molido 500g']);

        $promotion = $this->action()($this->data(new ProductTarget($product->id)));

        $this->assertSame(PromotionStatus::SCHEDULED, $promotion->fresh()->status);
    }

    public function test_it_always_starts_a_category_promotion_as_scheduled(): void
    {
        $category = Category::create(['name' => 'Bebidas']);

        $promotion = $this->action()($this->data(new CategoryTarget($category->id)));

        $this->assertSame(PromotionStatus::SCHEDULED, $promotion->fresh()->status);
    }

    public function test_it_persists_every_remaining_field(): void
    {
        $product = Product::create(['name' => 'Café molido 500g']);

        $promotion = $this->action()(new CreatePromotionData(
            name: 'Promoción de temporada',
            target: new ProductTarget($product->id),
            discountType: DiscountType::FIXED_AMOUNT,
            discountValue: '2500.75',
            startDate: CarbonImmutable::parse('2026-09-01'),
            endDate: CarbonImmutable::parse('2026-09-30'),
        ));

        $persisted = $promotion->fresh();

        $this->assertSame('Promoción de temporada', $persisted->name);
        $this->assertInstanceOf(DiscountType::class, $persisted->discount_type);
        $this->assertSame(DiscountType::FIXED_AMOUNT, $persisted->discount_type);
        $this->assertSame('2500.75', $persisted->discount_value);
        $this->assertSame('2026-09-01', $persisted->start_date->toDateString());
        $this->assertSame('2026-09-30', $persisted->end_date->toDateString());
    }

    public function test_it_returns_a_promotion_that_exists_in_the_database(): void
    {
        $product = Product::create(['name' => 'Café molido 500g']);

        $promotion = $this->action()($this->data(new ProductTarget($product->id)));

        $this->assertInstanceOf(Promotion::class, $promotion);
        $this->assertTrue($promotion->exists);

        $this->assertDatabaseHas('promotions', [
            'id' => $promotion->id,
            'name' => 'Promoción de temporada',
            'product_id' => $product->id,
            'category_id' => null,
            'discount_type' => DiscountType::PERCENTAGE->value,
            'status' => PromotionStatus::SCHEDULED->value,
        ]);

        $this->assertNotNull($promotion->fresh());
    }

    /**
     * Resolve the action from the container to prove autowiring needs no
     * manual binding.
     */
    private function action(): CreatePromotion
    {
        return app(CreatePromotion::class);
    }

    /**
     * Valid input that satisfies every CHECK constraint on the table.
     */
    private function data(PromotionTarget $target): CreatePromotionData
    {
        return new CreatePromotionData(
            name: 'Promoción de temporada',
            target: $target,
            discountType: DiscountType::PERCENTAGE,
            discountValue: '15.00',
            startDate: CarbonImmutable::parse('2026-09-01'),
            endDate: CarbonImmutable::parse('2026-09-30'),
        );
    }
}
