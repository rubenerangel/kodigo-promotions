<?php

namespace App\Actions\Promotions;

use App\Models\Promotion;
use Illuminate\Database\Eloquent\Collection;

/**
 * Lists every promotion with its target already resolved.
 */
final readonly class ListPromotions
{
    public function __construct(private Promotion $promotion) {}

    /**
     * Every promotion, newest start date first.
     *
     * Both target relations are eager loaded: the `target` accessor reads them,
     * so without this the caller would trigger one query per row.
     *
     * @return Collection<int, Promotion>
     */
    public function __invoke(): Collection
    {
        return $this->promotion->newQuery()
            ->with(['product', 'category'])
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();
    }
}
