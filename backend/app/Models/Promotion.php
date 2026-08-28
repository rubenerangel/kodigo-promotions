<?php

namespace App\Models;

use App\Enums\DiscountType;
use App\Enums\PromotionStatus;
use Database\Factories\PromotionFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The types the casts below produce at runtime.
 *
 * Eloquent resolves them dynamically, so without this block static analysis
 * reads every attribute as the string the column holds and reports comparing
 * a status against its enum as always false. Declaring them is the missing
 * information, not a way to quiet the analyser.
 *
 * @property int $id
 * @property string $name
 * @property int|null $product_id
 * @property int|null $category_id
 * @property DiscountType $discount_type
 * @property string $discount_value
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property PromotionStatus $status
 * @property-read Product|Category|null $target
 */
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
     * The generic parameters are left to inference. Restating them here made
     * static analysis compare an invariant template against itself and report
     * a mismatch between two identical types; the closure already carries the
     * information, and the reader gets it from the @property-read entry above.
     */
    protected function target(): Attribute
    {
        return Attribute::get(fn (): Product|Category|null => $this->product ?? $this->category);
    }
}
