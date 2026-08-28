<?php

namespace App\Http\Controllers;

use App\Actions\Promotions\ListPromotionTargets;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;

/**
 * HTTP entry point for the catalogue the promotion form picks a target from.
 *
 * Products and categories travel together under a single URI instead of
 * behind `GET /api/products` and `GET /api/categories`. The specification
 * lists the CRUD of products and categories among its non goals, and two
 * routes bearing those names would advertise a product API this project
 * neither has nor wants. The name `promotion-targets` says what the endpoint
 * is for, and the form fills its two selects with one request.
 */
final class PromotionTargetController extends Controller
{
    public function __construct(private readonly ListPromotionTargets $listPromotionTargets) {}

    /**
     * Every product and every category, as identifier and label.
     */
    public function __invoke(): JsonResponse
    {
        $catalog = ($this->listPromotionTargets)();

        // Each row is spelled out key by key rather than serialised whole:
        // the query already narrows the columns, but that only decides what
        // is read, not what is written, and a column added to either table
        // must not start reaching the form on its own.
        return new JsonResponse([
            'data' => [
                'products' => $catalog->products
                    ->map(static fn (Product $product): array => [
                        'id' => $product->id,
                        'name' => $product->name,
                    ])
                    ->all(),
                'categories' => $catalog->categories
                    ->map(static fn (Category $category): array => [
                        'id' => $category->id,
                        'name' => $category->name,
                    ])
                    ->all(),
            ],
        ]);
    }
}
