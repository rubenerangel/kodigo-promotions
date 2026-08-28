<?php

namespace Tests\Feature\Domain;

use App\Domain\PromotionValidity;
use App\Enums\DiscountType;
use App\Enums\PromotionStatus;
use App\Models\Product;
use App\Models\Promotion;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromotionValidityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_promotion_starting_today_is_valid(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-01'));

        $promotion = $this->promotion('2026-09-01', '2026-09-30');

        $this->assertTrue($this->validity()->isValidToday($promotion));
    }

    public function test_a_promotion_ending_today_is_valid(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-30'));

        $promotion = $this->promotion('2026-09-01', '2026-09-30');

        $this->assertTrue($this->validity()->isValidToday($promotion));
    }

    public function test_the_day_before_the_start_is_not_valid(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-31'));

        $promotion = $this->promotion('2026-09-01', '2026-09-30');

        $this->assertFalse($this->validity()->isValidToday($promotion));
    }

    public function test_the_day_after_the_end_is_not_valid(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-01'));

        $promotion = $this->promotion('2026-09-01', '2026-09-30');

        $this->assertFalse($this->validity()->isValidToday($promotion));
    }

    public function test_a_single_day_promotion_covering_today_is_valid(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-15'));

        // Not persisted on purpose: promotions_date_range_check requires
        // end_date > start_date, so a single day promotion cannot reach the
        // database. The calculator is still exercised on the degenerate range
        // to prove the comparison is inclusive on both ends at once.
        $promotion = new Promotion([
            'start_date' => '2026-09-15',
            'end_date' => '2026-09-15',
        ]);

        $this->assertTrue($this->validity()->isValidToday($promotion));
    }

    public function test_a_finished_promotion_still_covering_today_is_valid(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-15'));

        $promotion = $this->promotion('2026-09-01', '2026-09-30', PromotionStatus::FINISHED);

        // Validity is a matter of dates alone: the persisted status is
        // deliberately ignored.
        $this->assertTrue($this->validity()->isValidToday($promotion));
    }

    public function test_an_active_promotion_outside_its_dates_is_not_valid(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-15'));

        $promotion = $this->promotion('2026-09-01', '2026-09-30', PromotionStatus::ACTIVE);

        $this->assertFalse($this->validity()->isValidToday($promotion));
    }

    private function validity(): PromotionValidity
    {
        return app(PromotionValidity::class);
    }

    private function promotion(
        string $startDate,
        string $endDate,
        PromotionStatus $status = PromotionStatus::SCHEDULED,
    ): Promotion {
        $product = Product::create(['name' => 'Café molido 500g']);

        return Promotion::create([
            'name' => 'Promoción de temporada',
            'product_id' => $product->id,
            'category_id' => null,
            'discount_type' => DiscountType::PERCENTAGE,
            'discount_value' => '15.00',
            'start_date' => $startDate,
            'end_date' => $endDate,
            'status' => $status,
        ]);
    }
}
