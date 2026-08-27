<?php

namespace App\Enums;

/**
 * Discount variants accepted by a promotion.
 *
 * The backing values must match the `promotions_discount_type_check`
 * constraint defined in the promotions migration.
 */
enum DiscountType: string
{
    case PERCENTAGE = 'PERCENTAGE';
    case FIXED_AMOUNT = 'FIXED_AMOUNT';
}
