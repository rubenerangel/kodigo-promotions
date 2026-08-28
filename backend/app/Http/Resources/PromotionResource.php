<?php

namespace App\Http\Resources;

use App\Domain\PromotionValidity;
use App\Models\Product;
use App\Models\Promotion;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wire representation of a single promotion.
 *
 * @property-read Promotion $resource
 */
class PromotionResource extends JsonResource
{
    /**
     * The validity rule arrives by constructor rather than being reached for
     * from the container, so the collaborator stays visible and replaceable.
     * The consequence is that `collection()` cannot build these: the caller
     * maps the collection itself.
     */
    public function __construct(Promotion $resource, private readonly PromotionValidity $validity)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $promotion = $this->resource;

        return [
            'id' => $promotion->id,
            'name' => $promotion->name,
            'target' => $this->target($promotion),
            'discountType' => $promotion->discount_type->value,
            // Emitted as text so the exact decimal of a decimal(10, 2) column
            // survives JSON encoding untouched.
            'discountValue' => (string) $promotion->discount_value,
            // Dates only: the column is DATE, and a time component would
            // invite clients to reason about timezones that do not apply.
            'startDate' => $promotion->start_date->format('Y-m-d'),
            'endDate' => $promotion->end_date->format('Y-m-d'),
            'status' => $promotion->status->value,
            // Derived from the dates on every read instead of being stored, so
            // it can never go stale.
            'validToday' => $this->validity->isValidToday($promotion),
        ];
    }

    /**
     * Shape of the promotion target.
     *
     * This is the only place that asks which variant a promotion holds, and it
     * belongs here for the same reason the Form Request may ask in the
     * opposite direction: this is the edge where the domain becomes a
     * representation. Everything behind it keeps seeing a target it never
     * interrogates.
     *
     * @return array{type: string, id: int, name: string}
     */
    private function target(Promotion $promotion): array
    {
        $target = $promotion->target;

        return [
            'type' => $target instanceof Product ? 'product' : 'category',
            'id' => $target->id,
            'name' => $target->name,
        ];
    }
}
