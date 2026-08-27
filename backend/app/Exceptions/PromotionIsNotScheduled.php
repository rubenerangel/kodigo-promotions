<?php

namespace App\Exceptions;

use App\Enums\PromotionStatus;

/**
 * Raised when an operation requires a scheduled promotion and the promotion is
 * in any other status.
 *
 * A single type covers both ACTIVE and FINISHED: one rule, one exception.
 * PromotionIsFinished stays reserved for data modifications.
 */
final class PromotionIsNotScheduled extends PromotionStateConflict
{
    public function __construct(PromotionStatus $current)
    {
        parent::__construct(sprintf(
            'Only a SCHEDULED promotion allows this operation, but it is %s.',
            $current->value,
        ));
    }
}
