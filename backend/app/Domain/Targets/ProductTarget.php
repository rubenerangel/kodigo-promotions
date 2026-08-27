<?php

namespace App\Domain\Targets;

/**
 * A promotion aimed at a single product.
 */
final readonly class ProductTarget implements PromotionTarget
{
    public function __construct(private int $productId) {}

    /**
     * {@inheritDoc}
     */
    public function columns(): array
    {
        return [
            'product_id' => $this->productId,
            'category_id' => null,
        ];
    }
}
