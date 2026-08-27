<?php

namespace App\Actions\Promotions;

use App\Enums\PromotionStatus;
use App\Exceptions\PromotionIsNotScheduled;
use App\Models\Promotion;

/**
 * Deletes a promotion that has not started yet.
 */
final readonly class DeletePromotion
{
    /**
     * Remove the promotion permanently.
     *
     * Deletion is physical: the domain keeps no trace of a promotion that
     * never ran.
     *
     * @throws PromotionIsNotScheduled when the promotion already left SCHEDULED
     */
    public function __invoke(Promotion $promotion): void
    {
        // Guard runs before any write, so a rejected deletion leaves the row
        // in place.
        if ($promotion->status !== PromotionStatus::SCHEDULED) {
            throw new PromotionIsNotScheduled($promotion->status);
        }

        $promotion->delete();
    }
}
