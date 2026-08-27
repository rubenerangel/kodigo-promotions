<?php

namespace App\Domain\Targets;

/**
 * A promotion aimed at a single category.
 */
final readonly class CategoryTarget implements PromotionTarget
{
    public function __construct(private int $categoryId) {}

    /**
     * {@inheritDoc}
     */
    public function columns(): array
    {
        return [
            'product_id' => null,
            'category_id' => $this->categoryId,
        ];
    }
}
