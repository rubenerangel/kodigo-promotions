<?php

namespace Tests\Feature\Actions;

use App\Actions\Promotions\DeletePromotion;
use App\Enums\DiscountType;
use App\Enums\PromotionStatus;
use App\Exceptions\PromotionIsFinished;
use App\Exceptions\PromotionIsNotScheduled;
use App\Exceptions\PromotionStateConflict;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeletePromotionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_a_scheduled_promotion(): void
    {
        $promotion = $this->promotion(PromotionStatus::SCHEDULED);

        $this->action()($promotion);

        $this->assertDatabaseMissing('promotions', ['id' => $promotion->id]);
        $this->assertNull($promotion->fresh());
    }

    public function test_it_rejects_deleting_an_active_promotion(): void
    {
        $promotion = $this->promotion(PromotionStatus::ACTIVE);

        $this->expectException(PromotionIsNotScheduled::class);

        $this->action()($promotion);
    }

    public function test_it_rejects_deleting_a_finished_promotion_with_its_own_exception(): void
    {
        $promotion = $this->promotion(PromotionStatus::FINISHED);

        try {
            $this->action()($promotion);
            $this->fail('Expected PromotionIsNotScheduled to be thrown.');
        } catch (PromotionStateConflict $exception) {
            $this->assertInstanceOf(PromotionIsNotScheduled::class, $exception);
            $this->assertNotInstanceOf(PromotionIsFinished::class, $exception);
        }
    }

    public function test_it_leaves_the_row_in_place_when_the_deletion_is_rejected(): void
    {
        $promotion = $this->promotion(PromotionStatus::ACTIVE);
        $before = $promotion->fresh()->getAttributes();

        try {
            $this->action()($promotion);
            $this->fail('Expected PromotionIsNotScheduled to be thrown.');
        } catch (PromotionIsNotScheduled) {
            // Expected: the assertions below check the row survived untouched.
        }

        $this->assertDatabaseHas('promotions', ['id' => $promotion->id]);
        $this->assertSame($before, $promotion->fresh()->getAttributes());
    }

    public function test_deleting_a_promotion_keeps_its_product(): void
    {
        $promotion = $this->promotion(PromotionStatus::SCHEDULED);
        $productId = $promotion->product_id;

        $this->action()($promotion);

        $this->assertDatabaseHas('products', ['id' => $productId]);
    }

    public function test_deleting_a_promotion_keeps_its_category(): void
    {
        $category = Category::create(['name' => 'Bebidas']);
        $promotion = $this->promotion(PromotionStatus::SCHEDULED, categoryId: $category->id);

        $this->action()($promotion);

        $this->assertDatabaseMissing('promotions', ['id' => $promotion->id]);
        $this->assertDatabaseHas('categories', ['id' => $category->id]);
    }

    public function test_the_not_scheduled_conflict_is_catchable_as_a_state_conflict(): void
    {
        $promotion = $this->promotion(PromotionStatus::ACTIVE);

        try {
            $this->action()($promotion);
            $this->fail('Expected PromotionIsNotScheduled to be thrown.');
        } catch (PromotionStateConflict $exception) {
            $this->assertInstanceOf(PromotionIsNotScheduled::class, $exception);
        }
    }

    /**
     * Resolve the action from the container to prove autowiring needs no
     * manual binding.
     */
    private function action(): DeletePromotion
    {
        return app(DeletePromotion::class);
    }

    /**
     * An existing promotion in the given status, targeting a product unless a
     * category is supplied.
     */
    private function promotion(PromotionStatus $status, ?int $categoryId = null): Promotion
    {
        $productId = $categoryId === null
            ? Product::create(['name' => 'Café molido 500g'])->id
            : null;

        $promotion = Promotion::create([
            'name' => 'Promoción original',
            'product_id' => $productId,
            'category_id' => $categoryId,
            'discount_type' => DiscountType::PERCENTAGE,
            'discount_value' => '15.00',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-30',
            'status' => $status,
        ]);

        return $promotion->fresh();
    }
}
