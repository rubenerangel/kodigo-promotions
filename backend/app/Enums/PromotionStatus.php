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

    /**
     * Whether this status may advance to the given one.
     *
     * The lifecycle only moves forward, one step at a time: SCHEDULED becomes
     * ACTIVE, ACTIVE becomes FINISHED, and FINISHED is terminal. Transitioning
     * to the same status is rejected as well.
     */
    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::SCHEDULED => $target === self::ACTIVE,
            self::ACTIVE => $target === self::FINISHED,
            self::FINISHED => false,
        };
    }
}
