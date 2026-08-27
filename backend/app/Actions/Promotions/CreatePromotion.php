<?php

namespace App\Actions\Promotions;

use App\DataTransferObjects\CreatePromotionData;
use App\Enums\PromotionStatus;
use App\Models\Promotion;

/**
 * Creates a promotion in its initial state.
 */
final readonly class CreatePromotion
{
    public function __construct(private Promotion $promotion) {}

    /**
     * Persist a new promotion and return it.
     *
     * The target resolves its own columns, so this action never inspects which
     * variant it received.
     */
    public function __invoke(CreatePromotionData $data): Promotion
    {
        return $this->promotion->newQuery()->create([
            'name' => $data->name,
            ...$data->target->columns(),
            'discount_type' => $data->discountType,
            'discount_value' => $data->discountValue,
            'start_date' => $data->startDate,
            'end_date' => $data->endDate,
            // Stated explicitly by the use case; the column default is only a
            // safety net.
            'status' => PromotionStatus::SCHEDULED,
        ]);
    }
}
