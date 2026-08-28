<?php

namespace App\Actions\Promotions;

use App\DataTransferObjects\PromotionSummary;
use App\Enums\PromotionStatus;
use App\Models\Promotion;
use Carbon\CarbonImmutable;

/**
 * Counts promotions per status, plus how many are running today.
 */
final readonly class SummarizePromotions
{
    public function __construct(private Promotion $promotion) {}

    /**
     * Totals for every status, resolved in a single aggregate query.
     *
     * The validity counter rides along in the same query with a conditional
     * count, so adding it does not cost a second round trip. It ignores the
     * status on purpose: a promotion running today counts regardless of the
     * status it was left in.
     */
    public function __invoke(): PromotionSummary
    {
        $today = CarbonImmutable::today()->toDateString();

        $rows = $this->promotion->newQuery()
            ->toBase()
            ->selectRaw(
                'status, count(*) as total, count(*) filter (where start_date <= ? and end_date >= ?) as valid_today',
                [$today, $today],
            )
            ->groupBy('status')
            ->get();

        // GROUP BY returns no row for a status nobody used, so every case of
        // the enum is seeded to zero before reading the result.
        $counts = [];

        foreach (PromotionStatus::cases() as $status) {
            $counts[$status->value] = 0;
        }

        $validToday = 0;

        foreach ($rows as $row) {
            $counts[$row->status] = (int) $row->total;
            $validToday += (int) $row->valid_today;
        }

        return new PromotionSummary(
            scheduled: $counts[PromotionStatus::SCHEDULED->value],
            active: $counts[PromotionStatus::ACTIVE->value],
            finished: $counts[PromotionStatus::FINISHED->value],
            validToday: $validToday,
        );
    }
}
