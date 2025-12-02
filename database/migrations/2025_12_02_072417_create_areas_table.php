<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique()->comment('Name of the area/region');
            $table->text('description')->nullable()->comment('Optional description of the area');
            $table->timestamps();
        });

        // Pivot table for area_supervisor relationship
        Schema::create('area_supervisor', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // supervisor user_id
            $table->timestamps();

            $table->unique(['area_id', 'user_id']);
        });

        // Pivot table for area_technician relationship
        Schema::create('area_technician', function (Blueprint $table) {
            $table->id();
            $table->foreignId('area_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade'); // technician user_id
            $table->timestamps();

            $table->unique(['area_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('area_technician');
        Schema::dropIfExists('area_supervisor');
        Schema::dropIfExists('areas');
    }
};
