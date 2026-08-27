<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Minimal point of sale categories used as promotion targets.
     *
     * @var list<string>
     */
    private array $categories = [
        'Bebidas',
        'Panadería',
        'Lácteos',
        'Snacks',
        'Limpieza',
    ];

    /**
     * Seed the categories table.
     *
     * Uses firstOrCreate so seeding twice neither duplicates the catalog nor
     * collides with the categories_name_unique index.
     */
    public function run(): void
    {
        foreach ($this->categories as $name) {
            Category::firstOrCreate(['name' => $name]);
        }
    }
}
