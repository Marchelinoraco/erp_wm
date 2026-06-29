<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rincian baris invoice — milik tiap invoice, TERPISAH dari tour_items.
 * tour_items = costing/estimasi internal; invoice_items = tagihan yang dibuat
 * & disetujui sales. Snapshot harga sama seperti tour_items (cost & sell → profit).
 *
 * Target: MySQL 8.0+ (butuh stored generated columns).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->string('product_type')->nullable();         // disalin dari product saat dibuat
            $table->string('description')->nullable();           // bebas, mis. "Hotel Swiss-Bel 3 malam"
            $table->unsignedInteger('qty')->default(1);
            $table->unsignedInteger('nights')->default(1);
            // SNAPSHOT — disalin dari products saat item dibuat, JANGAN referensi live
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->decimal('unit_sell', 15, 2)->default(0);
            $table->char('currency', 3)->default('IDR');
            $table->unsignedInteger('sort_order')->default(0);

            // Kolom turunan (MySQL stored generated) — total per baris dihitung DB
            $table->decimal('line_cost', 15, 2)->storedAs('qty * nights * unit_cost');
            $table->decimal('line_sell', 15, 2)->storedAs('qty * nights * unit_sell');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
