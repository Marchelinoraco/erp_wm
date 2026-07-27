# Satukan Nomor Invoice — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Sales, Keuangan, dan PDF invoice menampilkan **satu nomor yang identik** (`number`) untuk invoice yang sama — pensiunkan `finance_number` sebagai nomor terpisah, dan pastikan invoice lama yang belum berkode tipe penjualan ikut diberi kode tipe tanpa menggeser nomor invoice mana pun yang sudah stabil.

**Architecture:** Dua tahap terpisah risiko. Task 1 murni kode/tampilan (tidak menyentuh data invoice mana pun — aman lewat alur normal). Task 2 backfill data (menyentuh `number` invoice production yang sudah disetujui — mengikuti protokol keamanan data §5 spec, mirip pola Fase 2 `sales_line` sebelumnya). Task 3 gerbang regresi.

**Tech Stack:** Laravel 13, PHPUnit, SQLite in-memory untuk test.

**Spec:** [`docs/superpowers/specs/2026-07-27-satu-nomor-invoice-design.md`](../specs/2026-07-27-satu-nomor-invoice-design.md)

**Cakupan repo:** `erp_wm`, branch `feat/satukan-nomor-invoice` (sudah dibuat dari `dev`, sudah membawa spec via commit `1e67d63`).

---

## Perbedaan penting antar-task

Task 1 murni mengubah kode/tampilan yang sudah ada — tidak ada baris data invoice yang berubah, aman diverifikasi lewat `php artisan test` biasa. Task 2 **menulis ulang kolom `number`** pada invoice production yang sudah disetujui dan sudah dikirim ke customer lewat PDF — kesalahan di sini bisa membuat nomor resmi yang sudah dipakai customer berubah. Karena itu:

- Setiap test Task 2 membuktikan **satu klaim keamanan spesifik** dari §5 spec — bukan sekadar "backfill jalan".
- Task 2 selesai secara KODE di plan ini (teruji SQLite). Menjalankannya ke database production sungguhan adalah langkah operasional terpisah mengikuti protokol §5 spec (backup → staging dengan data nyata → verifikasi nol selisih → baru production) — **di luar cakupan plan ini**, sama seperti Fase 2 `sales_line` sebelumnya.

## Global Constraints

- Semua path relatif terhadap `/Users/marchelinoraco/Documents/2026/erp_wm`.
- Perintah test: `php artisan test` dari root repo.
- **`finance_number` kolom TETAP ADA di database** — JANGAN membuat migrasi drop kolom apa pun. Plan ini tidak mengubah skema sama sekali.
- **Invoice yang `number`-nya sudah berkode tipe (format `INV-<tahun>-<tipe>-NNNN`, 3 tanda hubung) TIDAK BOLEH berubah sama sekali** — dibuktikan test byte-identik sebelum/sesudah.
- **Backfill menyambung di UJUNG urutan (tahun, tipe) yang ada** — TIDAK menyisip berdasarkan tanggal dibuat, TIDAK menggeser nomor yang sudah ada. Ini keputusan sadar (§2.5 spec), bukan yang terlewat.
- **Backfill DILARANG menyentuh kolom selain `number`** — tidak boleh memanggil logic apa pun yang mengubah `total`, `total_idr`, `pax`, `unit_price`, `approved_at`, atau `finance_number`. Hanya `update(['number' => ...])`.
- **Backfill wajib idempoten** — pass kedua tidak menemukan invoice format lama lagi (semua sudah 3 tanda hubung), jadi tidak melakukan apa pun.
- Deteksi format lama vs baru pakai **jumlah tanda hubung** (`2` = lama `INV-tahun-NNNN`, `3` = baru `INV-tahun-tipe-NNNN`) — bukan tanggal, supaya presisi dan tidak ada baris yang lolos.
- Tahun pada nomor backfill diambil dari **nomor lama invoice itu sendiri** (regex), bukan `now()->year` — invoice historis mungkin dibuat di tahun sebelumnya.
- Kode tipe pakai `tour?->resolveTypeCode() ?? '11'` — fallback yang **sama persis** dengan `Invoice::nextNumber()`/`nextFinanceNumber()` yang sudah ada.
- Komentar kode dalam **Bahasa Indonesia**.
- Setiap commit memakai prefiks Conventional Commits dan diakhiri baris:
  `Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>`
