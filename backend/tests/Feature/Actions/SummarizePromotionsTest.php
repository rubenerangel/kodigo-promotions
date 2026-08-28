<?php

namespace Tests\Feature\Actions;

use App\Actions\Promotions\SummarizePromotions;
use App\DataTransferObjects\PromotionSummary;
use App\Enums\DiscountType;
use App\Enums\PromotionStatus;
use App\Models\Product;
use App\Models\Promotion;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SummarizePromotionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_counts_promotions_of_every_status(): void
    {
        // Frozen inside the seeded date range, so validToday does not depend
        // on the day the suite happens to run.
        $this->travelTo(CarbonImmutable::parse('2026-09-15'));

        // Deliberately different amounts so a swapped counter is visible.
        $this->promotions(PromotionStatus::SCHEDULED, 3);
        $this->promotions(PromotionStatus::ACTIVE, 2);
        $this->promotions(PromotionStatus::FINISHED, 1);

        $summary = $this->action()();

        $this->assertInstanceOf(PromotionSummary::class, $summary);
        $this->assertSame(3, $summary->scheduled);
        $this->assertSame(2, $summary->active);
        $this->assertSame(1, $summary->finished);

        // Every seeded promotion runs on the frozen date, across all three
        // statuses: the validity counter overlaps them rather than splitting
        // them.
        $this->assertSame(6, $summary->validToday);
    }

    public function test_a_status_without_promotions_counts_zero_instead_of_being_omitted(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-09-15'));

        $this->promotions(PromotionStatus::ACTIVE, 2);

        $summary = $this->action()();

        $this->assertSame(0, $summary->scheduled);
        $this->assertSame(2, $summary->active);
        $this->assertSame(0, $summary->finished);
        $this->assertSame(2, $summary->validToday);
    }

    public function test_every_counter_is_zero_when_there_are_no_promotions(): void
    {
        $summary = $this->action()();

        $this->assertSame(0, $summary->scheduled);
        $this->assertSame(0, $summary->active);
        $this->assertSame(0, $summary->finished);
        $this->assertSame(0, $summary->validToday);
    }

    public function test_it_resolves_the_summary_in_a_single_query(): void
    {
        $this->promotions(PromotionStatus::SCHEDULED, 2);
        $this->promotions(PromotionStatus::ACTIVE, 2);
        $this->promotions(PromotionStatus::FINISHED, 2);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->action()();

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // One aggregate query. Counting in PHP or one query per status would
        // show up here.
        $this->assertCount(1, $queries);
    }

    /**
     * Resolve the action from the container to prove autowiring needs no
     * manual binding.
     */
    private function action(): SummarizePromotions
    {
        return app(SummarizePromotions::class);
    }

    private function promotions(PromotionStatus $status, int $amount): void
    {
        $product = Product::create(['name' => 'Café molido '.$status->value]);

        for ($index = 0; $index < $amount; $index++) {
            Promotion::create([
                'name' => $status->value.' '.$index,
                'product_id' => $product->id,
                'category_id' => null,
                'discount_type' => DiscountType::PERCENTAGE,
                'discount_value' => '15.00',
                'start_date' => '2026-09-01',
                'end_date' => '2026-09-30',
                'status' => $status,
            ]);
        }
    }
}
