<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ProductOptionGroup;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds a "Ships" category and 3 variable products that mirror the
 * Najdi Sheep / livestock-ordering UI from the screenshots:
 *  - Packaging type (single required)
 *  - Cutting        (single required)
 *  - Packing        (single required, paid options)
 *  - Contains       (multi optional)
 *  - Weight         (single optional, price variants)
 */
class ShipsProductSeeder extends Seeder
{
    public function run(): void
    {
        // ── Category ────────────────────────────────────────────────────────
        $category = Category::firstOrCreate(
            ['slug' => 'ships'],
            [
                'name'        => 'Ships',
                'description' => 'Live animals — sheep, goats, and cattle for slaughter and delivery.',
                'is_active'   => true,
            ]
        );

        // ── Product definitions ──────────────────────────────────────────────
        $products = [
            [
                'name'        => 'Najdi Sheep',
                'description' => 'Premium Najdi sheep — naturally raised, hand-selected for optimal weight and quality. Full customisation available for packaging, cutting, and delivery.',
                'price'       => 1030.00,
                'handle'      => 'najdi-sheep',
                'sku'         => 'SHP-NAJDI-001',
                'option_groups' => [
                    [
                        'name'        => 'Packaging type',
                        'input_type'  => 'single',
                        'is_required' => true,
                        'options'     => [
                            ['label' => 'In bag',  'price_modifier' => 0],
                            ['label' => 'In box',  'price_modifier' => 5],
                        ],
                    ],
                    [
                        'name'        => 'Cutting',
                        'input_type'  => 'single',
                        'is_required' => true,
                        'options'     => [
                            ['label' => 'تقطيع عربي 8 قطع',         'price_modifier' => 0],
                            ['label' => 'تقطيع برياني كبيرة',        'price_modifier' => 0],
                            ['label' => 'تقطيع ثلاجة متوسطة',        'price_modifier' => 0],
                            ['label' => 'تقطيع ثلاجة صغيرة',         'price_modifier' => 0],
                        ],
                    ],
                    [
                        'name'        => 'Packing',
                        'input_type'  => 'single',
                        'is_required' => true,
                        'options'     => [
                            ['label' => 'Foam',    'price_modifier' => 10],
                            ['label' => 'Plastic', 'price_modifier' => 0],
                        ],
                    ],
                    [
                        'name'        => 'Contains',
                        'input_type'  => 'multi',
                        'is_required' => false,
                        'options'     => [
                            ['label' => 'Belly',      'price_modifier' => 0],
                            ['label' => 'Head',       'price_modifier' => 0],
                            ['label' => 'Intestines', 'price_modifier' => 0],
                            ['label' => 'Liver',      'price_modifier' => 0],
                        ],
                    ],
                    [
                        'name'        => 'Najdi weight',
                        'input_type'  => 'single',
                        'is_required' => false,
                        'options'     => [
                            ['label' => '8–10 KG  age 3–4', 'price_modifier' => 0],
                            ['label' => '11–13 KG age 5–6', 'price_modifier' => 50],
                            ['label' => '14–16 KG age 7–8', 'price_modifier' => 100],
                        ],
                    ],
                ],
            ],
            [
                'name'        => 'Haili Goat',
                'description' => 'Young Haili goat — tender meat, perfectly suited for family gatherings and celebrations. Customise your cut and packaging.',
                'price'       => 850.00,
                'handle'      => 'haili-goat',
                'sku'         => 'SHP-HAILI-001',
                'option_groups' => [
                    [
                        'name'        => 'Packaging type',
                        'input_type'  => 'single',
                        'is_required' => true,
                        'options'     => [
                            ['label' => 'In bag',  'price_modifier' => 0],
                            ['label' => 'In box',  'price_modifier' => 5],
                        ],
                    ],
                    [
                        'name'        => 'Cutting',
                        'input_type'  => 'single',
                        'is_required' => true,
                        'options'     => [
                            ['label' => 'Whole',         'price_modifier' => 0],
                            ['label' => 'Half (2 parts)','price_modifier' => 0],
                            ['label' => 'Quarter cuts',  'price_modifier' => 0],
                        ],
                    ],
                    [
                        'name'        => 'Packing',
                        'input_type'  => 'single',
                        'is_required' => true,
                        'options'     => [
                            ['label' => 'Foam',    'price_modifier' => 10],
                            ['label' => 'Plastic', 'price_modifier' => 0],
                        ],
                    ],
                    [
                        'name'        => 'Contains',
                        'input_type'  => 'multi',
                        'is_required' => false,
                        'options'     => [
                            ['label' => 'Head',       'price_modifier' => 0],
                            ['label' => 'Liver',      'price_modifier' => 0],
                            ['label' => 'Intestines', 'price_modifier' => 0],
                        ],
                    ],
                    [
                        'name'        => 'Weight range',
                        'input_type'  => 'single',
                        'is_required' => false,
                        'options'     => [
                            ['label' => '10–12 KG', 'price_modifier' => 0],
                            ['label' => '13–15 KG', 'price_modifier' => 40],
                        ],
                    ],
                ],
            ],
            [
                'name'        => 'Premium Lamb',
                'description' => 'Farm-raised premium lamb — delicate flavour, ideal for Eid and special occasions. Choose your preferred cutting style and extras.',
                'price'       => 1200.00,
                'handle'      => 'premium-lamb',
                'sku'         => 'SHP-LAMB-001',
                'option_groups' => [
                    [
                        'name'        => 'Packaging type',
                        'input_type'  => 'single',
                        'is_required' => true,
                        'options'     => [
                            ['label' => 'In bag',  'price_modifier' => 0],
                            ['label' => 'In box',  'price_modifier' => 8],
                            ['label' => 'Vacuum',  'price_modifier' => 15],
                        ],
                    ],
                    [
                        'name'        => 'Cutting',
                        'input_type'  => 'single',
                        'is_required' => true,
                        'options'     => [
                            ['label' => 'تقطيع عربي 8 قطع',   'price_modifier' => 0],
                            ['label' => 'تقطيع برياني كبيرة',  'price_modifier' => 0],
                            ['label' => 'Chops (French)',       'price_modifier' => 10],
                            ['label' => 'Minced',               'price_modifier' => 5],
                        ],
                    ],
                    [
                        'name'        => 'Packing',
                        'input_type'  => 'single',
                        'is_required' => true,
                        'options'     => [
                            ['label' => 'Foam',    'price_modifier' => 10],
                            ['label' => 'Plastic', 'price_modifier' => 0],
                        ],
                    ],
                    [
                        'name'        => 'Contains',
                        'input_type'  => 'multi',
                        'is_required' => false,
                        'options'     => [
                            ['label' => 'Head',       'price_modifier' => 0],
                            ['label' => 'Belly',      'price_modifier' => 0],
                            ['label' => 'Intestines', 'price_modifier' => 0],
                            ['label' => 'Liver',      'price_modifier' => 0],
                            ['label' => 'Legs',       'price_modifier' => 0],
                        ],
                    ],
                    [
                        'name'        => 'Weight range',
                        'input_type'  => 'single',
                        'is_required' => false,
                        'options'     => [
                            ['label' => '10–12 KG age 2–3', 'price_modifier' => 0],
                            ['label' => '13–16 KG age 4–5', 'price_modifier' => 60],
                            ['label' => '17–20 KG age 6+',  'price_modifier' => 130],
                        ],
                    ],
                ],
            ],
        ];

        // Reuse real local product images so client cards render reliably in all environments.
        $localImagePool = ProductImage::query()
            ->whereNotNull('image_path')
            ->where('image_path', '!=', '')
            ->where('image_path', 'not like', 'http%')
            ->orderBy('id')
            ->pluck('image_path')
            ->unique()
            ->values()
            ->all();

        foreach ($products as $index => $pd) {
            // Skip if already seeded (idempotent)
            $product = Product::firstOrCreate(
                ['handle' => $pd['handle']],
                [
                    'category_id'  => $category->id,
                    'name'         => $pd['name'],
                    'description'  => $pd['description'],
                    'price'        => $pd['price'],
                    'sku'          => $pd['sku'],
                    'status'       => 'active',
                    'product_type' => 'variable',
                    'stock'        => 50,
                    'is_featured'  => true,
                ]
            );

            // (Re)create option groups so seeder is idempotent
            $product->optionGroups()->delete();
            foreach ($pd['option_groups'] as $gi => $groupData) {
                $group = $product->optionGroups()->create([
                    'name'        => $groupData['name'],
                    'input_type'  => $groupData['input_type'],
                    'is_required' => $groupData['is_required'],
                    'sort_order'  => $gi,
                ]);
                foreach ($groupData['options'] as $oi => $optData) {
                    $group->options()->create([
                        'label'          => $optData['label'],
                        'price_modifier' => $optData['price_modifier'],
                        'sort_order'     => $oi,
                    ]);
                }
            }

            // Ensure each seeded variable product has a visible image in client cards.
            $seedImagePath = $localImagePool[$index] ?? 'images/logo.png';
            if ($seedImagePath) {
                $firstImage = $product->images()->orderBy('sort_order')->first();
                if (! $firstImage) {
                    $product->images()->create([
                        'image_path' => $seedImagePath,
                        'sort_order' => 0,
                        'is_primary' => true,
                    ]);
                } else {
                    if (! $firstImage->is_primary) {
                        $firstImage->update(['is_primary' => true, 'sort_order' => 0]);
                    }
                    if ($firstImage->image_path !== $seedImagePath && (str_starts_with((string) $firstImage->image_path, 'http') || str_starts_with((string) $firstImage->image_path, 'https'))) {
                        // If current image is external placeholder, replace with local fallback path.
                        $firstImage->update(['image_path' => $seedImagePath]);
                    }
                }
                if ($product->image !== $seedImagePath) {
                    $product->update(['image' => $seedImagePath]);
                }
            }
        }

        $this->command->info('Ships category + 3 variable products seeded successfully.');
    }
}
