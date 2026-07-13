<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permintaan biaya tambahan dari sales (mis. biaya tak terduga saat tour
 * berjalan) — Rincian Profit sudah terkunci setelah invoice disetujui, jadi
 * ini jalur resmi terpisah. Akuntan verifikasi (boleh sesuaikan nominal)
 * lalu otomatis jadi Bill, atau tolak dengan alasan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cost_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tour_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('category', ['hotel', 'transport', 'guide', 'restaurant', 'attraction', 'other'])
                  ->default('other');
            $table->string('description');
            $table->decimal('amount', 15, 2);
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->foreignId('bill_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cost_requests');
    }
};
