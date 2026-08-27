<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    /**
     * Minimal point of sale products used as promotion targets.
     *
     * Products are intentionally not linked to categories: the products table
     * has no category_id, since no requirement relates them.
     *
     * @var list<string>
     */
    private array $products = [
        'Café molido 500g',
        'Té verde 20 sobres',
        'Jugo de naranja 1L',
        'Pan francés',
        'Pan integral',
        'Leche entera 1L',
        'Queso fresco 400g',
        'Galletas de chocolate',
        'Papas fritas 150g',
        'Detergente líquido 1L',
    ];

    /**
     * Seed the products table.
     *
     * Uses firstOrCreate so seeding twice does not duplicate the catalog.
     */
    public function run(): void
    {
        foreach ($this->products as $name) {
            Product::firstOrCreate(['name' => $name]);
        }
    }
}
