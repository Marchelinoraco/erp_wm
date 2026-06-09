<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->string('status_snapshot');
            $table->string('type')->default('note');
            $table->text('description');
            $table->string('created_by')->nullable();
            $table->timestamps();
            $table->index(['tour_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_histories');
    }
};
