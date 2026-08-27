<?php

namespace App\Exceptions;

use App\Enums\PromotionStatus;

/**
 * Raised when a status change does not follow the promotion lifecycle.
 *
 * Every rejected transition uses this single type, including one attempted
 * from FINISHED: PromotionIsFinished stays reserved for data modifications.
 */
final class InvalidStatusTransition extends PromotionStateConflict
{
    public function __construct(PromotionStatus $from, PromotionStatus $to)
    {
        parent::__construct(sprintf(
            'A promotion cannot transition from %s to %s.',
            $from->value,
            $to->value,
        ));
    }
}
