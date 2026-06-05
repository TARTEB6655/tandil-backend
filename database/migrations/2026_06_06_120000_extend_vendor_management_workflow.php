<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('category_vendor')) {
            Schema::create('category_vendor', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
                $table->foreignId('category_id')->constrained()->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['vendor_id', 'category_id'], 'cat_vendor_unique');
            });
        }

        Schema::table('vendor_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('vendor_profiles', 'years_in_business')) {
                $table->unsignedSmallInteger('years_in_business')->nullable()->after('description');
            }
            if (! Schema::hasColumn('vendor_profiles', 'onboarding_completed_at')) {
                $table->timestamp('onboarding_completed_at')->nullable()->after('years_in_business');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendor_profiles', function (Blueprint $table) {
            foreach (['years_in_business', 'onboarding_completed_at'] as $col) {
                if (Schema::hasColumn('vendor_profiles', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::dropIfExists('category_vendor');
    }
};
