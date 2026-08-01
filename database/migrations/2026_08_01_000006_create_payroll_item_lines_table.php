<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap B §4.1 D10: kas bon otomatis (kind='kas_bon') selalu punya
 * employee_advance_id — sumber yang dipakai EmployeeAdvance::sisa() untuk
 * menghitung ulang sisa piutang. Baris tunjangan/potongan biasa tidak
 * merujuk kas bon apa pun (employee_advance_id null).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_item_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_item_id')->constrained('payroll_items')->cascadeOnDelete();
            $table->enum('kind', ['tunjangan', 'potongan', 'kas_bon']);
            $table->string('label');
            $table->decimal('amount', 15, 2);
            $table->foreignId('employee_advance_id')->nullable()->constrained('employee_advances')->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_item_lines');
    }
};
