<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            // Kelompok penyusutan fiskal (PMK 96/2009) — nullable sampai user mengisi
            $table->string('fiscal_group', 30)->nullable()->after('useful_life_years');
        });
    }

    public function down(): void
    {
        Schema::table('fixed_assets', function (Blueprint $table) {
            $table->dropColumn('fiscal_group');
        });
    }
};
