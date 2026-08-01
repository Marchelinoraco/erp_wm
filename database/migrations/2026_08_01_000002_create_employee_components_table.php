<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap B §4.1 / D4: komponen tetap per karyawan (tunjangan/potongan), ikut
 * otomatis di draft gajian tanpa diketik ulang tiap bulan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_components', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['tunjangan', 'potongan']);
            $table->decimal('amount', 15, 2);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_components');
    }
};
