<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Example table: JSON columns per Spatie laravel-translatable convention.
     * Each column stores {"en":"...","ar":"...","ur":"..."}.
     */
    public function up(): void
    {
        Schema::create('localized_articles', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 160)->unique();
            $table->json('title');
            $table->json('description');
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('localized_articles');
    }
};
