<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropForeign(['subscription_id']);
        });
        Schema::table('visits', function (Blueprint $table) {
            $table->unsignedBigInteger('subscription_id')->nullable()->change();
        });
        Schema::table('visits', function (Blueprint $table) {
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropForeign(['subscription_id']);
        });
        Schema::table('visits', function (Blueprint $table) {
            $table->unsignedBigInteger('subscription_id')->nullable(false)->change();
        });
        Schema::table('visits', function (Blueprint $table) {
            $table->foreign('subscription_id')->references('id')->on('subscriptions')->cascadeOnDelete();
        });
    }
};
