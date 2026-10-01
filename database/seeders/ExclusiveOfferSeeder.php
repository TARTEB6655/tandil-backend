<?php

namespace Database\Seeders;

use App\Models\ExclusiveOffer;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * Seeds sample exclusive offers so GET /api/exclusive-offers returns data.
 * Offers are active and valid for a date range that includes today.
 */
class ExclusiveOfferSeeder extends Seeder
{
    public function run(): void
    {
        if (ExclusiveOffer::count() > 0) {
            $this->command->info('Exclusive offers already exist, skipping.');

            return;
        }

        $this->command->info('Seeding exclusive offers...');

        $today = Carbon::today();
        $start = $today->copy()->subDays(7);
        $end = $today->copy()->addDays(30);

        $offers = [
            [
                'title' => 'Spring Garden Sale',
                'description' => 'Up to 20% off on selected plants and gardening kits. Limited time only.',
                'image' => null,
                'discount_type' => 'percentage',
                'discount_value' => 20,
                'applies_to' => 'Fresh produce, Fertilizers & Soil',
                'start_date' => $start,
                'end_date' => $end,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'title' => 'Buy One Get One Free',
                'description' => 'On selected seeds and soil products. Perfect for expanding your garden.',
                'image' => null,
                'discount_type' => 'buy_one_get_one',
                'discount_value' => null,
                'applies_to' => 'Seeds, Soil',
                'start_date' => $start,
                'end_date' => $end,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'title' => 'AED 50 Off Your Order',
                'description' => 'Minimum order AED 200. Use at checkout.',
                'image' => null,
                'discount_type' => 'fixed_amount',
                'discount_value' => 50,
                'applies_to' => 'All products',
                'start_date' => $start,
                'end_date' => $end,
                'is_active' => true,
                'sort_order' => 3,
            ],
        ];

        $productIds = Product::query()->limit(3)->pluck('id')->all();

        foreach ($offers as $index => $data) {
            $offer = ExclusiveOffer::create($data);
            if (! empty($productIds)) {
                $offer->products()->sync(array_slice($productIds, 0, $index + 1));
            }
        }

        $this->command->info('Created ' . count($offers) . ' exclusive offers.');
    }
}
