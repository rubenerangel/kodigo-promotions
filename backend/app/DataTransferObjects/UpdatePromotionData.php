<?php

namespace App\DataTransferObjects;

use App\Domain\Targets\PromotionTarget;
use App\Enums\DiscountType;
use Carbon\CarbonImmutable;

/**
 * Input required to update a promotion, already free of any transport concern.
 *
 * Kept separate from CreatePromotionData even though the fields match today:
 * they are different use cases and already differ in intent, since creating
 * sets the initial status and updating is forbidden to touch it.
 */
final readonly class UpdatePromotionData
{
    public function __construct(
        public string $name,
        public PromotionTarget $target,
        public DiscountType $discountType,
        // String keeps the exact decimal the caller supplied: the column is
        // decimal(10, 2) and a float would lose precision.
        public string $discountValue,
        public CarbonImmutable $startDate,
        public CarbonImmutable $endDate,
    ) {}
}
