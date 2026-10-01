<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('supervisor_registrations')) {
            Schema::create('supervisor_registrations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('status', 32)->default('pending')->index();
                $table->string('name');
                $table->string('phone', 32)->nullable();
                $table->string('email');
                $table->string('company_name');
                $table->string('trade_license_number', 100)->nullable();
                $table->date('trade_license_expiry_date')->nullable();
                $table->string('trn', 64)->nullable();
                $table->string('emirate', 100)->nullable();
                $table->string('city', 100)->nullable();
                $table->unsignedBigInteger('city_id')->nullable();
                $table->text('company_address')->nullable();
                $table->string('bank_name', 191)->nullable();
                $table->unsignedBigInteger('bank_id')->nullable();
                $table->string('account_holder_name', 191)->nullable();
                $table->string('bank_account_number', 64)->nullable();
                $table->string('iban', 64)->nullable();
                $table->json('main_service_categories')->nullable();
                $table->json('service_subcategories')->nullable();
                $table->json('selected_services')->nullable();
                $table->json('emirates')->nullable();
                $table->json('cities')->nullable();
                $table->json('service_coverage_areas')->nullable();
                $table->string('employee_id', 64)->nullable();
                $table->json('assigned_zone_ids')->nullable();
                $table->text('rejection_reason')->nullable();
                $table->text('admin_review_message')->nullable();
                $table->timestamp('documents_requested_at')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('rejected_at')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('supervisor_documents')) {
            Schema::create('supervisor_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('supervisor_registration_id')->constrained('supervisor_registrations')->cascadeOnDelete();
                $table->string('type', 64);
                $table->string('file_path');
                $table->string('original_name')->nullable();
                $table->string('verification_status', 32)->default('pending');
                $table->timestamps();
                $table->index(['supervisor_registration_id', 'type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supervisor_documents');
        Schema::dropIfExists('supervisor_registrations');
    }
};
