<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fiscal_corrections', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->string('name', 200);                // Deskripsi koreksi
            $table->string('type', 20)->default('positive'); // positive | negative
            $table->decimal('amount', 15, 2);
            $table->text('notes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['year', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_corrections');
    }
};
