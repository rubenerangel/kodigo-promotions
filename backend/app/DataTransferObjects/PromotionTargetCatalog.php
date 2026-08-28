<?php

namespace App\DataTransferObjects;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

/**
 * The two sets of things a promotion is allowed to target.
 *
 * It carries the models rather than an already flattened shape because giving
 * the response its form is the controller's job, exactly as it is for the
 * promotion listing.
 */
final readonly class PromotionTargetCatalog
{
    /**
     * @param  Collection<int, Product>  $products
     * @param  Collection<int, Category>  $categories
     */
    public function __construct(
        public Collection $products,
        public Collection $categories,
    ) {}
}
