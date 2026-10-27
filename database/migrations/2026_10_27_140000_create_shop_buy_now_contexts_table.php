<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_buy_now_contexts', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary();
            $table->json('payload');
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_buy_now_contexts');
    }
};
