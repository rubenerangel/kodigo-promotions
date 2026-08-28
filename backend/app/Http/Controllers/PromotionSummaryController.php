<?php

namespace App\Http\Controllers;

use App\Actions\Promotions\SummarizePromotions;
use Illuminate\Http\JsonResponse;

/**
 * HTTP entry point for the promotion counters.
 */
final class PromotionSummaryController extends Controller
{
    public function __construct(private readonly SummarizePromotions $summarizePromotions) {}

    /**
     * Totals per status plus how many promotions run today.
     */
    public function __invoke(): JsonResponse
    {
        $summary = ($this->summarizePromotions)();

        // The DTO is translated to its wire keys here, at the edge, so the
        // summary object owes nothing to the transport that carries it.
        return new JsonResponse([
            'data' => [
                'scheduled' => $summary->scheduled,
                'active' => $summary->active,
                'finished' => $summary->finished,
                'validToday' => $summary->validToday,
            ],
        ]);
    }
}
