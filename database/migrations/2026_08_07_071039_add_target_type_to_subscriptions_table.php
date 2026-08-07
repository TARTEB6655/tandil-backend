<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTargetTypeToSubscriptionsTable extends Migration
{
    /**
     * Run the migrations.
     * Adds target_type column to distinguish between:
     *   - 'all_users'        → Subscription plan visible/assigned to every client
     *   - 'specific_clients' → Subscription assigned to selected clients only
     */
    public function up()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'target_type')) {
                // 'all_users' = for every client | 'specific_clients' = selected clients only
                $table->string('target_type', 20)->default('specific_clients')->after('apply_to_all');
            }
        });
    }

    public function down()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('subscriptions', 'target_type')) {
                $table->dropColumn('target_type');
            }
        });
    }
}
