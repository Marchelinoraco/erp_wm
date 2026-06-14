<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('label');
            $table->unsignedSmallInteger('qty')->default(1);
            $table->unsignedSmallInteger('nights')->default(1);
            $table->decimal('unit_sell', 15, 2)->default(0);
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('proposed'); // proposed | approved | rejected
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotation_items');
    }
};
