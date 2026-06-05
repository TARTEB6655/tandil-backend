<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_profiles', function (Blueprint $table) {
            $columns = [
                'trade_license_number' => fn () => $table->string('trade_license_number', 100)->nullable()->after('phone'),
                'vendor_type' => fn () => $table->string('vendor_type', 32)->nullable()->after('trade_license_number'),
                'emirate' => fn () => $table->string('emirate', 100)->nullable()->after('vendor_type'),
                'city' => fn () => $table->string('city', 100)->nullable()->after('emirate'),
                'google_maps_location' => fn () => $table->string('google_maps_location', 500)->nullable()->after('address'),
                'bank_name' => fn () => $table->string('bank_name', 191)->nullable()->after('google_maps_location'),
                'iban' => fn () => $table->string('iban', 64)->nullable()->after('bank_name'),
                'account_holder_name' => fn () => $table->string('account_holder_name', 191)->nullable()->after('iban'),
                'delivery_radius' => fn () => $table->decimal('delivery_radius', 8, 2)->nullable()->after('account_holder_name'),
                'operating_hours' => fn () => $table->string('operating_hours', 500)->nullable()->after('delivery_radius'),
                'minimum_order_amount' => fn () => $table->decimal('minimum_order_amount', 12, 2)->nullable()->after('operating_hours'),
                'terms_accepted_at' => fn () => $table->timestamp('terms_accepted_at')->nullable()->after('minimum_order_amount'),
            ];

            foreach ($columns as $name => $create) {
                if (! Schema::hasColumn('vendor_profiles', $name)) {
                    $create();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendor_profiles', function (Blueprint $table) {
            $columns = [
                'trade_license_number',
                'vendor_type',
                'emirate',
                'city',
                'google_maps_location',
                'bank_name',
                'iban',
                'account_holder_name',
                'delivery_radius',
                'operating_hours',
                'minimum_order_amount',
                'terms_accepted_at',
            ];

            foreach ($columns as $name) {
                if (Schema::hasColumn('vendor_profiles', $name)) {
                    $table->dropColumn($name);
                }
            }
        });
    }
};
