<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tandai Bill yang dibuat lewat tombol pintas "+ Bill" pada checklist
 * "Perlu Dibuatkan Bill" (Finance/Tour) — supaya checklist tahu item mana
 * yang sudah dibuatkan bill dan tidak menyarankan duplikat. Nullable: bill
 * yang dibuat manual (tanpa item asal) tetap berfungsi seperti biasa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->foreignId('invoice_item_id')->nullable()->after('supplier_id')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bills', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invoice_item_id');
        });
    }
};
