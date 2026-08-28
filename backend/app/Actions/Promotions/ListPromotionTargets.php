<?php

namespace App\Actions\Promotions;

use App\DataTransferObjects\PromotionTargetCatalog;
use App\Models\Category;
use App\Models\Product;

/**
 * Lists everything a promotion can be pointed at.
 */
final readonly class ListPromotionTargets
{
    public function __construct(
        private Product $product,
        private Category $category,
    ) {}

    /**
     * Both catalogues, one query each.
     *
     * Only `id` and `name` are selected: they are the whole of what a target
     * picker consumes, and stating them keeps a later column on either table
     * from silently travelling to the client. Ordering by name is not
     * cosmetic either, a picker past a handful of entries is unusable without
     * it, and the order has to hold between requests, which an unordered
     * query does not promise.
     *
     * The order is taken under a Spanish collation rather than the database
     * default, which compares bytes: an accented letter encodes above every
     * unaccented one, so `Limpieza` came out ahead of `Lácteos` and the picker
     * read as broken to anyone who speaks the language. Sorting here rather
     * than in the interface keeps the promise the endpoint makes true for
     * every client, not only for the screen that happens to consume it today.
     */
    public function __invoke(): PromotionTargetCatalog
    {
        return new PromotionTargetCatalog(
            products: $this->product->newQuery()
                ->select(['id', 'name'])
                ->orderByRaw('name COLLATE "es-x-icu"')
                ->get(),
            categories: $this->category->newQuery()
                ->select(['id', 'name'])
                ->orderByRaw('name COLLATE "es-x-icu"')
                ->get(),
        );
    }
}
