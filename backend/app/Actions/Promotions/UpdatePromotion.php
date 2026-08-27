<?php

namespace App\Actions\Promotions;

use App\DataTransferObjects\UpdatePromotionData;
use App\Enums\PromotionStatus;
use App\Exceptions\PromotionIsFinished;
use App\Models\Promotion;

/**
 * Updates the editable fields of a promotion.
 */
final readonly class UpdatePromotion
{
    /**
     * Apply the new values and return the updated promotion.
     *
     * The status is never touched here: changing it is a separate use case.
     *
     * @throws PromotionIsFinished when the promotion already finished
     */
    public function __invoke(Promotion $promotion, UpdatePromotionData $data): Promotion
    {
        // Guard runs before any write, so a rejected update leaves the row
        // exactly as it was.
        if ($promotion->status === PromotionStatus::FINISHED) {
            throw new PromotionIsFinished;
        }

        $promotion->update([
            'name' => $data->name,
            ...$data->target->columns(),
            'discount_type' => $data->discountType,
            'discount_value' => $data->discountValue,
            'start_date' => $data->startDate,
            'end_date' => $data->endDate,
        ]);

        return $promotion;
    }
}
