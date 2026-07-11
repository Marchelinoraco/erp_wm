<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nama tamu per-tour — wajib diisi sales bila customer bertipe buyer
 * (travel agent yang membeli tour). Tim lapangan (guide/sopir/tour leader)
 * melihat nama ini menggantikan nama customer; relasi customer tetap buyer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->string('guest_name')->nullable()->after('title');
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn('guest_name');
        });
    }
};
