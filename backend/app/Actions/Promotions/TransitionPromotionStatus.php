<?php

namespace App\Actions\Promotions;

use App\Enums\PromotionStatus;
use App\Exceptions\InvalidStatusTransition;
use App\Models\Promotion;

/**
 * Advances a promotion to the next status of its lifecycle.
 */
final readonly class TransitionPromotionStatus
{
    /**
     * Apply the status change and return the promotion.
     *
     * Which transitions are allowed is decided by the status itself; this
     * action only asks.
     *
     * @throws InvalidStatusTransition when the lifecycle forbids the change
     */
    public function __invoke(Promotion $promotion, PromotionStatus $target): Promotion
    {
        // Guard runs before any write, so a rejected transition leaves the row
        // exactly as it was.
        if (! $promotion->status->canTransitionTo($target)) {
            throw new InvalidStatusTransition($promotion->status, $target);
        }

        $promotion->update(['status' => $target]);

        return $promotion;
    }
}
