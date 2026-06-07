<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_itinerary_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('day_number');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->index(['tour_id', 'day_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_itinerary_days');
    }
};
