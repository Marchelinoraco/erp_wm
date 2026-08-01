<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tahap A — lihat docs/superpowers/specs/2026-07-31-master-karyawan-design.md §3.2.
 *
 * Tiga perubahan pada fin_transactions:
 * 1. contra_fin_category_id — lawan transaksi boleh berupa kategori, bukan hanya
 *    akun kas. Transaksi seperti itu TIDAK menyentuh kas.
 * 2. cash_account_id jadi nullable — konsekuensi dari (1).
 * 3. source menerima 'advance' (kas bon) dan 'payroll' (gajian).
 *
 * Aturan "tepat satu dari cash_account_id / contra_fin_category_id" ditegakkan di
 * lapisan model (FinTransaction), bukan lewat CHECK constraint basis data, supaya
 * perilakunya sama di MySQL dan SQLite dan pesan galatnya bisa dibaca manusia.
 *
 * Pola idempotensi (review Task 1, lihat 2026_07_31_000000_add_asset_type_to_fin_categories.php):
 * tiap sub-langkah dipagari kondisinya sendiri, BUKAN satu guard tunggal membungkus
 * seluruh blok SQLite. Kalau migrasi terhenti di tengah, retry harus melanjutkan
 * dari titik yang belum selesai, bukan langsung dilewati seolah semua sudah beres.
 *
 * Perbedaan dari pola Task 1: di sini ada DUA kolom yang dibangun ulang sekaligus
 * (cash_account_id, source), dan Laravel mengeksekusi dropColumn/renameColumn di
 * SQLite modern (>=3.35, yang dipakai untuk uji) sebagai SATU ALTER TABLE per
 * kolom yang ter-commit sendiri-sendiri (SQLiteGrammar tidak membungkusnya dalam
 * transaksi skema — lihat Grammar::$transactions bernilai false untuk SQLite).
 * Karena itu setiap kolom dipagari & diproses independen, bukan sebagai satu
 * paket "dua kolom sekaligus" — supaya migrasi tetap bisa dilanjutkan walau
 * terhenti PERSIS di antara pemrosesan cash_account_id dan source.
 *
 * cash_account_id juga masih terikat FOREIGN KEY ke cash_accounts (dibuat di
 * 2026_06_18_000000_create_finance_ledger_tables.php). SQLite menolak DROP COLUMN
 * atas kolom yang masih jadi bagian definisi FOREIGN KEY tabel itu sendiri
 * ("unknown column ... in foreign key definition") — jadi FK itu harus dilepas
 * lebih dulu (dropForeign) sebelum kolomnya bisa di-drop. Kolom pengganti sengaja
 * dibuat TANPA FK: aturannya sudah ditegakkan di lapisan model (lihat di atas),
 * dan basis data uji SQLite selalu dibangun ulang dari nol sehingga tak ada yang
 * bergantung pada FK tersebut tetap ada.
 *
 * source juga masih terpakai di index komposit fin_transactions_source_source_id_index
 * (dari migrasi yang sama). SQLite menolak DROP COLUMN atas kolom yang masih jadi
 * bagian index ("error in index ... after drop column: no such column"), jadi
 * index itu harus dilepas lebih dulu dan dibuat ulang setelah kolomnya di-rename
 * kembali ke nama final. Ditemukan lewat uji suite penuh (bukan hanya smoke test
 * migrasi tunggal) — lihat task-2-report.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fin_transactions', function (Blueprint $table) {
            if (! Schema::hasColumn('fin_transactions', 'contra_fin_category_id')) {
                $table->foreignId('contra_fin_category_id')
                    ->nullable()
                    ->after('fin_category_id')
                    ->constrained('fin_categories')
                    ->restrictOnDelete();
            }
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE fin_transactions MODIFY cash_account_id BIGINT UNSIGNED NULL');
            DB::statement("ALTER TABLE fin_transactions MODIFY source ENUM('manual','invoice','bill','advance','payroll') NOT NULL DEFAULT 'manual'");

            return;
        }

        // --- Langkah 1: siapkan kolom perantara (masing-masing dipagari sendiri) ---
        if (! Schema::hasColumn('fin_transactions', 'cash_account_id_baru')) {
            Schema::table('fin_transactions', function (Blueprint $table) {
                $table->unsignedBigInteger('cash_account_id_baru')->nullable();
            });
        }

        if (! Schema::hasColumn('fin_transactions', 'source_baru')) {
            Schema::table('fin_transactions', function (Blueprint $table) {
                // ->default('manual') dipertahankan supaya perilakunya sama dengan
                // kolom asli (dan cabang MySQL) -- fix round 1 (review): tanpa ini
                // FinTransaction yang dibuat tanpa 'source' eksplisit jadi NULL di
                // SQLite/uji tapi 'manual' di MySQL/production.
                $table->string('source_baru', 20)->nullable()->default('manual');
            });
        }

        // --- Langkah 2: pindahkan data (dipagari keberadaan kolom asal) ---
        if (Schema::hasColumn('fin_transactions', 'cash_account_id')) {
            DB::statement('UPDATE fin_transactions SET cash_account_id_baru = cash_account_id');
        }

        if (Schema::hasColumn('fin_transactions', 'source')) {
            DB::statement('UPDATE fin_transactions SET source_baru = source');
        }

        // --- Langkah 3: lepas FK sebelum drop kolom (dipagari: FK itu masih ada?) ---
        $cashAccountIdMasihBerFk = collect(Schema::getForeignKeys('fin_transactions'))
            ->contains(fn ($fk) => in_array('cash_account_id', $fk['columns'], true));

        if ($cashAccountIdMasihBerFk) {
            Schema::table('fin_transactions', function (Blueprint $table) {
                $table->dropForeign(['cash_account_id']);
            });
        }

        // --- Langkah 4: lepas index komposit sebelum drop kolom source (dipagari:
        // index itu masih ada?) ---
        $namaIndexSource = 'fin_transactions_source_source_id_index';

        if (in_array($namaIndexSource, Schema::getIndexListing('fin_transactions'), true)) {
            Schema::table('fin_transactions', function (Blueprint $table) {
                $table->dropIndex(['source', 'source_id']);
            });
        }

        // --- Langkah 5: drop kolom lama (dipagari keberadaannya sendiri) ---
        if (Schema::hasColumn('fin_transactions', 'cash_account_id')) {
            Schema::table('fin_transactions', function (Blueprint $table) {
                $table->dropColumn('cash_account_id');
            });
        }

        if (Schema::hasColumn('fin_transactions', 'source')) {
            Schema::table('fin_transactions', function (Blueprint $table) {
                $table->dropColumn('source');
            });
        }

        // --- Langkah 6: rename kolom perantara jadi nama final (dipagari sendiri) ---
        if (Schema::hasColumn('fin_transactions', 'cash_account_id_baru')) {
            Schema::table('fin_transactions', function (Blueprint $table) {
                $table->renameColumn('cash_account_id_baru', 'cash_account_id');
            });
        }

        if (Schema::hasColumn('fin_transactions', 'source_baru')) {
            Schema::table('fin_transactions', function (Blueprint $table) {
                $table->renameColumn('source_baru', 'source');
            });
        }

        // --- Langkah 7: buat ulang index komposit (dipagari: sudah ada?) ---
        if (! in_array($namaIndexSource, Schema::getIndexListing('fin_transactions'), true)) {
            Schema::table('fin_transactions', function (Blueprint $table) {
                $table->index(['source', 'source_id']);
            });
        }
    }

    public function down(): void
    {
        $jumlahNonKas = DB::table('fin_transactions')->whereNull('cash_account_id')->count();
        $jumlahSourceBaru = DB::table('fin_transactions')->whereIn('source', ['advance', 'payroll'])->count();

        if ($jumlahNonKas > 0 || $jumlahSourceBaru > 0) {
            throw new RuntimeException(
                "Rollback dibatalkan: ada {$jumlahNonKas} transaksi non-kas (cash_account_id NULL) dan "
                . "{$jumlahSourceBaru} transaksi bersumber 'advance'/'payroll'. Mengembalikan cash_account_id "
                . "jadi NOT NULL atau source ke enum lama akan merusak baris tersebut. "
                . 'Hapus atau migrasikan baris tersebut lebih dulu, baru rollback.'
            );
        }

        Schema::table('fin_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contra_fin_category_id');
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE fin_transactions MODIFY source ENUM('manual','invoice','bill') NOT NULL DEFAULT 'manual'");
            DB::statement('ALTER TABLE fin_transactions MODIFY cash_account_id BIGINT UNSIGNED NOT NULL');
        }

        // SQLite: NOT NULL pada cash_account_id dan CHECK constraint lama pada
        // source tidak dipulihkan — sama seperti precedent Task 1 pada
        // fin_categories.type. Basis data uji selalu dibangun ulang dari nol,
        // jadi tak ada yang bergantung pada keduanya kembali seperti semula.
    }
};
