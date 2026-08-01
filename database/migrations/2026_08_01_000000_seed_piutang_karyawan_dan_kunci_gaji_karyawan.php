<?php

use App\Models\FinCategory;
use Illuminate\Database\Migrations\Migration;

/**
 * Tahap B §4.1 — kas bon dibukukan sebagai Piutang Karyawan (D7), dan
 * pelunasannya membebani kategori Gaji Karyawan yang sudah ada.
 *
 * Kedua kategori dikunci is_system=true supaya tidak bisa dihapus lewat
 * layar Transaksi (FinanceLedgerController::destroyCategory sudah menolak
 * kategori is_system) — mencegah payroll gagal karena kategori acuannya
 * hilang. "Gaji Karyawan" sebelumnya is_system=false; dikunci di sini karena
 * baru sekarang ia jadi kategori yang KODE (bukan cuma manusia) bergantung
 * padanya lewat pencarian nama.
 */
return new class extends Migration
{
    public function up(): void
    {
        FinCategory::updateOrCreate(
            ['name' => 'Piutang Karyawan'],
            ['type' => 'asset', 'is_system' => true, 'sort_order' => (int) FinCategory::max('sort_order') + 1]
        );

        FinCategory::where('name', 'Gaji Karyawan')->update(['is_system' => true]);
    }

    public function down(): void
    {
        FinCategory::where('name', 'Gaji Karyawan')->update(['is_system' => false]);
        FinCategory::where('name', 'Piutang Karyawan')->where('is_system', true)->delete();
    }
};
