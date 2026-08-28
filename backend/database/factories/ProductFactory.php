<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    /**
     * Point of sale articles the generated names are built from.
     *
     * @var list<string>
     */
    private const NAMES = [
        'Café molido 500g',
        'Leche entera 1L',
        'Pan de molde integral',
        'Galletas de avena 300g',
        'Detergente líquido 3L',
        'Aceite de oliva 750ml',
        'Yogur natural pack 4',
        'Agua mineral 1.5L',
    ];

    /**
     * How many products this process has generated so far.
     *
     * The products table has no unique index today, but tests that count
     * rows or look one up by name still need distinct fixtures, so the names
     * are kept unique for the same reason as the categories.
     */
    private static int $generated = 0;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $index = self::$generated++;

        return [
            'name' => sprintf('%s %02d', self::NAMES[$index % count(self::NAMES)], $index + 1),
        ];
    }
}
