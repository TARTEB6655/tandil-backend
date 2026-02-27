<?php

namespace App\Console\Commands;

use App\Models\ExclusiveOffer;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Deletes all exclusive offers and creates 3 sample offers (active, current date range).
 * Use when existing offers are inactive/expired and the public API returns empty.
 */
class RefreshExclusiveOffers extends Command
{
    protected $signature = 'exclusive-offers:refresh';

    protected $description = 'Delete all exclusive offers and seed 3 sample offers (active, valid dates)';

    public function handle(): int
    {
        $count = ExclusiveOffer::count();
        ExclusiveOffer::query()->delete();
        $this->info("Deleted {$count} existing exclusive offer(s).");

        $today = Carbon::today();
        $start = $today->copy()->subDays(7);
        $end = $today->copy()->addDays(90);

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

        $this->info('Created ' . count($offers) . ' exclusive offers. GET /api/exclusive-offers should now return data.');
        return 0;
    }
}
