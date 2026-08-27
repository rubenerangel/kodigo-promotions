<?php

namespace App\DataTransferObjects;

use App\Domain\Targets\PromotionTarget;
use App\Enums\DiscountType;
use Carbon\CarbonImmutable;

/**
 * Input required to create a promotion, already free of any transport concern.
 *
 * Building this object from an HTTP request belongs to the Form Request layer,
 * so no construction helpers live here.
 */
final readonly class CreatePromotionData
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
