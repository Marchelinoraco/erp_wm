<?php

namespace App\Services\SalesLine;

use App\Models\Invoice;

/**
 * Transport (sewa mobil/kapal): per hari. Disimpan sebagai `rental` di kolom
 * tours.type — lihat SalesLineRuleRegistry.
 */
final class TransportRule extends BaseSalesLineRule
{
    public function unitPriceLabel(): string
    {
        return 'Harga / hari';
    }

    public function defaultMultipliers(Invoice $invoice): array
    {
        return [new Multiplier('hari', 'Hari', $this->daysOf($invoice))];
    }

    /**
     * Rental menagih beberapa unit berbeda dengan harga masing-masing, jadi
     * totalnya dijumlah dari baris bernominal — bukan satu harga × kuantitas.
     * unit_price diabaikan untuk jenis ini.
     */
    public function totalComposition(): string
    {
        return 'line_items';
    }

    /**
     * Rental tidak menyusun paket di muka: modal dan jual per unit baru
     * diketahui saat invoice dirinci. tour_items-nya memang kosong, jadi
     * Ringkasan Biaya membacanya dari Rincian Profit invoice.
     */
    public function costingSource(): string
    {
        return 'invoice_items';
    }

    /** Sewa kendaraan/kapal berjalan dari tanggal mulai sampai tanggal selesai. */
    public function chargeLinesUseDateRange(): bool
    {
        return true;
    }

    /**
     * Satu invoice rental kerap memuat beberapa unit dengan periode berbeda,
     * jadi yang pertama dicari customer adalah PERIODE-nya — bukan nama unit.
     * Rentang tanggal naik ke kolom kiri, nama unit turun ke kanan.
     */
    public function chargeLinesDateFirstInPdf(): bool
    {
        return true;
    }
}
