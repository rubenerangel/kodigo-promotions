<?php

namespace Tests\Unit\Enums;

use App\Enums\PromotionStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PromotionStatusTest extends TestCase
{
    #[DataProvider('transitions')]
    public function test_it_only_allows_moving_one_step_forward(
        PromotionStatus $from,
        PromotionStatus $to,
        bool $allowed,
    ): void {
        $this->assertSame($allowed, $from->canTransitionTo($to));
    }

    /**
     * Every combination of the three statuses: two allowed, seven rejected.
     *
     * @return array<string, array{PromotionStatus, PromotionStatus, bool}>
     */
    public static function transitions(): array
    {
        return [
            // The only two allowed steps.
            'SCHEDULED -> ACTIVE is allowed' => [PromotionStatus::SCHEDULED, PromotionStatus::ACTIVE, true],
            'ACTIVE -> FINISHED is allowed' => [PromotionStatus::ACTIVE, PromotionStatus::FINISHED, true],

            // Same status is not a transition.
            'SCHEDULED -> SCHEDULED is rejected' => [PromotionStatus::SCHEDULED, PromotionStatus::SCHEDULED, false],
            'ACTIVE -> ACTIVE is rejected' => [PromotionStatus::ACTIVE, PromotionStatus::ACTIVE, false],
            'FINISHED -> FINISHED is rejected' => [PromotionStatus::FINISHED, PromotionStatus::FINISHED, false],

            // Skipping a step forward.
            'SCHEDULED -> FINISHED is rejected' => [PromotionStatus::SCHEDULED, PromotionStatus::FINISHED, false],

            // Moving backwards.
            'ACTIVE -> SCHEDULED is rejected' => [PromotionStatus::ACTIVE, PromotionStatus::SCHEDULED, false],

            // FINISHED is terminal.
            'FINISHED -> SCHEDULED is rejected' => [PromotionStatus::FINISHED, PromotionStatus::SCHEDULED, false],
            'FINISHED -> ACTIVE is rejected' => [PromotionStatus::FINISHED, PromotionStatus::ACTIVE, false],
        ];
    }
}
