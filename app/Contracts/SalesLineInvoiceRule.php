<?php

namespace App\Contracts;

use App\Models\Invoice;
use App\Services\SalesLine\Multiplier;

/**
 * Aturan hitung tagihan untuk satu jenis penjualan.
 *
 * Tiap jenis (tour, hotel, guide, transport, mice, document, ticketing) punya
 * satu implementasi. Mengubah cara hitung satu jenis berarti menyentuh satu
 * berkas — jenis lain tidak terpengaruh.
 */
interface SalesLineInvoiceRule
{
    /** Label kolom harga di form & PDF, mis. "Harga / kamar / malam". */
    public function unitPriceLabel(): string;

    /**
     * Pengali beserta nilai AWAL saat invoice dibuat, diturunkan dari data tour
     * (pax atau rentang tanggal). Belum dipakai di jalur produksi pada Fase 1;
     * dialirkan lewat backfill dan store pada Fase 2.
     *
     * @return Multiplier[]
     */
    public function defaultMultipliers(Invoice $invoice): array;

    /** total = unit_price × hasil kali seluruh nilai Multiplier. Tanpa pembulatan. */
    public function calculateTotal(float $unitPrice, array $multipliers): float;

    /**
     * true  = profit dihitung dari tagihan customer (total_idr − Σ cost item)
     * false = profit dihitung per item (Σ sell − Σ cost)
     *
     * Satu-satunya aturan uang yang bercabang per jenis. Sebelumnya
     * terduplikasi di InvoicesPanel.vue, CostingPanel.vue, dan
     * InvoiceController::profitPdf() — tiga berkas yang harus disunting
     * serempak, tanpa galat apa pun bila salah satu terlupa.
     */
    public function profitFromRevenue(): bool;

    /**
     * Cara total disusun:
     *   'per_unit'   = unit_price × hasil kali pengali
     *   'line_items' = jumlah nominal baris deskripsi (unit_price diabaikan)
     *
     * Rental kerap menagih beberapa unit berbeda dengan harga masing-masing
     * (Avanza + Innova + biaya luar kota), yang tidak muat di satu harga satuan.
     */
    public function totalComposition(): string;

    /**
     * Di mana modal & harga jual per komponen dicatat:
     *   'tour_items'    = tabel tour_items, paket disusun sebelum invoice ada
     *   'invoice_items' = Rincian Profit di dalam invoice
     *
     * Rental tidak pernah menyusun paket di muka — modal dan jualnya baru
     * diketahui saat invoice dirinci, jadi `tour_items`-nya memang kosong dan
     * Ringkasan Biaya harus membacanya dari sana.
     */
    public function costingSource(): string;

    /**
     * true = baris bernominal invoice punya tanggal mulai DAN tanggal selesai.
     *
     * Sewa kendaraan dan menginap berjalan sepanjang rentang tanggal, sedangkan
     * biaya dokumen atau izin terjadi pada satu titik tanggal.
     */
    public function chargeLinesUseDateRange(): bool;

    /**
     * true = di PDF customer, baris bernominal menaruh rentang tanggal di kolom
     * kiri (tempat label) dan menurunkan nama/unit ke kolom kanan.
     *
     * SENGAJA terpisah dari chargeLinesUseDateRange(): hotel juga punya tanggal
     * mulai & selesai, tapi tata letak PDF-nya tidak ikut berubah. Melebur
     * keduanya berarti mengubah dokumen hotel tanpa ada yang memintanya.
     */
    public function chargeLinesDateFirstInPdf(): bool;
}
