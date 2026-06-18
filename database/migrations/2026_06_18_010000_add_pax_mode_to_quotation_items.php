<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            // shared = biaya grup (dibagi jumlah pax) · per_pax = biaya per orang (tetap)
            $table->enum('pax_mode', ['shared', 'per_pax'])->default('per_pax')->after('nights');
        });
    }

    public function down(): void
    {
        Schema::table('quotation_items', function (Blueprint $table) {
            $table->dropColumn('pax_mode');
        });
    }
};
