<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap B §4.1 D11: menyimpan SALINAN nama/jabatan/gaji pokok — bukan
 * merujuk employees — supaya slip lama tidak ikut berubah saat master
 * diedit. net_amount DISIMPAN (bukan dihitung), angka final yang beku.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_id')->constrained('payrolls')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->string('employee_name');
            $table->string('position')->nullable();
            $table->decimal('base_salary', 15, 2);
            $table->decimal('net_amount', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
    }
};
