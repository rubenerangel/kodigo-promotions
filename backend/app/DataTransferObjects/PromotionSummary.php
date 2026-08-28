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
        // Counted by dates alone, so it overlaps the status counters instead
        // of partitioning them.
        public int $validToday,
    ) {}
}
