<?php

namespace App\Http\Controllers;

use App\Actions\Promotions\CreatePromotion;
use App\Actions\Promotions\DeletePromotion;
use App\Actions\Promotions\ListPromotions;
use App\Actions\Promotions\UpdatePromotion;
use App\Domain\PromotionValidity;
use App\Http\Requests\StorePromotionRequest;
use App\Http\Requests\UpdatePromotionRequest;
use App\Http\Resources\PromotionResource;
use App\Models\Promotion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

/**
 * HTTP entry point for the promotion catalogue.
 *
 * Every method does the same three things and nothing else: take the request,
 * hand it to a use case, and shape the answer. No business rule lives here.
 */
final class PromotionController extends Controller
{
    public function __construct(
        private readonly ListPromotions $listPromotions,
        private readonly CreatePromotion $createPromotion,
        private readonly UpdatePromotion $updatePromotion,
        private readonly DeletePromotion $deletePromotion,
        // Handed on to every resource this controller builds, since the
        // resource takes it by constructor instead of reaching for it.
        private readonly PromotionValidity $validity,
    ) {}

    /**
     * Every promotion, newest start date first.
     */
    public function index(): JsonResponse
    {
        $promotions = ($this->listPromotions)();

        // Mapped here rather than through PromotionResource::collection(),
        // which cannot pass the second constructor argument.
        $resources = $promotions->map(
            fn (Promotion $promotion): PromotionResource => $this->resource($promotion),
        );

        return new JsonResponse(['data' => $resources->all()]);
    }

    /**
     * Create a promotion from a validated payload.
     */
    public function store(StorePromotionRequest $request): JsonResponse
    {
        $promotion = ($this->createPromotion)($request->toData());

        return new JsonResponse(
            ['data' => $this->resource($promotion)],
            Response::HTTP_CREATED,
        );
    }

    /**
     * Replace the editable fields of a promotion.
     */
    public function update(UpdatePromotionRequest $request, Promotion $promotion): JsonResponse
    {
        $updated = ($this->updatePromotion)($promotion, $request->toData());

        return new JsonResponse(['data' => $this->resource($updated)]);
    }

    /**
     * Remove a promotion that has not started yet.
     */
    public function destroy(Promotion $promotion): Response
    {
        ($this->deletePromotion)($promotion);

        // No body: the caller already knows which promotion it removed, and an
        // envelope with nothing in it would only invite clients to read it.
        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    private function resource(Promotion $promotion): PromotionResource
    {
        return new PromotionResource($promotion, $this->validity);
    }
}
