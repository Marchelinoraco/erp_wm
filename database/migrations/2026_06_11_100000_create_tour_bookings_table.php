<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TOUR_BOOKINGS — jembatan operasional antara Sales (confirmed) dan Keuangan (AP).
 *
 * Saat tour confirmed, otomatis dibuat 1 baris booking per supplier (dari tour_items).
 * Operation eksekusi: catat no. konfirmasi + harga deal → status 'booked' →
 * otomatis membuat Bill (AP) di Keuangan. Profit riil jadi akurat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tour_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained('tours')->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->string('description');                       // nama supplier / deskripsi booking
            $table->string('category')->default('other');        // hotel|transport|guide|restaurant|attraction|other
            $table->decimal('est_cost', 15, 2)->default(0);      // perkiraan (snapshot tour_items)
            $table->decimal('actual_cost', 15, 2)->nullable();   // harga deal final yg di-booking
            $table->string('status')->default('pending')->index(); // pending|booked|cancelled
            $table->string('booking_ref')->nullable();           // no. konfirmasi dari supplier
            $table->timestamp('booked_at')->nullable();
            $table->string('booked_by')->nullable();             // nama user yg booking
            $table->foreignId('bill_id')->nullable()->constrained('bills')->nullOnDelete(); // tagihan AP terkait
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['tour_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tour_bookings');
    }
};
