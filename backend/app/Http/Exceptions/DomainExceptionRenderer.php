<?php

namespace App\Http\Exceptions;

use App\Enums\ApiErrorCode;
use App\Exceptions\InvalidStatusTransition;
use App\Exceptions\PromotionIsFinished;
use App\Exceptions\PromotionIsNotScheduled;
use App\Exceptions\PromotionStateConflict;
use App\Http\Responses\ApiErrorResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Turns the failures the domain and the router raise into the API error
 * envelope.
 *
 * It lives in the HTTP layer because the translation is a transport concern:
 * the domain exceptions carry no status code and never mention ApiErrorCode,
 * so this class is the single place where a state rule meets its wire meaning.
 */
final readonly class DomainExceptionRenderer
{
    /**
     * Which machine readable code names each state rule.
     *
     * Keyed by class instead of asking the exception for its own code, which
     * would push the transport into the domain. A rule missing from the table
     * cannot break the answer, because the fallback below still replies 409;
     * what it would lose is the precise code, and ApiErrorMappingTest fails
     * when a subclass is left out so the loss never ships.
     *
     * @var array<class-string<PromotionStateConflict>, ApiErrorCode>
     */
    private const CONFLICT_CODES = [
        PromotionIsFinished::class => ApiErrorCode::PROMOTION_FINISHED,
        InvalidStatusTransition::class => ApiErrorCode::INVALID_STATUS_TRANSITION,
        PromotionIsNotScheduled::class => ApiErrorCode::PROMOTION_NOT_SCHEDULED,
    ];

    public function __construct(private ApiErrorResponse $errors) {}

    /**
     * Answer the envelope for a failure this class knows, or null to leave the
     * exception to the framework.
     */
    public function __invoke(Throwable $exception, Request $request): ?JsonResponse
    {
        // The path decides, not the Accept header: the envelope is the
        // contract of the API alone, while /health and the root route answer
        // shapes of their own. A monitoring client that sends
        // Accept: application/json to /health must keep receiving what that
        // endpoint always sent.
        if (! $request->is('api/*')) {
            return null;
        }

        return match (true) {
            $exception instanceof PromotionStateConflict => $this->conflict($exception),
            // ModelNotFoundException is listed even though the framework
            // usually rewrites it into NotFoundHttpException before the
            // render callbacks run: a use case that raises it outside route
            // model binding must answer the same 404.
            $exception instanceof ModelNotFoundException,
            $exception instanceof NotFoundHttpException => $this->notFound(),
            // Everything else keeps the framework handling. This class
            // translates the failures it recognises and hides none of the
            // rest, so a real database or programming error still surfaces.
            default => null,
        };
    }

    /**
     * A rule rejected the operation because of the promotion's current state.
     */
    private function conflict(PromotionStateConflict $exception): JsonResponse
    {
        return $this->errors->build(
            self::CONFLICT_CODES[$exception::class] ?? ApiErrorCode::PROMOTION_STATE_CONFLICT,
            // The domain message already names the states involved, so
            // restating it here would only let the two texts drift apart.
            $exception->getMessage(),
            Response::HTTP_CONFLICT,
        );
    }

    /**
     * The identifier in the URI matches no promotion.
     */
    private function notFound(): JsonResponse
    {
        return $this->errors->build(
            ApiErrorCode::PROMOTION_NOT_FOUND,
            'The requested promotion does not exist.',
            Response::HTTP_NOT_FOUND,
        );
    }
}
