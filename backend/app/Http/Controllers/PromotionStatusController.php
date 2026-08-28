<?php

namespace App\Http\Controllers;

use App\Actions\Promotions\TransitionPromotionStatus;
use App\Domain\PromotionValidity;
use App\Http\Requests\TransitionPromotionStatusRequest;
use App\Http\Resources\PromotionResource;
use App\Models\Promotion;
use Illuminate\Http\JsonResponse;

/**
 * HTTP entry point for the status lifecycle of a promotion.
 *
 * Kept apart from PromotionController because it answers a different question:
 * the catalogue controller edits what a promotion says, this one moves it
 * through its lifecycle.
 */
final class PromotionStatusController extends Controller
{
    public function __construct(
        private readonly TransitionPromotionStatus $transitionPromotionStatus,
        private readonly PromotionValidity $validity,
    ) {}

    /**
     * Advance the promotion to the requested status.
     */
    public function __invoke(TransitionPromotionStatusRequest $request, Promotion $promotion): JsonResponse
    {
        $updated = ($this->transitionPromotionStatus)($promotion, $request->status());

        return new JsonResponse(['data' => new PromotionResource($updated, $this->validity)]);
    }
}
