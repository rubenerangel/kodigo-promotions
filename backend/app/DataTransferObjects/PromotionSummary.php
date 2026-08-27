<?php

namespace App\DataTransferObjects;

/**
 * How many promotions sit in each status.
 */
final readonly class PromotionSummary
{
    public function __construct(
        public int $scheduled,
        public int $active,
        public int $finished,
    ) {}
}
