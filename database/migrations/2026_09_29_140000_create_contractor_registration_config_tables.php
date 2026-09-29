<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('contractor_banks')) {
            Schema::create('contractor_banks', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('name_ar')->nullable();
                $table->string('slug')->unique();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('contractor_cities')) {
            Schema::create('contractor_cities', function (Blueprint $table) {
                $table->id();
                $table->foreignId('emirate_id')->constrained('emirates')->cascadeOnDelete();
                $table->string('name');
                $table->string('name_ar')->nullable();
                $table->boolean('is_active')->default(true);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['emirate_id', 'name']);
            });
        }

        if (! Schema::hasTable('contractor_registration_fields')) {
            Schema::create('contractor_registration_fields', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->string('section', 64);
                $table->string('label');
                $table->string('label_ar')->nullable();
                $table->string('field_type', 32);
                $table->string('option_source', 64)->nullable();
                $table->boolean('is_required')->default(false);
                $table->boolean('is_enabled')->default(true);
                $table->boolean('is_multiple')->default(false);
                $table->unsignedInteger('sort_order')->default(0);
                $table->string('placeholder')->nullable();
                $table->string('placeholder_ar')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('vendor_area')) {
            Schema::create('vendor_area', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->foreignId('area_id')->constrained('areas')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['vendor_id', 'area_id']);
            });
        }

        if (! Schema::hasTable('service_vendor')) {
            Schema::create('service_vendor', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
                $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['vendor_id', 'service_id']);
            });
        }

        Schema::table('vendor_profiles', function (Blueprint $table) {
            if (! Schema::hasColumn('vendor_profiles', 'trade_license_expiry')) {
                $table->date('trade_license_expiry')->nullable()->after('trade_license_number');
            }
            if (! Schema::hasColumn('vendor_profiles', 'bank_account_number')) {
                $table->string('bank_account_number', 64)->nullable()->after('iban');
            }
            if (! Schema::hasColumn('vendor_profiles', 'bank_id')) {
                $table->foreignId('bank_id')->nullable()->after('bank_name')->constrained('contractor_banks')->nullOnDelete();
            }
            if (! Schema::hasColumn('vendor_profiles', 'city_id')) {
                $table->foreignId('city_id')->nullable()->after('city')->constrained('contractor_cities')->nullOnDelete();
            }
            if (! Schema::hasColumn('vendor_profiles', 'admin_review_message')) {
                $table->text('admin_review_message')->nullable()->after('years_in_business');
            }
            if (! Schema::hasColumn('vendor_profiles', 'documents_requested_at')) {
                $table->timestamp('documents_requested_at')->nullable()->after('admin_review_message');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendor_profiles', function (Blueprint $table) {
            foreach (['documents_requested_at', 'admin_review_message', 'city_id', 'bank_id', 'bank_account_number', 'trade_license_expiry'] as $col) {
                if (Schema::hasColumn('vendor_profiles', $col)) {
                    if (in_array($col, ['bank_id', 'city_id'], true)) {
                        $table->dropConstrainedForeignId($col);
                    } else {
                        $table->dropColumn($col);
                    }
                }
            }
        });

        Schema::dropIfExists('service_vendor');
        Schema::dropIfExists('vendor_area');
        Schema::dropIfExists('contractor_registration_fields');
        Schema::dropIfExists('contractor_cities');
        Schema::dropIfExists('contractor_banks');
    }
};
