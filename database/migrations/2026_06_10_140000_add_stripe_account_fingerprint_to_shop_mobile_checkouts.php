<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_mobile_checkouts', function (Blueprint $table) {
            if (! Schema::hasColumn('shop_mobile_checkouts', 'stripe_account_fingerprint')) {
                $table->string('stripe_account_fingerprint', 64)->nullable()->after('stripe_payment_intent_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('shop_mobile_checkouts', function (Blueprint $table) {
            if (Schema::hasColumn('shop_mobile_checkouts', 'stripe_account_fingerprint')) {
                $table->dropColumn('stripe_account_fingerprint');
            }
        });
    }
};
