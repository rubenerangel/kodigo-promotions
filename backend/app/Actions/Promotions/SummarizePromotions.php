<?php

namespace App\Actions\Promotions;

use App\DataTransferObjects\PromotionSummary;
use App\Enums\PromotionStatus;
use App\Models\Promotion;

/**
 * Counts promotions per status.
 */
final readonly class SummarizePromotions
{
    public function __construct(private Promotion $promotion) {}

    /**
     * Totals for every status, resolved in a single aggregate query.
     */
    public function __invoke(): PromotionSummary
    {
        $totals = $this->promotion->newQuery()
            ->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        // GROUP BY returns no row for a status nobody used, so every case of
        // the enum is filled in explicitly and defaults to zero.
        $counts = [];

        foreach (PromotionStatus::cases() as $status) {
            $counts[$status->value] = (int) ($totals[$status->value] ?? 0);
        }

        return new PromotionSummary(
            scheduled: $counts[PromotionStatus::SCHEDULED->value],
            active: $counts[PromotionStatus::ACTIVE->value],
            finished: $counts[PromotionStatus::FINISHED->value],
        );
    }
}
