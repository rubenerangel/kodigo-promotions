<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Enums\PromotionStatus;
use Database\Factories\PromotionFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Promotion extends Model
{
    /** @use HasFactory<PromotionFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'product_id',
        'category_id',
        'discount_type',
        'discount_value',
        'start_date',
        'end_date',
        'status',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_type' => DiscountType::class,
            'status' => PromotionStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'discount_value' => 'decimal:2',
        ];
    }

    /**
     * Product this promotion targets, when the target is a product.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Category this promotion targets, when the target is a category.
     *
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Resolved target of the promotion, either a product or a category.
     *
     * Declared as an accessor rather than a relation method: Eloquent would
     * try to resolve a `target()` method as a relationship and fail.
     *
     * @return Attribute<Product|Category|null, never>
     */
    protected function target(): Attribute
    {
        return Attribute::get(fn (): Product|Category|null => $this->product ?? $this->category);
    }
}
