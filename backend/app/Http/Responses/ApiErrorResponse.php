<?php

namespace App\Http\Responses;

use App\Enums\ApiErrorCode;
use Illuminate\Http\JsonResponse;

/**
 * Builds the single error body shape every API endpoint answers with.
 *
 * It knows nothing about the domain: callers decide which code, message and
 * status describe their failure, and this class only composes the payload so
 * the shape cannot drift between endpoints.
 */
final readonly class ApiErrorResponse
{
    /**
     * Compose an error response.
     *
     * @param  array<string, list<string>>  $fields  Per field messages, empty when the
     *                                               failure is not about the payload.
     */
    public function build(ApiErrorCode $code, string $message, int $status, array $fields = []): JsonResponse
    {
        $error = [
            'code' => $code->value,
            'message' => $message,
        ];

        // Omitted rather than sent empty: statuses such as 404 and 409 have no
        // field level detail, and an empty object would invite clients to read
        // it as "the payload was checked and nothing was wrong".
        if ($fields !== []) {
            $error['fields'] = $fields;
        }

        return new JsonResponse(['error' => $error], $status);
    }
}