- Tidak ada perubahan Vue yang butuh test frontend — repo ini tidak punya test-runner JS; perubahan tampilan diverifikasi lewat pembacaan diff, bukan test otomatis (konsisten dengan fitur-fitur Vue sebelumnya di sesi ini).

## Struktur Berkas

| Berkas | Tanggung jawab |
|---|---|
| `app/Http/Controllers/InvoiceController.php` | `approve()` berhenti mengisi `finance_number` |
| `resources/js/Pages/Finance/Index.vue` | Tampilkan `number` saja (2 tempat: outstanding & paid invoices) |
| `resources/js/Pages/Finance/Tour.vue` | Tampilkan `number` saja (4 tempat) |
| `resources/views/finance/profit_breakdown.blade.php` | Tampilkan `number` saja |
| `resources/js/Components/Tours/InvoicesPanel.vue` | Hapus baris kecil "Keuangan: {{ finance_number }}" |
| `tests/Feature/Invoice/ApprovalSideEffectsTest.php` | 2 test ditulis ulang (finance_number tidak lagi diisi) |
| `app/Console/Commands/BackfillInvoiceNumberTypeCode.php` | Backfill kode tipe utk `number` format lama, append-tidak-sisip, idempoten |
| `tests/Feature/Invoice/BackfillInvoiceNumberTypeCodeTest.php` | Korektheid backfill, non-sentuh yang sudah stabil, append di ujung, idempotensi, non-duplikat |

---

### Task 1: Pensiunkan `finance_number` — kode & tampilan

**Files:**
- Modify: `app/Http/Controllers/InvoiceController.php`
- Modify: `resources/js/Pages/Finance/Index.vue`
- Modify: `resources/js/Pages/Finance/Tour.vue`
- Modify: `resources/views/finance/profit_breakdown.blade.php`
- Modify: `resources/js/Components/Tours/InvoicesPanel.vue`
- Modify: `tests/Feature/Invoice/ApprovalSideEffectsTest.php`

**Interfaces:**
- Consumes: kolom `invoices.number` dan `invoices.finance_number` yang sudah ada (skema tidak berubah).
- Produces: `InvoiceController::approve()` tidak lagi menulis `finance_number`. Task 2 tidak bergantung pada perubahan ini, tapi harus dikerjakan lebih dulu supaya invoice yang di-backfill Task 2 tidak lagi punya `finance_number` yang aktif ditampilkan di mana pun.

- [ ] **Step 1: Tulis ulang test yang menguji perilaku LAMA (`finance_number` terbit saat disetujui)**

Ganti isi `tests/Feature/Invoice/ApprovalSideEffectsTest.php` — method `test_nomor_keuangan_terbit_saat_disetujui` (baris 24-36) dan `test_nomor_keuangan_tidak_membedakan_jenis_penjualan` (baris 38-54) diganti dengan:

```php
    public function test_finance_number_tidak_lagi_diisi_saat_disetujui(): void
    {
        $tour    = $this->makeTour('tour');
        $invoice = $this->makeInvoice($tour, 500_000);

        $this->assertNull($invoice->finance_number);

        $invoice = $this->approveInvoice($invoice);

        $this->assertNull($invoice->finance_number, 'finance_number pensiun — satu nomor (number) dipakai semua sisi, lihat spec 2026-07-27');
        $this->assertSame('sent', $invoice->status);
        $this->assertNotNull($invoice->approved_at);
    }

    public function test_finance_number_tetap_null_untuk_semua_jenis_penjualan(): void
    {
        foreach (['guide', 'hotel', 'rental'] as $type) {
            $tour    = $this->makeTour($type);
            $invoice = $this->approveInvoice($this->makeInvoice($tour, 500_000));

            $this->assertNull($invoice->finance_number, "finance_number harus tetap null utk jenis {$type}");
        }
    }
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=ApprovalSideEffectsTest`
Expected: FAIL — `InvoiceController::approve()` saat ini masih mengisi `finance_number`, jadi `assertNull($invoice->finance_number)` setelah `approveInvoice()` akan gagal (nilainya bukan null).

