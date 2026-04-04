<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed menu categories.
     */
    public function run(): void
    {
        $categoryNames = [
            'Mezze',
            'Salads',
            'Grills',
            'Wraps & Sandwiches',
            'Main Dishes',
            'Desserts',
            'Beverages',
        ];

        foreach ($categoryNames as $name) {
            Category::updateOrCreate(
                ['name' => $name],
                ['description' => null]
            );
        }
    }
}
