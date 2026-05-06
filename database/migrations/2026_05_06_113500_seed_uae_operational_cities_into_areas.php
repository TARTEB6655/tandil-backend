<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }
        if (! Schema::hasTable('areas')) {
            return;
        }

        $hasDescription = Schema::hasColumn('areas', 'description');
        $hasIsActive = Schema::hasColumn('areas', 'is_active');
        $hasPriority = Schema::hasColumn('areas', 'priority');
        $hasCreatedAt = Schema::hasColumn('areas', 'created_at');
        $hasUpdatedAt = Schema::hasColumn('areas', 'updated_at');

        $rows = [
            ['name' => 'Abu Dhabi City', 'country' => 'UAE', 'location' => 'Abu Dhabi'],
            ['name' => 'Al Ain', 'country' => 'UAE', 'location' => 'Abu Dhabi'],
            ['name' => 'Madinat Zayed', 'country' => 'UAE', 'location' => 'Abu Dhabi'],
            ['name' => 'Ruwais', 'country' => 'UAE', 'location' => 'Abu Dhabi'],
            ['name' => 'Ghayathi', 'country' => 'UAE', 'location' => 'Abu Dhabi'],
            ['name' => 'Liwa Oasis', 'country' => 'UAE', 'location' => 'Abu Dhabi'],
            ['name' => 'Al Shahama', 'country' => 'UAE', 'location' => 'Abu Dhabi'],
            ['name' => 'Baniyas', 'country' => 'UAE', 'location' => 'Abu Dhabi'],
            ['name' => 'Mussafah', 'country' => 'UAE', 'location' => 'Abu Dhabi'],
            ['name' => 'Dubai City', 'country' => 'UAE', 'location' => 'Dubai'],
            ['name' => 'Jebel Ali', 'country' => 'UAE', 'location' => 'Dubai'],
            ['name' => 'Hatta', 'country' => 'UAE', 'location' => 'Dubai'],
            ['name' => 'Sharjah City', 'country' => 'UAE', 'location' => 'Sharjah'],
            ['name' => 'Khor Fakkan', 'country' => 'UAE', 'location' => 'Sharjah'],
            ['name' => 'Kalba', 'country' => 'UAE', 'location' => 'Sharjah'],
            ['name' => 'Dibba Al-Hisn', 'country' => 'UAE', 'location' => 'Sharjah'],
            ['name' => 'Al Dhaid', 'country' => 'UAE', 'location' => 'Sharjah'],
            ['name' => 'Ajman City', 'country' => 'UAE', 'location' => 'Ajman'],
            ['name' => 'Masfout', 'country' => 'UAE', 'location' => 'Ajman'],
            ['name' => 'Manama', 'country' => 'UAE', 'location' => 'Ajman'],
            ['name' => 'Umm Al Quwain City', 'country' => 'UAE', 'location' => 'Umm Al Quwain'],
            ['name' => 'Falaj Al Mualla', 'country' => 'UAE', 'location' => 'Umm Al Quwain'],
            ['name' => 'Ras Al Khaimah City', 'country' => 'UAE', 'location' => 'Ras Al Khaimah'],
            ['name' => 'Al Jazirah Al Hamra', 'country' => 'UAE', 'location' => 'Ras Al Khaimah'],
            ['name' => 'Digdaga', 'country' => 'UAE', 'location' => 'Ras Al Khaimah'],
            ['name' => 'Khatt', 'country' => 'UAE', 'location' => 'Ras Al Khaimah'],
            ['name' => 'Fujairah City', 'country' => 'UAE', 'location' => 'Fujairah'],
            ['name' => 'Dibba Al-Fujairah', 'country' => 'UAE', 'location' => 'Fujairah'],
            ['name' => 'Mirbah', 'country' => 'UAE', 'location' => 'Fujairah'],
            ['name' => 'Qidfa', 'country' => 'UAE', 'location' => 'Fujairah'],
        ];

        foreach ($rows as $row) {
            $existing = DB::table('areas')
                ->where('name', $row['name'])
                ->where('country', 'UAE')
                ->first();

            $payload = [
                'name' => $row['name'],
                'country' => 'UAE',
                'location' => $row['location'],
            ];
            if ($hasDescription) {
                $payload['description'] = 'Operational city zone in UAE';
            }
            if ($hasIsActive) {
                $payload['is_active'] = 1;
            }
            if ($hasPriority) {
                $payload['priority'] = 100;
            }
            if ($hasUpdatedAt) {
                $payload['updated_at'] = now();
            }
            if ($hasCreatedAt) {
                $payload['created_at'] = now();
            }

            if ($existing) {
                DB::table('areas')
                    ->where('id', $existing->id)
                    ->update(array_diff_key($payload, array_flip(['created_at'])));
            } else {
                DB::table('areas')->insert($payload);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('areas')) {
            return;
        }

        $names = [
            'Abu Dhabi City', 'Al Ain', 'Madinat Zayed', 'Ruwais', 'Ghayathi', 'Liwa Oasis', 'Al Shahama', 'Baniyas', 'Mussafah',
            'Dubai City', 'Jebel Ali', 'Hatta',
            'Sharjah City', 'Khor Fakkan', 'Kalba', 'Dibba Al-Hisn', 'Al Dhaid',
            'Ajman City', 'Masfout', 'Manama',
            'Umm Al Quwain City', 'Falaj Al Mualla',
            'Ras Al Khaimah City', 'Al Jazirah Al Hamra', 'Digdaga', 'Khatt',
            'Fujairah City', 'Dibba Al-Fujairah', 'Mirbah', 'Qidfa',
        ];

        DB::table('areas')
            ->where('country', 'UAE')
            ->whereIn('name', $names)
            ->delete();
    }
};

