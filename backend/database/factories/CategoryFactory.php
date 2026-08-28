<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    /**
     * Point of sale departments the generated names are built from.
     *
     * @var list<string>
     */
    private const NAMES = [
        'Bebidas',
        'Panadería',
        'Lácteos',
        'Snacks',
        'Limpieza',
        'Congelados',
        'Frutas y verduras',
        'Cuidado personal',
    ];

    /**
     * How many categories this process has generated so far.
     *
     * The name column carries a unique index, so a randomly picked name would
     * make the suite fail as soon as two categories collided: an intermittent
     * failure that says nothing about the code under test. A monotonic
     * counter makes the collision impossible instead of merely unlikely.
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
