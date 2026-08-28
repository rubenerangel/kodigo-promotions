<?php

namespace App\Enums;

/**
 * Machine readable catalogue of API error codes.
 *
 * The frontend branches on these values instead of matching message text, so
 * wording may change without breaking any client.
 */
enum ApiErrorCode: string
{
    case VALIDATION_FAILED = 'VALIDATION_FAILED';

    case PROMOTION_NOT_FOUND = 'PROMOTION_NOT_FOUND';

    case PROMOTION_FINISHED = 'PROMOTION_FINISHED';

    case INVALID_STATUS_TRANSITION = 'INVALID_STATUS_TRANSITION';

    case PROMOTION_NOT_SCHEDULED = 'PROMOTION_NOT_SCHEDULED';

    /**
     * Last resort for a state conflict whose rule has no code of its own.
     *
     * It exists so the HTTP layer can answer a truthful 409 when a new
     * PromotionStateConflict subclass reaches it before anyone adds its code,
     * instead of either failing or borrowing the code of an unrelated rule.
     * Reaching it is a defect, and ApiErrorMappingTest fails when a subclass
     * would.
     */
    case PROMOTION_STATE_CONFLICT = 'PROMOTION_STATE_CONFLICT';
}
