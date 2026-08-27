<?php

namespace Tests\Feature\Actions;

use App\Actions\Promotions\TransitionPromotionStatus;
use App\Enums\DiscountType;
use App\Enums\PromotionStatus;
use App\Exceptions\InvalidStatusTransition;
use App\Exceptions\PromotionIsFinished;
use App\Exceptions\PromotionStateConflict;
use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransitionPromotionStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_activates_a_scheduled_promotion(): void
    {
        $promotion = $this->promotion(PromotionStatus::SCHEDULED);

        $this->action()($promotion, PromotionStatus::ACTIVE);

        $this->assertSame(PromotionStatus::ACTIVE, $promotion->fresh()->status);
    }

    public function test_it_finishes_an_active_promotion(): void
    {
        $promotion = $this->promotion(PromotionStatus::ACTIVE);

        $this->action()($promotion, PromotionStatus::FINISHED);

        $this->assertSame(PromotionStatus::FINISHED, $promotion->fresh()->status);
    }

    public function test_it_rejects_skipping_a_step(): void
    {
        $promotion = $this->promotion(PromotionStatus::SCHEDULED);

        $this->expectException(InvalidStatusTransition::class);

        $this->action()($promotion, PromotionStatus::FINISHED);
    }

    public function test_it_rejects_transitioning_away_from_finished(): void
    {
        $promotion = $this->promotion(PromotionStatus::FINISHED);

        try {
            $this->action()($promotion, PromotionStatus::ACTIVE);
            $this->fail('Expected InvalidStatusTransition to be thrown.');
        } catch (PromotionStateConflict $exception) {
            $this->assertInstanceOf(InvalidStatusTransition::class, $exception);
            $this->assertNotInstanceOf(PromotionIsFinished::class, $exception);
        }
    }

    public function test_it_leaves_the_row_untouched_when_the_transition_is_rejected(): void
    {
        $promotion = $this->promotion(PromotionStatus::SCHEDULED);
        $before = $promotion->fresh()->getAttributes();

        try {
            $this->action()($promotion, PromotionStatus::FINISHED);
            $this->fail('Expected InvalidStatusTransition to be thrown.');
        } catch (InvalidStatusTransition) {
            // Expected: the assertion below checks nothing was written.
        }

        $this->assertSame($before, $promotion->fresh()->getAttributes());
    }

    public function test_a_valid_transition_changes_nothing_but_the_status(): void
    {
        $promotion = $this->promotion(PromotionStatus::SCHEDULED);
        $before = $promotion->fresh()->getAttributes();

        $this->action()($promotion, PromotionStatus::ACTIVE);

        $after = $promotion->fresh()->getAttributes();

        $this->assertSame(PromotionStatus::SCHEDULED->value, $before['status']);
        $this->assertSame(PromotionStatus::ACTIVE->value, $after['status']);

        // Everything else must be byte for byte the same. updated_at is
        // excluded because any write legitimately touches it.
        unset($before['status'], $before['updated_at'], $after['status'], $after['updated_at']);

        $this->assertSame($before, $after);
    }

    public function test_the_invalid_transition_is_catchable_as_a_state_conflict(): void
    {
        $promotion = $this->promotion(PromotionStatus::SCHEDULED);

        try {
            $this->action()($promotion, PromotionStatus::SCHEDULED);
            $this->fail('Expected InvalidStatusTransition to be thrown.');
        } catch (PromotionStateConflict $exception) {
            $this->assertInstanceOf(InvalidStatusTransition::class, $exception);
        }
    }

    /**
     * Resolve the action from the container to prove autowiring needs no
     * manual binding.
     */
    private function action(): TransitionPromotionStatus
    {
        return app(TransitionPromotionStatus::class);
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
}
