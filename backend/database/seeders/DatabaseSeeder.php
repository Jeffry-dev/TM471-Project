<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed an admin user with credentials from environment variables
        $adminEmail = env('ADMIN_EMAIL', 'admin@example.com');
        $adminPassword = env('ADMIN_PASSWORD', 'Admin123!');

        User::updateOrCreate(
            ['email' => $adminEmail],
            [
                'name' => 'Admin',
                'password' => Hash::make($adminPassword),
                'role' => 'ADMIN',
                'bio' => 'Head administrator for Cedars of Lebanon.',
                'avatar_url' => null,
            ]
        );

        // Seed categories
        $categoryNames = [
            'Mezze',
            'Salads',
            'Grills',
            'Wraps & Sandwiches',
            'Main Dishes',
            'Desserts',
            'Beverages',
        ];
        $categoryByName = [];

        foreach ($categoryNames as $name) {
            $categoryByName[$name] = Category::updateOrCreate(
                ['name' => $name],
                ['description' => null]
            );
        }

        $items = [
            // Mezze
            [
                'name' => 'Hummus',
                'description' => 'Creamy chickpea dip blended with tahini, lemon, and garlic. Served with warm pita',
                'price' => 8.5,
                'category' => 'Mezze',
            ],
            [
                'name' => 'Baba Ghanoush',
                'description' => 'Smoky roasted eggplant dip with tahini, garlic, and olive oil',
                'price' => 9.0,
                'category' => 'Mezze',
            ],
            [
                'name' => 'Falafel',
                'description' => 'Crispy chickpea fritters with herbs and spices. Served with tahini sauce',
                'price' => 10.0,
                'category' => 'Mezze',
            ],
            [
                'name' => 'Kibbeh',
                'description' => 'Traditional Lebanese bulgur and minced meat croquettes with pine nuts',
                'price' => 12.5,
                'category' => 'Mezze',
            ],
            [
                'name' => 'Labneh',
                'description' => 'Thick strained yogurt drizzled with olive oil and zaatar',
                'price' => 7.5,
                'category' => 'Mezze',
            ],
            [
                'name' => 'Warak Enab',
                'description' => 'Grape leaves stuffed with rice, tomatoes, and herbs',
                'price' => 10.5,
                'category' => 'Mezze',
            ],

            // Salads
            [
                'name' => 'Fattoush',
                'description' => 'Mixed greens, tomatoes, cucumbers, radish with crispy pita and sumac dressing',
                'price' => 11.0,
                'category' => 'Salads',
            ],
            [
                'name' => 'Tabbouleh',
                'description' => 'Finely chopped parsley, tomatoes, bulgur, mint, and lemon dressing',
                'price' => 10.5,
                'category' => 'Salads',
            ],
            [
                'name' => 'Lebanese Salad',
                'description' => 'Fresh tomatoes, cucumbers, onions with lemon and olive oil',
                'price' => 9.0,
                'category' => 'Salads',
            ],

            // Grills
            [
                'name' => 'Mixed Grill Platter',
                'description' => 'Combination of lamb kafta, chicken tawook, and lamb kebab with rice and grilled vegetables',
                'price' => 28.0,
                'category' => 'Grills',
            ],
            [
                'name' => 'Shish Tawook',
                'description' => 'Charcoal-grilled chicken skewers marinated in garlic, lemon, and yogurt',
                'price' => 18.0,
                'category' => 'Grills',
            ],
            [
                'name' => 'Lamb Kafta',
                'description' => 'Seasoned ground lamb and beef kebabs with parsley and onions',
                'price' => 19.0,
                'category' => 'Grills',
            ],
            [
                'name' => 'Lamb Kebab',
                'description' => 'Premium lamb cubes marinated with Mediterranean spices and charcoal-grilled',
                'price' => 22.0,
                'category' => 'Grills',
            ],
            [
                'name' => 'Grilled Halloumi',
                'description' => 'Pan-grilled halloumi cheese with zaatar and olive oil',
                'price' => 13.0,
                'category' => 'Grills',
            ],

            // Wraps & Sandwiches
            [
                'name' => 'Shawarma Chicken Wrap',
                'description' => 'Marinated chicken with garlic sauce, pickles, and tomatoes in fresh saj bread',
                'price' => 12.0,
                'category' => 'Wraps & Sandwiches',
            ],
            [
                'name' => 'Falafel Wrap',
                'description' => 'Crispy falafel with hummus, tahini, vegetables in fresh pita',
                'price' => 10.5,
                'category' => 'Wraps & Sandwiches',
            ],
            [
                'name' => 'Kafta Sandwich',
                'description' => 'Grilled kafta with hummus, onions, tomatoes, and pickles',
                'price' => 11.5,
                'category' => 'Wraps & Sandwiches',
            ],

            // Main Dishes
            [
                'name' => 'Mansaf',
                'description' => 'Traditional lamb cooked in fermented yogurt sauce over rice with almonds',
                'price' => 24.0,
                'category' => 'Main Dishes',
            ],
            [
                'name' => 'Moussaka',
                'description' => 'Baked eggplant with spiced ground beef, chickpeas, and tomato sauce',
                'price' => 16.5,
                'category' => 'Main Dishes',
            ],
            [
                'name' => 'Shish Barak',
                'description' => 'Lebanese dumplings filled with meat in tangy yogurt sauce',
                'price' => 17.0,
                'category' => 'Main Dishes',
            ],

            // Desserts
            [
                'name' => 'Baklava',
                'description' => 'Layers of phyllo pastry with honey, pistachios, and walnuts',
                'price' => 7.5,
                'category' => 'Desserts',
            ],
            [
                'name' => 'Knafeh',
                'description' => 'Sweet cheese pastry soaked in sugar syrup and topped with pistachios',
                'price' => 8.5,
                'category' => 'Desserts',
            ],
            [
                'name' => 'Maamoul',
                'description' => 'Traditional shortbread cookies filled with dates or walnuts',
                'price' => 6.5,
                'category' => 'Desserts',
            ],
            [
                'name' => 'Halawet El Jibn',
                'description' => 'Sweet cheese rolls with rose water syrup and pistachios',
                'price' => 8.0,
                'category' => 'Desserts',
            ],

            // Beverages
            [
                'name' => 'Arabic Coffee',
                'description' => 'Traditional Lebanese coffee with cardamom',
                'price' => 4.0,
                'category' => 'Beverages',
            ],
            [
                'name' => 'Mint Tea',
                'description' => 'Fresh mint tea served hot or iced',
                'price' => 3.5,
                'category' => 'Beverages',
            ],
            [
                'name' => 'Jallab',
                'description' => 'Traditional drink with dates, grape molasses, and rose water',
                'price' => 5.0,
                'category' => 'Beverages',
            ],
            [
                'name' => 'Ayran',
                'description' => 'Refreshing salted yogurt drink',
                'price' => 4.0,
                'category' => 'Beverages',
            ],
            [
                'name' => 'Fresh Lemonade',
                'description' => 'Freshly squeezed lemon juice with mint',
                'price' => 4.5,
                'category' => 'Beverages',
            ],
        ];
        // seed menu images
        $imageUrlByName = [
            'Hummus' => 'https://images.pexels.com/photos/6089614/pexels-photo-6089614.jpeg?auto=compress&cs=tinysrgb&w=1200',
            'Baba Ghanoush' => 'https://images.pexels.com/photos/5191817/pexels-photo-5191817.jpeg?auto=compress&cs=tinysrgb&w=1200',
            'Falafel' => 'https://images.pexels.com/photos/6546028/pexels-photo-6546028.jpeg?auto=compress&cs=tinysrgb&w=1200',
            'Kibbeh' => 'https://images.pexels.com/photos/8360249/pexels-photo-8360249.jpeg?auto=compress&cs=tinysrgb&w=1200',
            'Labneh' => 'https://images.pexels.com/photos/32986467/pexels-photo-32986467.jpeg?auto=compress&cs=tinysrgb&w=1200',
            'Warak Enab' => 'https://images.pexels.com/photos/31928141/pexels-photo-31928141.jpeg?auto=compress&cs=tinysrgb&w=1200',
            'Fattoush' => 'https://images.pexels.com/photos/31233886/pexels-photo-31233886.jpeg?auto=compress&cs=tinysrgb&w=1200',
            'Tabbouleh' => 'https://images.pexels.com/photos/5191816/pexels-photo-5191816.jpeg?auto=compress&cs=tinysrgb&w=1200',
            'Lebanese Salad' => 'https://images.pexels.com/photos/15059716/pexels-photo-15059716.jpeg?auto=compress&cs=tinysrgb&w=1200',
            'Mixed Grill Platter' => 'https://images.pexels.com/photos/32986488/pexels-photo-32986488.jpeg?auto=compress&cs=tinysrgb&w=1200',
            'Shish Tawook' => 'https://images.pexels.com/photos/32023378/pexels-photo-32023378.jpeg?auto=compress&cs=tinysrgb&w=1200',
            'Lamb Kafta' => 'https://images.pexels.com/photos/32986492/pexels-photo-32986492.jpeg?auto=compress&cs=tinysrgb&w=1200',
            'Lamb Kebab' => 'https://images.pexels.com/photos/17872669/pexels-photo-17872669.jpeg?auto=compress&cs=tinysrgb&w=1200',
            'Grilled Halloumi' => 'https://images.pexels.com/photos/8751405/pexels-photo-8751405.jpeg?auto=compress&cs=tinysrgb&w=1200',
            'Shawarma Chicken Wrap' => 'https://images.pexels.com/photos/29306505/pexels-photo-29306505.jpeg?auto=compress&cs=tinysrgb&w=1200',
            'Falafel Wrap' => 'https://images.pexels.com/photos/32402073/pexels-photo-32402073.jpeg?auto=compress&cs=tinysrgb&w=1200',
            'Kafta Sandwich' => 'https://images.pexels.com/photos/33144665/pexels-photo-33144665.jpeg?auto=compress&cs=tinysrgb&w=1200',
            'Mansaf' => 'https://images.pexels.com/photos/32986473/pexels-photo-32986473.jpeg?auto=compress&cs=tinysrgb&w=1200',
            'Moussaka' => 'https://images.pexels.com/photos/30818656/pexels-photo-30818656.jpeg?auto=compress&cs=tinysrgb&w=1200',
            'Shish Barak' => 'https://images.pexels.com/photos/32988071/pexels-photo-32988071.jpeg?auto=compress&cs=tinysrgb&w=1200',
            'Baklava' => 'https://images.pexels.com/photos/15794015/pexels-photo-15794015.jpeg?auto=compress&cs=tinysrgb&w=1200',
            'Knafeh' => 'https://images.pexels.com/photos/19559294/pexels-photo-19559294.jpeg?auto=compress&cs=tinysrgb&w=1200',
            'Maamoul' => 'https://images.pexels.com/photos/32641175/pexels-photo-32641175.jpeg?auto=compress&cs=tinysrgb&w=1200',
            'Halawet El Jibn' => 'https://images.pexels.com/photos/6419631/pexels-photo-6419631.jpeg?auto=compress&cs=tinysrgb&w=1200',
            'Arabic Coffee' => 'https://images.pexels.com/photos/32902704/pexels-photo-32902704.jpeg?auto=compress&cs=tinysrgb&w=1200',
            'Mint Tea' => 'https://images.pexels.com/photos/30497987/pexels-photo-30497987.jpeg?auto=compress&cs=tinysrgb&w=1200',
            'Jallab' => 'https://images.pexels.com/photos/33226992/pexels-photo-33226992.jpeg?auto=compress&cs=tinysrgb&w=1200',
            'Ayran' => 'https://images.pexels.com/photos/12318617/pexels-photo-12318617.jpeg?auto=compress&cs=tinysrgb&w=1200',
            'Fresh Lemonade' => 'https://images.pexels.com/photos/33107428/pexels-photo-33107428.jpeg?auto=compress&cs=tinysrgb&w=1200',
        ];
        // seed AI metadata for menu items
        $aiMetaByName = [
            'Hummus' => ['ingredients' => ['chickpeas', 'tahini', 'lemon', 'garlic'], 'allergens' => ['sesame'], 'dietary_tags' => ['vegan', 'vegetarian']],
            'Baba Ghanoush' => ['ingredients' => ['eggplant', 'tahini', 'garlic', 'olive oil'], 'allergens' => ['sesame'], 'dietary_tags' => ['vegan', 'vegetarian']],
            'Falafel' => ['ingredients' => ['chickpeas', 'parsley', 'coriander', 'garlic'], 'allergens' => [], 'dietary_tags' => ['vegan', 'vegetarian']],
            'Kibbeh' => ['ingredients' => ['bulgur', 'minced meat', 'pine nuts', 'onion'], 'allergens' => ['gluten', 'tree nuts'], 'dietary_tags' => ['high-protein']],
            'Labneh' => ['ingredients' => ['strained yogurt', 'olive oil', 'zaatar'], 'allergens' => ['dairy'], 'dietary_tags' => ['vegetarian']],
            'Warak Enab' => ['ingredients' => ['grape leaves', 'rice', 'tomato', 'parsley'], 'allergens' => [], 'dietary_tags' => ['vegan', 'vegetarian']],
            'Fattoush' => ['ingredients' => ['lettuce', 'tomato', 'cucumber', 'pita chips', 'sumac'], 'allergens' => ['gluten'], 'dietary_tags' => ['vegan', 'vegetarian']],
            'Tabbouleh' => ['ingredients' => ['parsley', 'bulgur', 'tomato', 'mint', 'lemon'], 'allergens' => ['gluten'], 'dietary_tags' => ['vegan', 'vegetarian']],
            'Lebanese Salad' => ['ingredients' => ['tomato', 'cucumber', 'onion', 'lemon', 'olive oil'], 'allergens' => [], 'dietary_tags' => ['vegan', 'vegetarian']],
            'Mixed Grill Platter' => ['ingredients' => ['lamb', 'chicken', 'beef', 'rice', 'vegetables'], 'allergens' => [], 'dietary_tags' => ['high-protein']],
            'Shish Tawook' => ['ingredients' => ['chicken', 'garlic', 'lemon', 'yogurt'], 'allergens' => ['dairy'], 'dietary_tags' => ['high-protein']],
            'Lamb Kafta' => ['ingredients' => ['lamb', 'beef', 'parsley', 'onion'], 'allergens' => [], 'dietary_tags' => ['high-protein']],
            'Lamb Kebab' => ['ingredients' => ['lamb', 'spices', 'olive oil'], 'allergens' => [], 'dietary_tags' => ['high-protein']],
            'Grilled Halloumi' => ['ingredients' => ['halloumi cheese', 'zaatar', 'olive oil'], 'allergens' => ['dairy'], 'dietary_tags' => ['vegetarian']],
            'Shawarma Chicken Wrap' => ['ingredients' => ['chicken', 'garlic sauce', 'pickles', 'saj bread'], 'allergens' => ['gluten', 'dairy'], 'dietary_tags' => ['high-protein']],
            'Falafel Wrap' => ['ingredients' => ['falafel', 'hummus', 'tahini', 'pita'], 'allergens' => ['gluten', 'sesame'], 'dietary_tags' => ['vegan', 'vegetarian']],
            'Kafta Sandwich' => ['ingredients' => ['kafta', 'hummus', 'tomato', 'pickles', 'bread'], 'allergens' => ['gluten', 'sesame'], 'dietary_tags' => ['high-protein']],
            'Mansaf' => ['ingredients' => ['lamb', 'rice', 'fermented yogurt', 'almonds'], 'allergens' => ['dairy', 'tree nuts'], 'dietary_tags' => ['high-protein']],
            'Moussaka' => ['ingredients' => ['eggplant', 'ground beef', 'chickpeas', 'tomato'], 'allergens' => [], 'dietary_tags' => ['high-protein']],
            'Shish Barak' => ['ingredients' => ['dumplings', 'meat', 'yogurt sauce'], 'allergens' => ['gluten', 'dairy'], 'dietary_tags' => ['high-protein']],
            'Baklava' => ['ingredients' => ['phyllo pastry', 'honey', 'pistachios', 'walnuts'], 'allergens' => ['gluten', 'tree nuts'], 'dietary_tags' => ['vegetarian']],
            'Knafeh' => ['ingredients' => ['semolina', 'cheese', 'sugar syrup', 'pistachios'], 'allergens' => ['dairy', 'gluten', 'tree nuts'], 'dietary_tags' => ['vegetarian']],
            'Maamoul' => ['ingredients' => ['flour', 'dates', 'walnuts'], 'allergens' => ['gluten', 'tree nuts'], 'dietary_tags' => ['vegetarian']],
            'Halawet El Jibn' => ['ingredients' => ['sweet cheese', 'semolina', 'rose water', 'pistachios'], 'allergens' => ['dairy', 'gluten', 'tree nuts'], 'dietary_tags' => ['vegetarian']],
            'Arabic Coffee' => ['ingredients' => ['coffee', 'cardamom'], 'allergens' => [], 'dietary_tags' => ['vegan', 'vegetarian']],
            'Mint Tea' => ['ingredients' => ['mint', 'tea'], 'allergens' => [], 'dietary_tags' => ['vegan', 'vegetarian']],
            'Jallab' => ['ingredients' => ['dates', 'grape molasses', 'rose water'], 'allergens' => [], 'dietary_tags' => ['vegan', 'vegetarian']],
            'Ayran' => ['ingredients' => ['yogurt', 'water', 'salt'], 'allergens' => ['dairy'], 'dietary_tags' => ['vegetarian']],
            'Fresh Lemonade' => ['ingredients' => ['lemon', 'mint', 'water', 'sugar'], 'allergens' => [], 'dietary_tags' => ['vegan', 'vegetarian']],
        ]; 
        foreach ($items as $data) {
            $imageUrl = $imageUrlByName[$data['name']] ?? null;
            $aiMeta = $aiMetaByName[$data['name']] ?? [
                'ingredients' => [],
                'allergens' => [],
                'dietary_tags' => [],
            ];
            $category = $categoryByName[$data['category']] ?? Category::firstOrCreate(
                ['name' => $data['category']],
                ['description' => null],
            );

            MenuItem::updateOrCreate(
                ['name' => $data['name']],
                [
                    ...$data,
                    'category_id' => $category->id,
                    'image_url' => $imageUrl,
                    'is_available' => true,
                    'ingredients' => $aiMeta['ingredients'],
                    'allergens' => $aiMeta['allergens'],
                    'dietary_tags' => $aiMeta['dietary_tags'],
                ]
            );
        }
    }
}
