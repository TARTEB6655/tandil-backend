<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_notification_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('message');
            $table->string('scope_type', 32);
            $table->string('scope_role', 64)->nullable();
            $table->json('messages_by_role')->nullable();
            $table->unsignedInteger('recipient_client_count')->default(0);
            $table->unsignedInteger('recipient_technician_count')->default(0);
            $table->unsignedInteger('recipient_supervisor_count')->default(0);
            $table->unsignedInteger('recipient_area_manager_count')->default(0);
            $table->unsignedInteger('recipient_hr_count')->default(0);
            $table->unsignedInteger('recipient_admin_count')->default(0);
            $table->unsignedInteger('recipient_other_count')->default(0);
            $table->unsignedInteger('total_recipients')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_notification_broadcasts');
    }
};
