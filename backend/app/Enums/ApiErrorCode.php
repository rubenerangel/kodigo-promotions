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
}
