<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loyalty_rewards', function (Blueprint $table) {
            if (! Schema::hasColumn('loyalty_rewards', 'expires_at')) {
                $table->date('expires_at')->nullable()->after('is_active');
            }
            if (! Schema::hasColumn('loyalty_rewards', 'cities')) {
                $table->string('cities', 500)->nullable()->after('expires_at');
            }
            if (! Schema::hasColumn('loyalty_rewards', 'customer_targeting')) {
                $table->string('customer_targeting', 20)->default('all')->after('cities');
            }
            if (! Schema::hasColumn('loyalty_rewards', 'specific_customer_ids')) {
                $table->json('specific_customer_ids')->nullable()->after('customer_targeting');
            }
        });

        if (! Schema::hasTable('loyalty_campaigns')) {
            Schema::create('loyalty_campaigns', function (Blueprint $table) {
                $table->id();
                $table->string('title', 160);
                $table->decimal('multiplier', 4, 2)->default(2);
                $table->date('start_date');
                $table->date('end_date');
                $table->string('cities', 500)->nullable();
                $table->string('customer_targeting', 20)->default('all');
                $table->json('specific_customer_ids')->nullable();
                $table->json('eligible_activities')->nullable();
                $table->text('notes')->nullable();
                $table->boolean('is_enabled')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_campaigns');

        Schema::table('loyalty_rewards', function (Blueprint $table) {
            foreach (['specific_customer_ids', 'customer_targeting', 'cities', 'expires_at'] as $col) {
                if (Schema::hasColumn('loyalty_rewards', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
