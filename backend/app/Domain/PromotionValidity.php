<?php

namespace App\Domain;

use App\Models\Promotion;
use Carbon\CarbonImmutable;

/**
 * Decides whether a promotion is running on the current date.
 *
 * Validity depends only on the date range, never on the persisted status: a
 * FINISHED promotion whose dates still cover today counts as valid.
 */
final readonly class PromotionValidity
{
    /**
     * Whether today falls inside the promotion range, both ends included.
     */
    public function isValidToday(Promotion $promotion): bool
    {
        $today = CarbonImmutable::today();

        // Normalized to immutable midnights so the comparison is day against
        // day. Comparing instants would let the time of execution decide the
        // boundaries. toImmutable() also avoids mutating the model attribute,
        // since the date cast hands back a mutable Carbon.
        $start = $promotion->start_date->toImmutable()->startOfDay();
        $end = $promotion->end_date->toImmutable()->startOfDay();

        return $start->lessThanOrEqualTo($today)
            && $end->greaterThanOrEqualTo($today);
    }
}
