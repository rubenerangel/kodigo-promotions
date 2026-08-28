<?php

namespace App\Http\Controllers;

use App\Domain\DatabaseHealth;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Operational status of the application and its database.
 */
final class HealthController extends Controller
{
    public function __construct(private readonly DatabaseHealth $databaseHealth) {}

    /**
     * Answer 200 only when the database really responds, 503 otherwise.
     */
    public function __invoke(): JsonResponse
    {
        $available = $this->databaseHealth->isAvailable();

        return new JsonResponse(
            [
                'status' => $available ? 'ok' : 'error',
                'database' => $available ? 'ok' : 'unavailable',
            ],
            $available ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE,
        );
    }
}
