<?php

namespace App\Exceptions;

/**
 * Raised when an operation would modify a promotion that already finished.
 */
final class PromotionIsFinished extends PromotionStateConflict
{
    public function __construct()
    {
        parent::__construct('A finished promotion cannot be modified.');
    }
}
