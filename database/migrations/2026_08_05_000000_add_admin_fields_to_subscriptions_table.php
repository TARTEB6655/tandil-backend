<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAdminFieldsToSubscriptionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (! Schema::hasColumn('subscriptions', 'plan_name')) {
                $table->string('plan_name')->nullable()->after('plan');
            }
            if (! Schema::hasColumn('subscriptions', 'subtitle')) {
                $table->string('subtitle')->nullable()->after('plan_name');
            }
            if (! Schema::hasColumn('subscriptions', 'features')) {
                $table->json('features')->nullable()->after('subtitle');
            }
            if (! Schema::hasColumn('subscriptions', 'apply_to_all')) {
                $table->boolean('apply_to_all')->default(false)->after('features');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            if (Schema::hasColumn('subscriptions', 'apply_to_all')) {
                $table->dropColumn('apply_to_all');
            }
            if (Schema::hasColumn('subscriptions', 'features')) {
                $table->dropColumn('features');
            }
            if (Schema::hasColumn('subscriptions', 'subtitle')) {
                $table->dropColumn('subtitle');
            }
            if (Schema::hasColumn('subscriptions', 'plan_name')) {
                $table->dropColumn('plan_name');
            }
        });
    }
}
