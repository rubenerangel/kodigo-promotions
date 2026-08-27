<?php

namespace App\Domain\Targets;

/**
 * A promotion target, which is always exactly one product or one category.
 *
 * The interface is deliberately minimal: it exposes only what persisting a
 * promotion needs, so no consumer has to know which variant it holds.
 */
interface PromotionTarget
{
    /**
     * Target columns for this variant.
     *
     * @return array{product_id: int|null, category_id: int|null}
     */
    public function columns(): array;
}