- [ ] **Step 3: Hapus pengisian `finance_number` di `InvoiceController::approve()`**

Baca dulu baris di sekitar 160-170 `app/Http/Controllers/InvoiceController.php` untuk konteks pasti, lalu hapus baris:

```php
'finance_number' => $invoice->finance_number ?? Invoice::nextFinanceNumber(),
```

dari array yang dikirim ke `$invoice->update(...)` (atau setara) di dalam method `approve()`. Baris lain di sekitarnya (status, approved_at, approved_by, dll.) **tidak diubah**.

- [ ] **Step 4: Jalankan test untuk memastikan lulus**

Run: `php artisan test --filter=ApprovalSideEffectsTest`
Expected: PASS

- [ ] **Step 5: Ubah tampilan Keuangan — `Finance/Index.vue`**

Ada **dua** kemunculan pola yang identik (baris ~163-165 tabel "Invoice Belum Lunas", baris ~225-227 tabel "Invoice Lunas"). Ganti keduanya dari:

```html
<td class="px-4 py-3 font-mono text-xs">
    <span class="font-medium text-gray-700">{{ inv.finance_number ?? inv.number }}</span>
    <span v-if="inv.finance_number" class="block text-[11px] text-gray-400">{{ inv.number }}</span>
</td>
```

menjadi:

```html
<td class="px-4 py-3 font-mono text-xs">
    <span class="font-medium text-gray-700">{{ inv.number }}</span>
</td>
```

- [ ] **Step 6: Ubah tampilan Keuangan — `Finance/Tour.vue`**

Empat kemunculan:

**(a) Baris ~378-379** (header kartu invoice) — ganti:
```html
<span class="font-mono text-sm font-semibold text-gray-800">{{ inv.finance_number ?? inv.number }}</span>
<span v-if="inv.finance_number" class="font-mono text-xs text-gray-400">{{ inv.number }}</span>
```
menjadi:
```html
<span class="font-mono text-sm font-semibold text-gray-800">{{ inv.number }}</span>
```

**(b) Baris ~528** (biaya tambahan) — ganti:
```html
📄 Ditambahkan ke invoice {{ cr.invoice.finance_number ?? cr.invoice.number }} sebagai biaya tambahan
```
menjadi:
```html
📄 Ditambahkan ke invoice {{ cr.invoice.number }} sebagai biaya tambahan
```

**(c) Baris ~578** (bill dari rincian profit) — ganti:
```html
· 📋 Dari Rincian Profit {{ bill.invoice_item.invoice.finance_number ?? bill.invoice_item.invoice.number }}
```
menjadi:
```html
· 📋 Dari Rincian Profit {{ bill.invoice_item.invoice.number }}
```

**(d) Baris ~885** (keterangan tagih customer) — ganti:
```html
Menambahkan baris "Additional" ke invoice {{ mainInvoice.finance_number ?? mainInvoice.number }} — total tagihan customer bertambah otomatis, invoice tidak diganti nomor barunya.
```
menjadi:
```html
Menambahkan baris "Additional" ke invoice {{ mainInvoice.number }} — total tagihan customer bertambah otomatis, invoice tidak diganti nomor barunya.
```

- [ ] **Step 7: Ubah PDF Rincian Profit — `resources/views/finance/profit_breakdown.blade.php`**

Baris ~24, ganti:
```blade
<td style="width:38%;"><b>{{ $invoice->finance_number ?? $invoice->number }}</b>@if($invoice->finance_number) <span class="sub">({{ $invoice->number }})</span>@endif</td>
```
menjadi:
```blade
<td style="width:38%;"><b>{{ $invoice->number }}</b></td>
```

