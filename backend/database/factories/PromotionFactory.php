<?php

namespace Database\Factories;

use App\Enums\DiscountType;
use App\Enums\PromotionStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promotion;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    /**
     * Campaign names the generated names are built from.
     *
     * @var list<string>
     */
    private const NAMES = [
        'Promoción de temporada',
        'Descuento de apertura',
        'Semana del cliente',
        'Liquidación de inventario',
        'Oferta de fin de semana',
    ];

    /**
     * How many promotions this process has generated so far.
     */
    private static int $generated = 0;

    /**
     * Define the model's default state.
     *
     * The defaults satisfy every CHECK constraint of the promotions table: a
     * single target, a range whose end is after its start, a positive value
     * and a percentage inside the inclusive 1 to 100 band. Any state below
     * only moves one of those axes, so the rest stays valid by construction.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $index = self::$generated++;
        $startDate = CarbonImmutable::now()->startOfDay();

        return [
            'name' => sprintf('%s %02d', self::NAMES[$index % count(self::NAMES)], $index + 1),
            'product_id' => Product::factory(),
            'category_id' => null,
            'discount_type' => DiscountType::PERCENTAGE,
            'discount_value' => number_format(fake()->randomFloat(2, 1, 100), 2, '.', ''),
            'start_date' => $startDate,
            'end_date' => $startDate->addDays(29),
            'status' => PromotionStatus::SCHEDULED,
        ];
    }

    /**
     * A promotion that has not been started yet.
     */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PromotionStatus::SCHEDULED,
        ]);
    }

    /**
     * A promotion already running.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PromotionStatus::ACTIVE,
        ]);
    }

    /**
     * A promotion that reached the terminal state.
     */
    public function finished(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PromotionStatus::FINISHED,
        ]);
    }

    /**
     * Target a product, building one when the caller has none at hand.
     *
     * The opposite column is cleared in the same state so the exclusive
     * target constraint holds however the states are chained.
     */
    public function forProduct(?Product $product = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'product_id' => $product?->id ?? Product::factory(),
            'category_id' => null,
        ]);
    }

    /**
     * Target a category, building one when the caller has none at hand.
     */
    public function forCategory(?Category $category = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'category_id' => $category?->id ?? Category::factory(),
            'product_id' => null,
        ]);
    }

    /**
     * Place the range around the given day, so the promotion covers it.
     */
    public function runningOn(string|CarbonImmutable $date): static
    {
        $day = $this->day($date);

        return $this->state(fn (array $attributes): array => [
            'start_date' => $day->subDays(7),
            'end_date' => $day->addDays(7),
        ]);
    }

    /**
     * Place the range entirely before the given day, ending the day before it.
     */
    public function endedBefore(string|CarbonImmutable $date): static
    {
        $day = $this->day($date);

        return $this->state(fn (array $attributes): array => [
            'start_date' => $day->subDays(14),
            'end_date' => $day->subDay(),
        ]);
    }

    /**
     * Normalise the reference day of the date states.
     */
    private function day(string|CarbonImmutable $date): CarbonImmutable
    {
        return ($date instanceof CarbonImmutable ? $date : CarbonImmutable::parse($date))->startOfDay();
    }
}
