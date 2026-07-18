<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tambah 'travel_agent' ke enum role
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('admin','sales','accountant','guide','driver','tour_leader','travel_agent') NOT NULL DEFAULT 'sales'");
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('supplier_id')
                  ->nullable()
                  ->after('role')
                  ->constrained('suppliers')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['supplier_id']);
            $table->dropColumn('supplier_id');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE users MODIFY role ENUM('admin','sales','accountant','guide','driver','tour_leader') NOT NULL DEFAULT 'sales'");
        }
    }
};
