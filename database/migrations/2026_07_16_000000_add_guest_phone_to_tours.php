<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor telepon tamu (pelengkap guest_name) — dipakai guide/sopir untuk
 * menghubungi tamu langsung saat customer bertipe buyer, tanpa membuka
 * kontak buyer (travel agent) itu sendiri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->string('guest_phone')->nullable()->after('guest_name');
        });
    }

    public function down(): void
    {
        Schema::table('tours', function (Blueprint $table) {
            $table->dropColumn('guest_phone');
        });
    }
};
