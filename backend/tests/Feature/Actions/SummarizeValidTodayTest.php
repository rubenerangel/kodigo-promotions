<?php

namespace Tests\Feature\Actions;

use App\Actions\Promotions\ListPromotions;
use App\Actions\Promotions\SummarizePromotions;
use App\Domain\PromotionValidity;
use App\Enums\DiscountType;
use App\Enums\PromotionStatus;
use App\Models\Product;
use App\Models\Promotion;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The validity rule is expressed twice: in PHP inside PromotionValidity, and
 * in SQL inside the summary. Two expressions of one rule drift apart sooner or
 * later, and they drift at the boundaries, where nobody looks.
 *
 * These tests pin both against the same boundary dates and require them to
 * agree, so touching one without the other fails the build.
 */
class SummarizeValidTodayTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sql_counter_matches_the_php_calculation_on_the_boundaries(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-15'));
        $this->boundaryPromotions();

        $validity = app(PromotionValidity::class);

        $countedInPhp = app(ListPromotions::class)()
            ->filter(fn (Promotion $promotion) => $validity->isValidToday($promotion))
            ->count();

        $summary = app(SummarizePromotions::class)();

        $this->assertSame($countedInPhp, $summary->validToday);
    }

    public function test_the_boundary_set_counts_exactly_three_promotions(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-15'));
        $this->boundaryPromotions();

        // Starting today, ending today and spanning today are valid; ended
        // yesterday and starting tomorrow are not. Asserting the absolute
        // number keeps the comparison test above from passing because both
        // sides are wrong in the same way.
        $this->assertSame(3, app(SummarizePromotions::class)()->validToday);
    }

    public function test_validity_ignores_the_status(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-15'));

        $this->promotion('2026-09-01', '2026-09-30', PromotionStatus::SCHEDULED);
        $this->promotion('2026-09-01', '2026-09-30', PromotionStatus::ACTIVE);
        $this->promotion('2026-09-01', '2026-09-30', PromotionStatus::FINISHED);

        $summary = app(SummarizePromotions::class)();

        // The counter overlaps the status counters instead of partitioning
        // them: every one of the three is running today.
        $this->assertSame(3, $summary->validToday);
        $this->assertSame(1, $summary->scheduled);
        $this->assertSame(1, $summary->active);
        $this->assertSame(1, $summary->finished);
    }

    public function test_no_promotion_is_valid_when_none_covers_today(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-12-25'));

        $this->promotion('2026-09-01', '2026-09-30');

        $this->assertSame(0, app(SummarizePromotions::class)()->validToday);
    }

    /**
     * Five promotions placed around a frozen today of 2026-09-15.
     */
    private function boundaryPromotions(): void
    {
        $this->promotion('2026-09-15', '2026-09-30');   // starts today
        $this->promotion('2026-09-01', '2026-09-15');   // ends today
        $this->promotion('2026-09-01', '2026-09-14');   // ended yesterday
        $this->promotion('2026-09-16', '2026-09-30');   // starts tomorrow
        $this->promotion('2026-09-01', '2026-09-30');   // spans today
    }

    private function promotion(
        string $startDate,
        string $endDate,
        PromotionStatus $status = PromotionStatus::SCHEDULED,
    ): Promotion {
        $product = Product::create(['name' => 'Café molido '.$startDate.$endDate.$status->value]);

        return Promotion::create([
            'name' => 'Promoción '.$startDate,
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
