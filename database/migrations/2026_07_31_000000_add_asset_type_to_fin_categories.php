<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap A — lihat docs/superpowers/specs/2026-07-31-master-karyawan-design.md §3.2.
 *
 * Kategori keuangan mendapat tipe ketiga 'asset'. Kategori bertipe asset tersedia
 * untuk KEDUA arah transaksi: 'out' menaikkan saldo aset (memberi kas bon),
 * 'in' menurunkannya.
 *
 * Dua driver ditangani berbeda dan ini disengaja:
 * - MySQL: ALTER ... MODIFY ENUM, mengikuti pola migrasi enum lain di repo ini.
 * - SQLite (dipakai uji): $table->enum() menghasilkan VARCHAR + CHECK constraint.
 *   CHECK tidak bisa diubah lewat ALTER, jadi kolomnya dibangun ulang sebagai
 *   VARCHAR polos. Tanpa ini, setiap uji yang menyimpan tipe 'asset' gagal.
 *
 * Validasi nilai pindah ke lapisan aplikasi (FinCategory::TYPES) — tempat yang
 * memang seharusnya, dan tidak menuntut migrasi ALTER tiap ada nilai baru.
 *
 * Fix round 1 (review Task 1):
 * - up() SQLite: tiap sub-langkah (buat type_baru / pindah data & drop type lama /
 *   rename type_baru → type) dipagari Schema::hasColumn miliknya sendiri, bukan satu
 *   guard di depan. Kalau migrasi terhenti di tengah, retry melanjutkan dari langkah
 *   yang belum selesai, bukan langsung return seolah semua sudah beres.
 * - down(): menolak berjalan (RuntimeException) selama masih ada kategori bertipe
 *   'asset'. Mengubah baris tersebut diam-diam jadi 'expense' saat rollback akan
 *   menggeser Laba Rugi dan Neraca — jauh lebih berbahaya daripada rollback yang gagal.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE fin_categories MODIFY type ENUM('income','expense','asset') NOT NULL");

            return;
        }

        if (! Schema::hasColumn('fin_categories', 'type_baru')) {
            Schema::table('fin_categories', function (Blueprint $table) {
                $table->string('type_baru', 20)->nullable();
            });
        }

        if (Schema::hasColumn('fin_categories', 'type')) {
            DB::statement('UPDATE fin_categories SET type_baru = type');

            Schema::table('fin_categories', function (Blueprint $table) {
                $table->dropColumn('type');
            });
        }

        if (Schema::hasColumn('fin_categories', 'type_baru')) {
            Schema::table('fin_categories', function (Blueprint $table) {
                $table->renameColumn('type_baru', 'type');
            });
        }
    }

    public function down(): void
    {
        $jumlahAset = DB::table('fin_categories')->where('type', 'asset')->count();

        if ($jumlahAset > 0) {
            throw new RuntimeException(
                "Rollback dibatalkan: masih ada {$jumlahAset} kategori bertipe 'asset'. "
                . "Mengubahnya jadi 'expense' akan menggeser Laba Rugi dan Neraca. "
                . "Pindahkan atau hapus kategori tersebut lebih dulu, baru rollback."
            );
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE fin_categories MODIFY type ENUM('income','expense') NOT NULL");
        }

        // SQLite: CHECK constraint asli tidak dipulihkan. Basis data uji selalu
        // dibangun dari nol, jadi tidak ada yang bergantung padanya.
    }
};
