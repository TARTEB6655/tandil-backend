<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_partnership_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('badge_color')->default('slate');
            $table->decimal('price', 10, 2);
            $table->string('currency', 3)->default('AED');
            $table->unsignedSmallInteger('duration_months');
            $table->unsignedInteger('required_products_min')->default(0);
            $table->unsignedInteger('required_products_max')->nullable();
            $table->unsignedInteger('max_product_listings')->nullable();
            $table->unsignedSmallInteger('max_partner_product_images')->default(1);
            $table->string('marketing_exposure')->default('low');
            $table->unsignedSmallInteger('social_media_posts_per_month')->default(0);
            $table->unsignedSmallInteger('app_banners')->default(0);
            $table->string('home_banner_size')->default('none');
            $table->json('benefits')->nullable();
            $table->json('features')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('vendor_partnerships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tier_id')->constrained('vendor_partnership_tiers')->restrictOnDelete();
            $table->string('status')->default('active');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('next_payment_at')->nullable();
            $table->string('payment_method')->nullable();
            $table->unsignedInteger('estimated_products')->nullable();
            $table->text('business_description')->nullable();
            $table->string('contact_phone')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('application_id')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
        });

        Schema::create('vendor_partnership_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tier_id')->constrained('vendor_partnership_tiers')->restrictOnDelete();
            $table->string('type')->default('new');
            $table->unsignedInteger('estimated_products');
            $table->text('business_description');
            $table->string('contact_phone');
            $table->string('payment_method');
            $table->string('status')->default('pending');
            $table->text('admin_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['vendor_id', 'status']);
        });

        Schema::table('vendor_partnerships', function (Blueprint $table) {
            $table->foreign('application_id')
                ->references('id')
                ->on('vendor_partnership_applications')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('vendor_partnerships', function (Blueprint $table) {
            $table->dropForeign(['application_id']);
        });
        Schema::dropIfExists('vendor_partnership_applications');
        Schema::dropIfExists('vendor_partnerships');
        Schema::dropIfExists('vendor_partnership_tiers');
    }
};
