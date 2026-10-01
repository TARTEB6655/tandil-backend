<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inserts exactly 10 new visits (jobs). Does not read, update, or delete any existing visits.
 *
 * Uses client1@test.com's latest subscription if present; otherwise creates one subscription for that client only.
 * Prefers supervisor1@test.com + their first supervised area when available.
 *
 * Run: php artisan db:seed --class=TenExtraJobsSeeder
 */
class TenExtraJobsSeeder extends Seeder
{
    private const NOTES_PREFIX = '[SEED-10-JOBS]';

    public function run(): void
    {
        $client = User::query()->where('email', 'client1@test.com')->first();
        if (! $client) {
            $this->command->error('User client1@test.com not found. Run db:seed (FixedUsersOnlySeeder) first, then run this seeder again.');

            return;
        }

        $subscription = Subscription::query()
            ->where('client_id', $client->id)
            ->orderByDesc('id')
            ->first();

        if (! $subscription) {
            $today = Carbon::today();
            $subscription = Subscription::query()->create([
                'client_id' => $client->id,
                'plan' => '6_month',
                'start_date' => $today->copy()->subMonth()->toDateString(),
                'end_date' => $today->copy()->addYear()->toDateString(),
                'amount' => 1999.00,
                'payment_status' => 'paid',
                'total_visits' => 24,
                'completed_visits' => 0,
            ]);
            $this->command->warn('No subscription for client1; created subscription id ' . $subscription->id . '.');
        }

        $supervisor = User::query()->where('email', 'supervisor1@test.com')->first();
        $supervisorId = $supervisor?->id;

        $areaId = null;
        if ($supervisorId && Schema::hasTable('area_supervisor')) {
            $areaId = DB::table('area_supervisor')->where('user_id', $supervisorId)->value('area_id');
        }
        if (! $areaId) {
            $areaId = Area::query()->orderBy('id')->value('id');
        }

        $rows = [
            'Al Reef Gardens | Lawn Mowing Visit | Al Reef, Abu Dhabi, UAE | 60 min | AED 180.00',
            'Saadiyat Villas | Hedge Trimming Visit | Saadiyat Island, UAE | 90 min | AED 240.00',
            'Khalifa City A | Sprinkler Repair Visit | Khalifa City, Abu Dhabi, UAE | 45 min | AED 195.00',
            'Yas Acres | Garden Bed Weeding Visit | Yas Island, Abu Dhabi, UAE | 120 min | AED 310.00',
            'Madinat Zayed | Tree Health Check Visit | Madinat Zayed, Abu Dhabi, UAE | 75 min | AED 265.00',
            'Al Raha Beach | Fertilizer Application Visit | Al Raha, Abu Dhabi, UAE | 50 min | AED 175.00',
            'Baniyas East | Palm Cleaning Visit | Baniyas, Abu Dhabi, UAE | 100 min | AED 290.00',
            'Shakhbout City | Irrigation Audit Visit | Shakhbout City, UAE | 80 min | AED 225.00',
            'Mohammed Bin Zayed City | Soil PH Test Visit | MBZ City, Abu Dhabi, UAE | 40 min | AED 145.00',
            'Al Mushrif | Seasonal Planting Visit | Al Mushrif, Abu Dhabi, UAE | 150 min | AED 420.00',
        ];

        $prices = [180, 240, 195, 310, 265, 175, 290, 225, 145, 420];
        $base = Carbon::today();

        foreach ($rows as $i => $pipeLine) {
            Visit::query()->create([
                'subscription_id' => $subscription->id,
                'technician_id' => null,
                'supervisor_id' => $supervisorId,
                'area_id' => $areaId,
                'scheduled_date' => $base->copy()->addDays($i)->toDateString(),
                'status' => 'pending',
                'notes' => self::NOTES_PREFIX . ' ' . $pipeLine,
                'price' => $prices[$i],
            ]);
        }

        $this->command->info('Added 10 new visits for subscription id ' . $subscription->id . ' (notes start with ' . self::NOTES_PREFIX . ').');
    }
}
