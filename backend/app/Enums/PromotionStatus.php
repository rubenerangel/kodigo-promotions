<?php

namespace App\Enums;

/**
 * Persisted lifecycle state of a promotion.
 *
 * The backing values must match the `promotions_status_check` constraint
 * defined in the promotions migration.
 */
enum PromotionStatus: string
{
    case SCHEDULED = 'SCHEDULED';
    case ACTIVE = 'ACTIVE';
    case FINISHED = 'FINISHED';
}