- [ ] **Step 8: Hapus tampilan nomor keuangan di panel sales — `InvoicesPanel.vue`**

Baris ~698-701, hapus baris kedua (biarkan `inv.number` tetap tampil):
```html
<span class="font-mono text-sm font-semibold">{{ inv.number }}</span>
<span v-if="inv.finance_number" class="font-mono text-xs text-muted-foreground">
    Keuangan: {{ inv.finance_number }}
</span>
```
menjadi:
```html
<span class="font-mono text-sm font-semibold">{{ inv.number }}</span>
```

- [ ] **Step 9: Jalankan seluruh suite untuk memastikan tidak ada regresi**

Run: `php artisan test`
Expected: PASS semua (jumlah test sama seperti sebelumnya — Task 1 hanya MENGUBAH 2 test yang sudah ada, tidak menambah).

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/InvoiceController.php \
        resources/js/Pages/Finance/Index.vue \
        resources/js/Pages/Finance/Tour.vue \
        resources/views/finance/profit_breakdown.blade.php \
        resources/js/Components/Tours/InvoicesPanel.vue \
        tests/Feature/Invoice/ApprovalSideEffectsTest.php
git commit -m "refactor: pensiunkan finance_number, satu nomor (number) di semua sisi

InvoiceController::approve() berhenti mengisi finance_number. Halaman
Keuangan, panel sales, dan PDF Rincian Profit kini menampilkan number
saja — sudah identik dengan yang tampil di PDF invoice customer sejak
awal (invoice.blade.php tidak berubah, sudah pakai number).

Kolom finance_number TETAP ADA di database (tidak di-drop) — nilai
lama pada invoice yang sudah disetujui dibiarkan sebagai data historis,
sekadar tidak dibaca/ditampilkan lagi.

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 2: Backfill kode tipe penjualan untuk `number` format lama

**Files:**
- Create: `app/Console/Commands/BackfillInvoiceNumberTypeCode.php`
- Test: `tests/Feature/Invoice/BackfillInvoiceNumberTypeCodeTest.php`

**Interfaces:**
- Consumes: kolom `invoices.number`, `invoices.tour_id`, relasi `Invoice::tour()`, `Tour::resolveTypeCode()` — semua sudah ada.
- Produces: perintah artisan `invoices:backfill-number-type-code`, dipanggil manual saat protokol §5 spec dipenuhi (backup, staging, verifikasi). Tidak ada task lanjutan di plan ini yang memakainya.

Setiap test di sini membuktikan **satu klaim keamanan spesifik** dari §5 spec — terutama invarian "nomor yang sudah stabil tidak pernah bergeser".

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/Invoice/BackfillInvoiceNumberTypeCodeTest.php`:

```php
<?php

namespace Tests\Feature\Invoice;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * Setiap test membuktikan satu klaim keamanan spesifik dari §5 dokumen
 * desain (docs/superpowers/specs/2026-07-27-satu-nomor-invoice-design.md)
 * — bukan sekadar "backfill jalan tanpa error".
 */
class BackfillInvoiceNumberTypeCodeTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    public function test_mengisi_kode_tipe_untuk_number_format_lama(): void
    {
        $tour    = $this->makeTour('hotel');
        $invoice = $this->makeInvoice($tour, 500_000);
        $invoice->update(['number' => 'INV-2026-0009']); // simulasi format lama (2 tanda hubung)

        $this->artisan('invoices:backfill-number-type-code')->assertExitCode(0);

        $this->assertSame('INV-2026-16-0001', $invoice->fresh()->number, 'hotel = kode tipe 16 (Tour::TYPE_CODES)');
    }

    public function test_tidak_mengubah_number_yang_sudah_berkode_tipe(): void
    {
        $tour         = $this->makeTour('tour');
        $invoice      = $this->makeInvoice($tour, 500_000); // sudah dapat number berkode tipe dari Invoice::nextNumber()
        $nomorSebelum = $invoice->number;

        $this->artisan('invoices:backfill-number-type-code')->assertExitCode(0);

        $this->assertSame($nomorSebelum, $invoice->fresh()->number, 'Invoice yang sudah berkode tipe TIDAK BOLEH berubah sama sekali');
    }

    public function test_backfill_menyambung_di_ujung_bukan_menyisip_kronologis(): void
    {
        // Invoice BARU (dibuat sekarang, sudah otomatis berkode tipe) dapat 0001.
        $tourBaru    = $this->makeTour('tour');
        $invoiceBaru = $this->makeInvoice($tourBaru, 500_000);
        $this->assertSame('INV-' . now()->year . '-11-0001', $invoiceBaru->number);

        // Invoice LAMA (tipe sama), disimulasikan SEBENARNYA dibuat lebih dulu
        // secara kronologis (created_at 2 bulan lalu), tapi baru SEKARANG
        // di-backfill. TIDAK BOLEH mengambil 0001 lalu menggeser invoice baru
        // ke 0002 — harus dapat 0002 (menyambung di ujung urutan yang ADA).
        $tourLama    = $this->makeTour('tour');
        $invoiceLama = $this->makeInvoice($tourLama, 300_000);
        $invoiceLama->update([
            'number'     => 'INV-' . now()->year . '-0001',
            'created_at' => now()->subMonths(2),
        ]);

        $this->artisan('invoices:backfill-number-type-code')->assertExitCode(0);

        $this->assertSame('INV-' . now()->year . '-11-0001', $invoiceBaru->fresh()->number, 'Nomor yang sudah stabil TIDAK BOLEH bergeser');
        $this->assertSame('INV-' . now()->year . '-11-0002', $invoiceLama->fresh()->number, 'Invoice lama dapat nomor BERIKUTNYA, bukan menyisip di 0001');
    }

    public function test_tidak_mengubah_nominal_atau_kolom_lain(): void
    {
        $tour    = $this->makeTour('tour', ['pax' => 4]);
        $invoice = $this->approveInvoice($this->makeInvoice($tour, 13_139_000));
        $invoice->update(['number' => 'INV-2026-0009']);

        $totalSebelum      = $invoice->fresh()->total;
        $totalIdrSebelum   = $invoice->fresh()->total_idr;
        $approvedAtSebelum = (string) $invoice->fresh()->approved_at;

        $this->artisan('invoices:backfill-number-type-code')->assertExitCode(0);

        $sesudah = $invoice->fresh();
        $this->assertEquals($totalSebelum, $sesudah->total, 'total tidak boleh bergeser');
        $this->assertEquals($totalIdrSebelum, $sesudah->total_idr, 'total_idr tidak boleh bergeser');
        $this->assertSame($approvedAtSebelum, (string) $sesudah->approved_at, 'approved_at tidak boleh bergeser');
    }

    public function test_idempoten_pass_kedua_tidak_mengubah_apa_pun(): void
    {
        $tour    = $this->makeTour('guide');
        $invoice = $this->makeInvoice($tour, 500_000);
        $invoice->update(['number' => 'INV-2026-0009']);

        $this->artisan('invoices:backfill-number-type-code')->assertExitCode(0);
        $nomorSetelahPass1 = $invoice->fresh()->number;

        $this->artisan('invoices:backfill-number-type-code')->assertExitCode(0);

        $this->assertSame($nomorSetelahPass1, $invoice->fresh()->number, 'Pass kedua tidak boleh mengubah apa pun — semua sudah berkode tipe (3 tanda hubung)');
    }

    public function test_tidak_ada_duplikat_number_setelah_backfill(): void
    {
        foreach (['tour', 'tour', 'hotel', 'guide'] as $i => $type) {
            $invoice = $this->makeInvoice($this->makeTour($type), 100_000);
            $invoice->update(['number' => 'INV-2026-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT)]);
        }

        $this->artisan('invoices:backfill-number-type-code')->assertExitCode(0);

        $numbers = Invoice::pluck('number');
        $this->assertSame($numbers->count(), $numbers->unique()->count(), 'Tidak boleh ada number yang sama pada dua invoice berbeda');
    }

    public function test_tour_soft_deleted_jatuh_ke_kode_11(): void
    {
        $tour    = $this->makeTour('hotel');
        $invoice = $this->makeInvoice($tour, 500_000);
        $invoice->update(['number' => 'INV-2026-0009']);
        $tour->delete();

        $this->artisan('invoices:backfill-number-type-code')->assertExitCode(0);

        $this->assertSame('INV-2026-11-0001', $invoice->fresh()->number, 'Tour soft-deleted -> $invoice->tour null -> fallback kode 11, sama seperti Invoice::nextNumber()');
    }

    public function test_laporan_jumlah_baris_diproses(): void
    {
        $inv1 = $this->makeInvoice($this->makeTour('tour'), 100_000);
        $inv1->update(['number' => 'INV-2026-0001']);
        $inv2 = $this->makeInvoice($this->makeTour('hotel'), 100_000);
        $inv2->update(['number' => 'INV-2026-0002']);

        $this->artisan('invoices:backfill-number-type-code')
            ->expectsOutputToContain('2 invoice')
            ->assertExitCode(0);
    }
}
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=BackfillInvoiceNumberTypeCodeTest`
Expected: FAIL — perintah `invoices:backfill-number-type-code` belum terdaftar (`Command "invoices:backfill-number-type-code" is not defined`).

- [ ] **Step 3: Buat perintah backfill**

`app/Console/Commands/BackfillInvoiceNumberTypeCode.php`:

```php
<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use Illuminate\Console\Command;

/**
 * Backfill kode tipe penjualan pada invoice.number yang masih format lama
 * (INV-<tahun>-NNNN, invoice dibuat sebelum 16 Jul 2026) — lihat
 * docs/superpowers/specs/2026-07-27-satu-nomor-invoice-design.md §3.
 *
 * HANYA memproses number berformat LAMA (2 tanda hubung: INV-tahun-NNNN).
 * Invoice yang number-nya SUDAH berkode tipe (3 tanda hubung, format
 * INV-tahun-tipe-NNNN) tidak pernah tersentuh — deteksinya lewat jumlah
 * tanda hubung, bukan tanggal, supaya presisi dan tidak ada yang lolos.
 *
 * Nomor baru diberikan di UJUNG urutan (tahun, tipe) yang ADA SEKARANG —
 * TIDAK disisipkan berdasarkan kapan invoice sebenarnya dibuat. Ini
 * disengaja: menyisipkan kronologis akan menggeser nomor yang SUDAH
 * stabil dan mungkin sudah dikirim ke customer lewat PDF — dilarang
 * keras (§2.5 spec).
 *
 * Idempoten: pass kedua tidak menemukan invoice format lama lagi (semua
 * sudah 3 tanda hubung setelah pass pertama), jadi tidak melakukan apa pun.
 */
class BackfillInvoiceNumberTypeCode extends Command
{
    protected $signature = 'invoices:backfill-number-type-code';

    protected $description = 'Tambahkan kode tipe penjualan ke invoice.number format lama, tanpa mengubah nomor yang sudah berkode tipe';

    public function handle(): int
    {
        // Format lama = 2 tanda hubung (INV-tahun-NNNN). Format baru = 3
        // tanda hubung (INV-tahun-tipe-NNNN). withTrashed() supaya invoice
        // yang sudah soft-delete pun tercakup — konsisten dengan
        // Invoice::nextNumber()/nextFinanceNumber() yang juga withTrashed()
        // saat mencari nomor terakhir.
        $legacy = Invoice::withTrashed()
            ->whereRaw("(LENGTH(number) - LENGTH(REPLACE(number, '-', ''))) = 2")
            ->with('tour')
            ->orderBy('created_at')
            ->get();

        $count = 0;
        foreach ($legacy as $invoice) {
            $matches = [];
            // Tahun diambil dari number LAMA milik invoice itu sendiri, BUKAN
            // now()->year — invoice ini historis, mungkin dibuat di tahun lalu.
            if (! preg_match('/^INV-(\d{4})-\d+$/', $invoice->number, $matches)) {
                continue; // Format tak dikenal — dilewati, tidak dipaksakan.
            }
            $year     = $matches[1];
            $typeCode = $invoice->tour?->resolveTypeCode() ?? '11';

            $prefix = "INV-{$year}-{$typeCode}-";
            $latest = Invoice::withTrashed()
                ->where('number', 'like', $prefix . '%')
                ->orderByDesc('number')
                ->value('number');
            $next = $latest ? ((int) substr($latest, strlen($prefix))) + 1 : 1;

            $invoice->update(['number' => $prefix . str_pad($next, 4, '0', STR_PAD_LEFT)]);
            $count++;
        }

        $this->info("Selesai. {$count} invoice number di-backfill dengan kode tipe.");

        return self::SUCCESS;
    }
}
```

- [ ] **Step 4: Jalankan test untuk memastikan lulus**

Run: `php artisan test --filter=BackfillInvoiceNumberTypeCodeTest`
Expected: PASS, 8 test

- [ ] **Step 5: Jalankan seluruh suite**

Run: `php artisan test`
Expected: PASS semua (test Task 1 + 8 test baru Task 2)

- [ ] **Step 6: Commit**

```bash
git add app/Console/Commands/BackfillInvoiceNumberTypeCode.php tests/Feature/Invoice/BackfillInvoiceNumberTypeCodeTest.php
git commit -m "feat: perintah backfill invoices:backfill-number-type-code

Menambahkan kode tipe penjualan ke invoice.number format lama
(INV-tahun-NNNN, dibuat sebelum 16 Jul 2026), tanpa pernah mengubah
number invoice yang sudah berkode tipe. Nomor baru menyambung di
ujung urutan (tahun, tipe) yang ada, bukan menyisip kronologis —
mencegah nomor yang sudah stabil (mungkin sudah dikirim ke customer
lewat PDF) ikut bergeser.

Dibuktikan test: nol perubahan pada invoice yang sudah berkode tipe,
nol perubahan pada total/total_idr/approved_at, idempoten, tidak ada
duplikat number, fallback kode 11 utk tour ter-soft-delete (konsisten
dgn Invoice::nextNumber()).

Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>"
```

---

### Task 3: Gerbang regresi — buktikan invoice yang sudah stabil tidak tersentuh

**Files:**
- Tidak ada berkas baru. Task ini murni verifikasi.

**Interfaces:**
- Consumes: seluruh suite test yang ada + baru dari Task 1-2.
- Produces: tidak ada — task terakhir plan ini.

- [ ] **Step 1: Jalankan seluruh suite sebagai gerbang akhir**

Run: `php artisan test`
Expected: PASS semua, nol gagal.

- [ ] **Step 2: Buktikan tidak ada berkas di luar cakupan yang tersentuh**

Run: `git diff --stat dev...HEAD -- app/Models/Invoice.php database/migrations`
Expected: keluaran **kosong** — plan ini tidak mengubah `Invoice.php` (model) maupun membuat migrasi apa pun. Bila ada baris, periksa sebelum lanjut — berarti ada perubahan skema/model yang tidak direncanakan.

- [ ] **Step 3: Verifikasi manual — baca (bukan jalankan) kelima file Vue/Blade yang diubah Task 1**

Konfirmasi dengan `git diff` bahwa **tidak ada** kemunculan `finance_number` tersisa di kelima file itu:

```bash
grep -rn "finance_number" resources/js/Pages/Finance/Index.vue resources/js/Pages/Finance/Tour.vue resources/views/finance/profit_breakdown.blade.php resources/js/Components/Tours/InvoicesPanel.vue
```

Expected: keluaran kosong (nol kecocokan).

- [ ] **Step 4: Commit penanda selesai (jika ada perbaikan tertunda)**

Bila Step 1-3 semua lulus tanpa temuan, tidak ada yang perlu di-commit — Task 1 dan 2 sudah final. Jika ada temuan yang perlu diperbaiki, perbaiki lalu ulangi Step 1-3 sebelum commit.

---

## Verifikasi Akhir (kode) — sebelum lanjut ke langkah operasional

```bash
php artisan test
grep -rn "finance_number" resources/js/Pages/Finance/Index.vue resources/js/Pages/Finance/Tour.vue resources/views/finance/profit_breakdown.blade.php resources/js/Components/Tours/InvoicesPanel.vue
git diff --stat dev...HEAD -- app/Models/Invoice.php database/migrations
```

Yang harus terbukti oleh test, bukan oleh keyakinan:

| Properti | Dibuktikan oleh |
|---|---|
| `finance_number` tidak lagi diisi saat disetujui, utk semua jenis penjualan | `ApprovalSideEffectsTest` (Task 1) |
| Invoice berformat lama dapat kode tipe yang benar sesuai tour | `test_mengisi_kode_tipe_untuk_number_format_lama` |
| **Invoice yang sudah berkode tipe TIDAK bergeser sama sekali** | `test_tidak_mengubah_number_yang_sudah_berkode_tipe` |
| **Backfill menyambung di ujung, tidak menyisip kronologis** (invarian paling kritis) | `test_backfill_menyambung_di_ujung_bukan_menyisip_kronologis` |
| Nol perubahan nominal/approved_at | `test_tidak_mengubah_nominal_atau_kolom_lain` |
| Backfill idempoten | `test_idempoten_pass_kedua_tidak_mengubah_apa_pun` |
| Tidak ada duplikat `number` setelah backfill | `test_tidak_ada_duplikat_number_setelah_backfill` |
| Fallback kode 11 utk tour ter-soft-delete, konsisten `Invoice::nextNumber()` | `test_tour_soft_deleted_jatuh_ke_kode_11` |
| Tidak ada sisa tampilan `finance_number` di kode | Task 3 Step 3 |

**Definisi selesai untuk plan ini:** seluruh test hijau, nol kemunculan `finance_number` di file tampilan, `git diff` terhadap `Invoice.php`/migrations kosong.

**Definisi selesai untuk fitur ini secara keseluruhan (di luar plan ini, langkah operasional §5 spec):**

- [ ] Backup manual database production terbaru, diverifikasi bisa di-restore
- [ ] `invoices:backfill-number-type-code` dijalankan di **salinan data production** (staging `erp_wm_dev`, sudah tersedia dari verifikasi Fase 2 `sales_line` sebelumnya)
- [ ] Query verifikasi sebelum/sesudah di staging: nol perubahan pada invoice yang sudah berkode tipe, nol perubahan nominal, nol duplikat `number`
- [ ] Baru setelah staging bersih: `invoices:backfill-number-type-code` dijalankan di production sungguhan

Langkah operasional ini **tidak** diotomatisasi lewat task di plan ini — dilakukan manual oleh pengguna mengikuti §5 spec, dengan kode dari plan ini sebagai alat yang sudah teruji.

## Cakupan Spec

| Bagian spec | Task |
|---|---|
| §2.1-2.2 — satu nomor (`number`), bukan `finance_number` | Task 1 |
| §2.3 — konsekuensi diterima (tidak 100% gapless) | Tidak perlu kode — sudah melekat pada desain `number` yang ada |
| §2.4 — kode tipe wajib di semua invoice | Task 2 |
| §2.5 — backfill tidak boleh menggeser nomor stabil | Task 2 (`test_backfill_menyambung_di_ujung...`) |
| §3 — arsitektur akhir, perubahan kode per file | Task 1 (tampilan), Task 2 (backfill) |
| §4 — non-tujuan (jangan drop kolom, jangan sentuh approval/pembayaran) | Global Constraints |
| §5 — protokol keamanan data | Di luar cakupan plan ini — langkah operasional terpisah, lihat Verifikasi Akhir |

**Bagian spec yang sengaja belum dikerjakan di plan ini:** §5 (backup, staging, verifikasi nol selisih, rollback) adalah langkah operasional — tidak ada kode untuk itu, hanya checklist yang dijalankan manusia setelah plan ini selesai dan sebelum backfill menyentuh production sungguhan.
