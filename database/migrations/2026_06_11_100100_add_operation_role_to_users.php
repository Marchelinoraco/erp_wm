<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Tambah role 'operation' (operasional — eksekusi booking supplier).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('admin','sales','accountant','guide','driver','tour_leader','travel_agent','operation') NOT NULL DEFAULT 'sales'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY role ENUM('admin','sales','accountant','guide','driver','tour_leader','travel_agent') NOT NULL DEFAULT 'sales'");
    }
};
