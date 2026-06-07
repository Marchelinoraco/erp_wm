<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Welcome Manado ERP — Core MVP tables
 *
 * Filosofi: 1 ide inti. Setiap produk punya `cost` & `sell`.
 * Tour = kumpulan baris produk (tour_items) dengan harga di-SNAPSHOT.
 * Costing, quotation, dan profit semuanya TURUNAN dari data ini —
 * bukan modul terpisah.
 *
 * Target: MySQL 8.0+ (butuh stored generated columns).
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. SUPPLIERS — hotel, transport, guide, resto, attraction
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->nullable()->index(); // hotel|transport|guide|restaurant|attraction|other
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 2. PRODUCTS — inti master data. cost = modal, sell = harga jual
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->index();                 // hotel|transport|guide|restaurant|attraction|other
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('unit')->default('per_pax');       // per_pax|per_unit|per_night
            $table->decimal('cost', 15, 2)->default(0);        // harga modal
            $table->decimal('sell', 15, 2)->default(0);        // harga jual
            $table->char('currency', 3)->default('IDR');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 3. CUSTOMERS — agent / corporate / direct
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type')->default('direct')->index(); // agent|corporate|direct
            $table->string('country')->nullable()->index();      // Korea|Malaysia|Singapore|Indonesia|...
            $table->string('contact_person')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 4. TOURS — header. status = pipeline CRM
        Schema::create('tours', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();                  // WM-2026-0001
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('title')->nullable();               // "4D3N Manado"
            $table->unsignedInteger('pax')->default(1);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            // pipeline: inquiry|quotation_draft|quotation_sent|follow_up|negotiation|confirmed|cancelled
            $table->string('status')->default('inquiry')->index();
            $table->string('sales_person')->nullable();
            $table->decimal('default_markup', 5, 2)->default(0); // % opsional utk auto-isi item baru
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 5. TOUR_ITEMS — baris produk dalam tour. Harga DI-SNAPSHOT.
        Schema::create('tour_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained('tours')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->unsignedInteger('day_number')->nullable();
            $table->string('product_type')->nullable();        // disalin dari product saat dibuat
            $table->string('description')->nullable();          // bebas, mis. "Hotel Swiss-Bel 3 malam"
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

        // 6. ASSIGNMENTS — operation: guide/driver per tour (untuk manifest & share link)
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained('tours')->cascadeOnDelete();
            $table->string('role')->index();                   // guide|driver|tour_leader
            $table->string('person_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('vehicle')->nullable();             // mis. "Innova B 1234 XX"
            $table->time('pickup_time')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('tour_items');
        Schema::dropIfExists('tours');
        Schema::dropIfExists('customers');
        Schema::dropIfExists('products');
        Schema::dropIfExists('suppliers');
    }
};
