<?php

namespace Tests\Feature\Actions;

use App\Actions\Promotions\UpdatePromotion;
use App\DataTransferObjects\UpdatePromotionData;
use App\Domain\Targets\CategoryTarget;
use App\Domain\Targets\ProductTarget;
use App\Domain\Targets\PromotionTarget;
use App\Enums\DiscountType;
use App\Enums\PromotionStatus;
use App\Exceptions\PromotionIsFinished;
use App\Exceptions\PromotionStateConflict;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdatePromotionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_every_field_of_a_scheduled_promotion(): void
    {
        $promotion = $this->promotion(PromotionStatus::SCHEDULED);
        $product = Product::create(['name' => 'Té verde 20 sobres']);

        $this->action()($promotion, new UpdatePromotionData(
            name: 'Promoción renovada',
            target: new ProductTarget($product->id),
            discountType: DiscountType::FIXED_AMOUNT,
            discountValue: '1250.50',
            startDate: CarbonImmutable::parse('2026-10-05'),
            endDate: CarbonImmutable::parse('2026-11-20'),
        ));

        $persisted = $promotion->fresh();

        $this->assertSame('Promoción renovada', $persisted->name);
        $this->assertSame($product->id, $persisted->product_id);
        $this->assertSame(DiscountType::FIXED_AMOUNT, $persisted->discount_type);
        $this->assertSame('1250.50', $persisted->discount_value);
        $this->assertSame('2026-10-05', $persisted->start_date->toDateString());
        $this->assertSame('2026-11-20', $persisted->end_date->toDateString());
    }

    public function test_it_updates_an_active_promotion(): void
    {
        $promotion = $this->promotion(PromotionStatus::ACTIVE);

        $this->action()($promotion, $this->data(new ProductTarget($promotion->product_id)));

        $this->assertSame('Promoción renovada', $promotion->fresh()->name);
    }

    public function test_it_rejects_updating_a_finished_promotion(): void
    {
        $promotion = $this->promotion(PromotionStatus::FINISHED);

        $this->expectException(PromotionIsFinished::class);

        $this->action()($promotion, $this->data(new ProductTarget($promotion->product_id)));
    }

    public function test_it_leaves_a_finished_promotion_untouched_in_the_database(): void
    {
        $promotion = $this->promotion(PromotionStatus::FINISHED);
        $before = $promotion->fresh()->getAttributes();

        try {
            $this->action()($promotion, $this->data(new CategoryTarget(
                Category::create(['name' => 'Lácteos'])->id
            )));
            $this->fail('Expected PromotionIsFinished to be thrown.');
        } catch (PromotionIsFinished) {
            // Expected: the assertions below check nothing was written.
        }

        $this->assertSame($before, $promotion->fresh()->getAttributes());
    }

    public function test_it_can_switch_the_target_from_product_to_category(): void
    {
        $promotion = $this->promotion(PromotionStatus::SCHEDULED);
        $this->assertNotNull($promotion->product_id);

        $category = Category::create(['name' => 'Lácteos']);

        $this->action()($promotion, $this->data(new CategoryTarget($category->id)));

        $persisted = $promotion->fresh();

        $this->assertSame($category->id, $persisted->category_id);
        $this->assertNull($persisted->product_id);
    }

    public function test_it_does_not_change_the_status(): void
    {
        $promotion = $this->promotion(PromotionStatus::ACTIVE);

        $this->action()($promotion, $this->data(new ProductTarget($promotion->product_id)));

        $this->assertSame(PromotionStatus::ACTIVE, $promotion->fresh()->status);
    }

    public function test_the_finished_conflict_is_catchable_as_a_state_conflict(): void
    {
        $promotion = $this->promotion(PromotionStatus::FINISHED);

        try {
            $this->action()($promotion, $this->data(new ProductTarget($promotion->product_id)));
            $this->fail('Expected PromotionIsFinished to be thrown.');
        } catch (PromotionStateConflict $exception) {
            $this->assertInstanceOf(PromotionIsFinished::class, $exception);
        }
    }

    /**
     * Resolve the action from the container to prove autowiring needs no
     * manual binding.
     */
    private function action(): UpdatePromotion
    {
        return app(UpdatePromotion::class);
    }

    /**
     * An existing promotion targeting a product, in the given status.
     */
    private function promotion(PromotionStatus $status): Promotion
    {
        $product = Product::create(['name' => 'Café molido 500g']);

        $promotion = Promotion::create([
            'name' => 'Promoción original',
            'product_id' => $product->id,
            'category_id' => null,
            'discount_type' => DiscountType::PERCENTAGE,
            'discount_value' => '15.00',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'status' => $status,
        ]);

        return $promotion->fresh();
    }

    /**
     * New values that satisfy every CHECK constraint on the table.
     */
    private function data(PromotionTarget $target): UpdatePromotionData
    {
        return new UpdatePromotionData(
            name: 'Promoción renovada',
            target: $target,
            discountType: DiscountType::FIXED_AMOUNT,
            discountValue: '1250.50',
            startDate: CarbonImmutable::parse('2026-10-05'),
            endDate: CarbonImmutable::parse('2026-11-20'),
        );
    }
}
