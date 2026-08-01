<?php

use App\Models\FinCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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
 *
 * Fix round 1: down() menolak berjalan kalau masih ada transaksi yang merujuk
 * kategori Piutang Karyawan. Menghapus diam-diam akan cascade-delete riwayat
 * transaksi kas bon tanpa peringatan.
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
        $piutangKaryawan = FinCategory::where('name', 'Piutang Karyawan')->first();

        if ($piutangKaryawan) {
            $jumlahTransaksi = DB::table('fin_transactions')
                ->where(function ($query) use ($piutangKaryawan) {
                    $query->where('fin_category_id', $piutangKaryawan->id)
                          ->orWhere('contra_fin_category_id', $piutangKaryawan->id);
                })
                ->count();

            if ($jumlahTransaksi > 0) {
                throw new RuntimeException(
                    "Rollback dibatalkan: masih ada {$jumlahTransaksi} transaksi yang merujuk "
                    . "kategori Piutang Karyawan (fin_category_id atau contra_fin_category_id). "
                    . "Menghapusnya akan cascade-delete riwayat kas bon. Pindahkan atau hapus "
                    . "transaksi tersebut lebih dulu, baru rollback."
                );
            }
        }

        FinCategory::where('name', 'Gaji Karyawan')->update(['is_system' => false]);
        FinCategory::where('name', 'Piutang Karyawan')->where('is_system', true)->delete();
    }
};
