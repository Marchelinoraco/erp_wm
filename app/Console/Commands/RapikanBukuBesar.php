<?php

namespace App\Console\Commands;

use App\Models\BillPayment;
use App\Models\FinTransaction;
use App\Models\InvoicePayment;
use App\Support\LedgerSync;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Membereskan baris `fin_transactions` yang terlanjur salah di produksi
 * sebelum perbaikan kode 10 Sep 2026 dipasang. Lihat rekonsiliasi Laba Rugi
 * (akrual) vs Buku Besar (kas) pada tanggal yang sama.
 *
 * Invarian yang ditegakkan: setiap baris buku besar bersumber AR/AP harus
 * punya pembayaran yang masih hidup. Baris `source = 'manual'` TIDAK PERNAH
 * disentuh — itu catatan yang diketik akuntan, bukan hasil sinkronisasi.
 *
 * Idempoten: menjalankannya dua kali tidak mengubah apa pun pada jalan kedua.
 */
class RapikanBukuBesar extends Command
{
    protected $signature = 'keuangan:rapikan-buku-besar {--dry-run : Tampilkan hasil tanpa menyimpan perubahan}';

    protected $description = 'Bersihkan baris buku besar yatim dari pembayaran AR/AP yang sudah dihapus';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $hantu         = $this->hantu();
        $billHilang    = $this->pembayaranTanpaBukuBesar(BillPayment::class, 'bill');
        $invoiceHilang = $this->pembayaranTanpaBukuBesar(InvoicePayment::class, 'invoice');

        $this->tampilkanHantu($hantu);
        $this->tampilkanHilang($billHilang, $invoiceHilang);

        if ($dryRun) {
            $this->newLine();
            $this->warn('[DRY RUN] Tidak ada satu pun perubahan disimpan.');

            return self::SUCCESS;
        }

        foreach ($hantu as $baris) {
            $baris->delete();
        }

        // Dibuat ulang lewat LedgerSync, BUKAN insert manual — supaya baris
        // hasil perbaikan identik dengan yang dibuat aplikasi sehari-hari
        // (pemilihan akun kas, amount_idr untuk invoice multi-currency, format
        // keterangan). Kalau fondasinya bermasalah, LedgerSync melempar
        // exception dan perintah ini berhenti terang-terangan.
        foreach ($billHilang as $payment) {
            LedgerSync::syncBillPayment($payment);
        }
        foreach ($invoiceHilang as $payment) {
            LedgerSync::syncInvoicePayment($payment);
        }

        $this->info("Hantu dihapus: {$hantu->count()}");
        $this->info('Baris hilang dibuat ulang: ' . ($billHilang->count() + $invoiceHilang->count()));

        return self::SUCCESS;
    }

    /**
     * Pembayaran yang masih hidup tetapi tidak punya baris buku besar.
     *
     * Sengaja TIDAK ikut memeriksa apakah invoice/bill induknya ter-soft-delete.
     * Invariannya ada di tingkat pembayaran: selama pembayarannya hidup, uang
     * itu sungguhan berpindah dan wajib tercatat. Menambahkan syarat induk
     * berisiko menghapus baris yang sah.
     */
    private function pembayaranTanpaBukuBesar(string $model, string $source): Collection
    {
        return $model::whereNotIn(
            'id',
            FinTransaction::where('source', $source)->whereNotNull('source_id')->select('source_id')
        )->get();
    }

    private function rp(float $n): string
    {
        return 'Rp ' . number_format($n, 0, ',', '.');
    }

    private function tampilkanHantu(Collection $hantu): void
    {
        $this->newLine();
        $this->line('<comment>HANTU — baris buku besar tanpa pembayaran hidup (akan DIHAPUS)</comment>');

        if ($hantu->isEmpty()) {
            $this->line('  (tidak ada)');

            return;
        }

        $this->table(
            ['id', 'tanggal', 'sumber', 'source_id', 'arah', 'jumlah', 'keterangan'],
            $hantu->map(fn ($t) => [
                $t->id,
                $t->date?->format('Y-m-d'),
                $t->source,
                $t->source_id ?? '—',
                $t->direction,
                $this->rp((float) $t->amount),
                \Illuminate\Support\Str::limit((string) $t->description, 40),
            ])->all()
        );

        $this->line(sprintf('  Total: %d baris · %s', $hantu->count(), $this->rp((float) $hantu->sum('amount'))));
    }

    private function tampilkanHilang(Collection $bill, Collection $invoice): void
    {
        $this->newLine();
        $this->line('<comment>HILANG — pembayaran hidup tanpa baris buku besar (akan DIBUAT ULANG)</comment>');

        if ($bill->isEmpty() && $invoice->isEmpty()) {
            $this->line('  (tidak ada)');

            return;
        }

        $baris = collect();
        foreach ($bill as $p) {
            $baris->push(['bill', $p->id, $p->date?->format('Y-m-d'), $this->rp((float) $p->amount)]);
        }
        foreach ($invoice as $p) {
            $nilai = (float) ($p->amount_idr ?? 0) > 0 ? (float) $p->amount_idr : (float) $p->amount;
            $baris->push(['invoice', $p->id, $p->date?->format('Y-m-d'), $this->rp($nilai)]);
        }

        $this->table(['sumber', 'payment_id', 'tanggal', 'jumlah'], $baris->all());
        $this->line(sprintf(
            '  Total: %d baris · %s',
            $bill->count() + $invoice->count(),
            $this->rp((float) $bill->sum('amount') + (float) $invoice->sum('amount'))
        ));
    }

    /**
     * Baris buku besar AR/AP yang pembayarannya sudah tidak ada atau sudah
     * ter-soft-delete. `source_id` kosong ikut terjaring — baris seperti itu
     * mustahil dirujukkan kembali ke pembayaran manapun.
     */
    private function hantu(): Collection
    {
        $bill = FinTransaction::where('source', 'bill')
            ->where(fn ($q) => $q->whereNull('source_id')
                ->orWhereNotIn('source_id', BillPayment::query()->select('id')))
            ->get();

        $invoice = FinTransaction::where('source', 'invoice')
            ->where(fn ($q) => $q->whereNull('source_id')
                ->orWhereNotIn('source_id', InvoicePayment::query()->select('id')))
            ->get();

        return $bill->concat($invoice);
    }
}
