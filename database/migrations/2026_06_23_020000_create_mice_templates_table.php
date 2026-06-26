<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mice_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');                 // mis. "Paket Meeting Standar"
            $table->text('description')->nullable(); // catatan singkat untuk sales
            // Array item: [{label, pax_mode, unit_sell, qty, nights, notes}]
            $table->json('items');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mice_templates');
    }
};
