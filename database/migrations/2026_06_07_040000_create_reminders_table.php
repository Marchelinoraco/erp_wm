<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tour_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('notes')->nullable();
            $table->date('remind_at');
            $table->boolean('is_done')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'is_done', 'remind_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
