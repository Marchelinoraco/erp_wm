# Sentralisasi Aturan Invoice per Jenis + Mode Tagihan Baris untuk Rental — Rencana Implementasi

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengembalikan satuan pengali invoice ke "pax", memindahkan aturan uang per jenis penjualan ke `SalesLineRuleRegistry` sebagai satu-satunya sumber kebenaran, dan memberi rental cara menyusun total dari baris-baris bernominal alih-alih `unit_price × pax`.

**Architecture:** Kontrak `App\Contracts\SalesLineInvoiceRule` bertambah dua method (`profitFromRevenue()`, `totalComposition()`) dengan nilai bawaan di `BaseSalesLineRule`; hanya `TourRule` dan `TransportRule` yang menimpanya. Aturan disalurkan ke Vue lewat prop Inertia `salesLine` pada halaman `Tours/Edit`, sehingga frontend berhenti punya peta jenis sendiri. `Invoice::syncProformaTotal()` memilih basis total berdasarkan `totalComposition()`.

**Tech Stack:** Laravel 12 + Inertia + Vue 3 (`<script setup>`), PHPUnit 12.5, mPDF via blade `resources/views/invoice.blade.php`, MySQL.

**Spec:** [`docs/superpowers/specs/2026-07-29-sentralisasi-aturan-invoice-rental-design.md`](../specs/2026-07-29-sentralisasi-aturan-invoice-rental-design.md)

---

## Global Constraints

- **Satuan pengali adalah `pax` untuk semua jenis** (D1). Tidak ada kata "hari"/"dokumen"/"tiket"/"malam"/"peserta" sebagai satuan pengali di panel maupun PDF customer.
- **`SalesLineRule::unitPriceLabel()` tetap ada dan tidak diubah** (Fase 1, §3). Ia kembali dorman menunggu keputusan bisnis D2. Jangan hapus.
- **Frontend tidak boleh punya peta jenis penjualan sendiri** (D3). Tidak ada `{ guide: ..., rental: ... }` atau `props.tour.type === 'tour'` di berkas `.vue` setelah Fase 2.
- **Prop `salesLine` hanya memuat properti yang benar-benar dikonsumsi** (D5). `unitPriceLabel` TIDAK dikirim.
- **Komposisi total ditentukan per jenis lewat rule, bukan dipilih sales** (D7).
- **Invoice yang sudah disetujui tidak pernah dihitung ulang** (D8). Jangan tambahkan pemanggil baru `syncProformaTotal()` tanpa `ensureNotApproved()` di depannya.
- **Tidak ada migrasi data otomatis** untuk invoice rental lama (D9). Jangan tulis migration/command yang menyentuh `invoices.unit_price` atau `invoices.total`.
- Nama test dan komentar kode ditulis **dalam bahasa Indonesia**, mengikuti seluruh berkas di `tests/Unit/SalesLine/` dan `tests/Feature/SalesLine/`.
- Perintah test: `php artisan test`. Satu berkas: `php artisan test --filter=NamaTest`.

---

## Koreksi terhadap spec (diverifikasi 30 Jul 2026, sebelum kode ditulis)

Tiga premis spec tidak cocok dengan keadaan nyata. Rencana ini memakai keadaan nyata.

| Spec | Keadaan nyata (terverifikasi) | Dampak ke rencana |
|---|---|---|
| §1.1: "`bbde75f` sudah berada di `main`. Konsekuensinya nyata, bukan hipotetis." | `git merge-base --is-ancestor bbde75f main` → **TIDAK**. `1be88ff` dan `bbde75f` ada di `dev` saja; `dev` 13 commit di depan `main`. | Label salah **belum pernah sampai ke customer di production**. Fase 1 tetap wajib, tapi sifatnya *mencegah*, bukan *memperbaiki kebocoran*. Fase 1 harus mendarat sebelum promosi `dev` → `main` berikutnya, sebab promosi itu akan merilis label salahnya. |
| §5/R1: "Sudah dipastikan ada minimal satu: `INV-2026-13-0002`, proforma, `unit_price = 2.050.000`" | Query §5a di DB lokal (`welcome_manado`) → **0 baris**. Nomor `INV-2026-13-0002` tidak ada. DB lokal hanya berisi 8 tour (tipe `tour` & `guide`) dan 7 invoice — tidak ada satu pun tour rental. | Angka R1 berasal dari database lain (production). **Query §5a wajib dijalankan di production**, bukan lokal — lihat Prasyarat Rilis Fase 3. Hasil lokal tidak membuktikan apa pun tentang production. Test Fase 3 memakai fixture, bukan data nyata. |
| §3 Fase 3 tabel: "`InvoicesPanel.vue` — tambah input tanggal pada baris bernominal" | Benar, dan lebih spesifik: baris bernominal disimpan di form state sebagai `additional_lines` (terpisah dari `description_lines`), dibedakan lewat **kehadiran key `amount`** saat dibaca dari server (baris 81-86). | Perubahan menyentuh `additional_lines`, bukan `description_lines`. Penggabungan keduanya terjadi di `saveProforma()` baris 303-310. |

**Temuan tambahan yang tidak ada di spec:** `tests/Feature/SalesLine/SyncProformaThroughRegistryTest.php:39` memuat kelas anonim yang `implements SalesLineInvoiceRule`. Menambah method ke interface **akan memecah berkas test itu dengan fatal error**, bukan kegagalan assertion. Task 2 menanganinya dengan mengekstrak test double bersama `Tests\Support\FakeSalesLineRule` — bukan menambal kelas anonimnya.

**R5 terjawab konkret:** satu-satunya konsumen `InvoicesPanel.vue` dan `CostingPanel.vue` adalah `resources/js/Pages/Tours/Edit.vue` (baris 105 dan 115). `Pages/Finance/Index.vue:270` hanya menyebutnya di komentar. Nilai bawaan prop tetap diberikan sesuai R5, tapi tidak ada halaman kedua yang perlu disunting.

## Celah pengujian yang harus diakui, bukan disembunyikan

`package.json` **tidak punya test runner JS** — tidak ada vitest, jest, atau `@vue/test-utils` (diverifikasi 30 Jul 2026). Akibatnya dua butir §6 spec tidak bisa diotomatiskan dengan perkakas yang ada sekarang:

| Butir §6 | Bisa diotomatiskan? | Pengganti di rencana ini |
|---|---|---|
| "Panel menampilkan `Harga / pax` untuk tiap jenis" (Fase 1) | **Tidak** | Task 1 Step 8: `grep` memastikan tidak ada sisa `billingUnit`/`BILLING_UNIT_LABELS`, plus `npm run build` yang menangkap referensi template yang menggantung |
| "Invoice rental `unit_price > 0` tanpa baris bernominal memunculkan banner §5c; banner hilang begitu ada baris" (Fase 3) | **Tidak** | Task 8 Step 7: pemeriksaan manual dengan mata di aplikasi berjalan, dengan tour rental yang dibuat dulu (DB lokal tidak punya) |

Sisi backend dari kedua butir itu tetap diuji: `total` Rp0 untuk rental tanpa baris bernominal dikunci `LineItemsCompositionTest` (Task 6), dan hilangnya `billingUnit` dari payload view dikunci `CustomerPdfUnitLabelTest` (Task 1). Yang tidak terjaga otomatis adalah **tampilan Vue-nya**.

Menambahkan vitest berada **di luar lingkup rencana ini** — itu keputusan perkakas yang menyentuh seluruh repo, bukan bagian dari spec yang sudah disetujui. Bila kamu ingin celah ini ditutup, itu pekerjaan tersendiri dengan spec sendiri. Sampai itu terjadi, dua butir di atas bergantung pada mata manusia, dan rencana ini menyebutnya apa adanya alih-alih mengaku "§6 tercakup penuh".

---

## Struktur Berkas

**Dibuat:**

| Berkas | Tanggung jawab |
|---|---|
| `tests/Support/FakeSalesLineRule.php` | Test double `SalesLineInvoiceRule` yang dipakai bersama, menggantikan kelas anonim. Satu tempat untuk diperbarui setiap kontrak bertambah method. |
| `tests/Support/FakeSalesLineRuleRegistry.php` | Pengganti `SalesLineRuleRegistry` di container (kelas aslinya `final`, tidak bisa di-extend). |
| `tests/Unit/SalesLine/ProfitFromRevenueTest.php` | Fase 2 — aturan profit per jenis di tingkat rule. |
| `tests/Feature/SalesLine/SalesLinePropPayloadTest.php` | Fase 2 — payload Inertia `Tours/Edit` memuat `salesLine` yang benar. |
| `tests/Feature/Invoice/ProfitFormulaCharacterizationTest.php` | Fase 2 — angka profit/margin identik sebelum-sesudah refactor (R3). |
| `tests/Unit/SalesLine/TotalCompositionTest.php` | Fase 3 — komposisi total per jenis di tingkat rule. |
| `tests/Feature/Invoice/LineItemsCompositionTest.php` | Fase 3 — total rental dari baris bernominal, non-rental tetap lama, plus regresi R1/R2/R6. |
| `tests/Feature/Invoice/CustomerPdfUnitLabelTest.php` | Fase 1 — blade customer mencetak `× N pax` untuk semua jenis. |

**Diubah:**

| Berkas | Perubahan |
|---|---|
| `app/Contracts/SalesLineInvoiceRule.php` | +`profitFromRevenue(): bool` (Task 2), +`totalComposition(): string` (Task 5) |
| `app/Services/SalesLine/BaseSalesLineRule.php` | Nilai bawaan kedua method |
| `app/Services/SalesLine/TourRule.php` | `profitFromRevenue()` → `true` |
| `app/Services/SalesLine/TransportRule.php` | `totalComposition()` → `'line_items'` |
| `app/Models/Invoice.php` | `syncProformaTotal()` memilih basis total |
| `app/Http/Controllers/TourController.php` | `edit()` menambah prop `salesLine` |
| `app/Http/Controllers/InvoiceController.php` | Hapus `billingUnitNoun()` + key `billingUnit`; `profitPdf()` pakai rule |
| `resources/views/invoice.blade.php` | `pax` dipatok; baris bernominal cetak tanggal; baris `Price` disembunyikan saat `unit_price = 0` |
| `resources/js/Pages/Tours/Edit.vue` | Terima & teruskan prop `salesLine` |
| `resources/js/Components/Tours/InvoicesPanel.vue` | Hapus peta satuan; baca aturan dari prop; tanggal pada baris bernominal; tata letak `line_items`; banner peralihan |
| `resources/js/Components/Tours/CostingPanel.vue` | `fromInvoice` baca prop |
| `tests/Feature/SalesLine/SyncProformaThroughRegistryTest.php` | Pakai `FakeSalesLineRule` |

---

## Prasyarat

- [ ] **Branch sudah benar.** Branch `feat/sentralisasi-aturan-invoice-rental` sudah ada dan sudah aktif per 30 Jul 2026. Pastikan:

```bash
cd /Users/marchelinoraco/Documents/2026/erp_wm
git rev-parse --abbrev-ref HEAD   # harus: feat/sentralisasi-aturan-invoice-rental
git status --short                # harus bersih sebelum mulai
```

Bila bukan di branch itu: `git checkout feat/sentralisasi-aturan-invoice-rental`. **Jangan** menulis kode di `dev` atau `main`.

- [ ] **Test hijau sebelum mulai.** `php artisan test` — catat jumlah lulus sebagai patokan. Bila sudah ada yang merah sebelum kita menyentuh apa pun, laporkan dan berhenti; jangan campur kegagalan lama dengan yang baru.

---

# FASE 1 — Kembalikan satuan pengali ke "pax"

Membatalkan `1be88ff` dan `bbde75f`. Perilaku terlihat **berubah** (kembali ke keadaan sebelum 29 Jul 2026). Bisa dirilis sendiri.

### Task 1: Satuan pengali kembali "pax" di panel dan PDF

**Files:**
- Modify: `resources/js/Components/Tours/InvoicesPanel.vue:24-33` (hapus), `:854`, `:859`, `:899`
- Modify: `resources/views/invoice.blade.php:240`
- Modify: `app/Http/Controllers/InvoiceController.php:329`, `:339-351`
- Test: `tests/Feature/Invoice/CustomerPdfUnitLabelTest.php` (buat)

**Interfaces:**
- Consumes: `Tests\Support\CreatesSalesFixtures` (sudah ada) — `makeTour(string $type, array $overrides = []): Tour`, `makeInvoice(Tour $tour, float $unitPrice, array $overrides = []): Invoice`, konstanta `self::SALES_TYPES` = `['tour','hotel','guide','rental','mice','document','ticketing']`
- Produces: tidak ada API baru. Setelah task ini, `view('invoice', ...)` **tidak lagi menerima** key `billingUnit`.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/Invoice/CustomerPdfUnitLabelTest.php`:

```php
<?php

namespace Tests\Feature\Invoice;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * D1: satuan pengali di PDF customer adalah "pax" untuk SEMUA jenis — bukan
 * karena "pax" benar untuk semua, tapi karena angka yang dikalikan memang
 * jumlah pax (tour.pax) sampai Fase 3 dokumen lama selesai. Test ini mengunci
 * pembatalan 1be88ff/bbde75f agar satuan per jenis tidak diam-diam kembali.
 *
 * Caranya penting: setiap test SENGAJA mengirim `billingUnit` bernilai salah.
 * Selama blade masih memakai `$billingUnit ?? 'pax'`, test gagal; setelah
 * blade mematok 'pax', nilai salah itu diabaikan dan test lulus. Tanpa nilai
 * salah yang disengaja, fallback `?? 'pax'` membuat test lulus sejak awal dan
 * tidak membuktikan apa pun.
 */
class CustomerPdfUnitLabelTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    /**
     * Data view persis seperti yang dikirim InvoiceController::build().
     *
     * @param array<int, array<string, mixed>> $lines
     * @param array<string, mixed>             $extra  disisipkan/menimpa data view
     */
    private function renderInvoice(
        \App\Models\Invoice $invoice,
        float $unitPrice,
        int $pax,
        array $lines = [],
        array $extra = [],
    ): string {
        $segar = $invoice->fresh();

        return view('invoice', array_replace([
            'invoice'      => $segar,
            'company'      => config('quotation.company'),
            'bank'         => [],
            'paymentTerms' => '',
            'logo'         => '',
            'lines'        => $lines,
            'unitPrice'    => $unitPrice,
            'pax'          => $pax,
            'paid'         => 0.0,
            'outstanding'  => (float) $segar->total,
        ], $extra))->render();
    }

    public function test_pdf_customer_mencetak_pax_untuk_setiap_jenis(): void
    {
        foreach (self::SALES_TYPES as $type) {
            $invoice = $this->makeInvoice($this->makeTour($type, ['pax' => 7]), 100_000);

            // 'hari' sengaja salah: blade harus mengabaikannya sepenuhnya.
            $html = $this->renderInvoice($invoice, unitPrice: 100_000.0, pax: 7, extra: [
                'billingUnit' => 'hari',
            ]);

            $this->assertStringContainsString('&times; 7 pax', $html, "Jenis {$type}");
            $this->assertStringNotContainsString('&times; 7 hari', $html, "Jenis {$type}");
        }
    }

    public function test_tidak_ada_satuan_per_jenis_yang_tersisa_di_pdf(): void
    {
        // Keempat kata yang bbde75f perkenalkan. Kemunculannya kembali berarti
        // asumsi D2 yang belum diputuskan naik lagi jadi pernyataan ke customer.
        $invoice = $this->makeInvoice($this->makeTour('rental', ['pax' => 7]), 100_000);

        foreach (['hari', 'dokumen', 'tiket', 'malam'] as $satuan) {
            $html = $this->renderInvoice($invoice, unitPrice: 100_000.0, pax: 7, extra: [
                'billingUnit' => $satuan,
            ]);

            $this->assertStringNotContainsString('&times; 7 ' . $satuan, $html);
            $this->assertStringContainsString('&times; 7 pax', $html);
        }
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan GAGAL**

Run: `php artisan test --filter=CustomerPdfUnitLabelTest`
Expected: **FAIL, kedua test.** `test_pdf_customer_mencetak_pax_untuk_setiap_jenis` gagal pada jenis pertama karena blade masih membaca `$billingUnit` dan mencetak `&times; 7 hari`. Kegagalan ini yang membuktikan test-nya berguna — bila salah satu justru LULUS di sini, berarti `billingUnit` tidak sampai ke blade dan test perlu diperiksa dulu sebelum lanjut, jangan diteruskan.

- [ ] **Step 3: Hapus peta satuan di frontend**

Di `resources/js/Components/Tours/InvoicesPanel.vue`, hapus seluruh blok baris 24-33 (komentar + konstanta + computed):

```js
// HAPUS dari sini
// "Harga / pax" cocok untuk tour paket, tapi guide/rental/document/ticketing
// sebenarnya ditagih per hari/dokumen/tiket, bukan per kepala — label
// disesuaikan supaya jujur terhadap apa yang dikalikan. Rumus (unit_price ×
// tourPax) tidak berubah — lihat docs/logika-pembuatan-invoice/10-temuan.md §10.4.
// Kata-kata di sini mengikuti label resmi SalesLineRuleRegistry::unitPriceLabel()
// (app/Services/SalesLine/*Rule.php) — rental/Transport resminya "hari", bukan "unit".
const BILLING_UNIT_LABELS = {
    guide: 'hari', rental: 'hari', document: 'dokumen', ticketing: 'tiket',
}
const billingUnit = computed(() => BILLING_UNIT_LABELS[props.tour.type] ?? 'pax')
// HAPUS sampai sini
```

Ganti tiga pemakaian `billingUnit` di template dengan kata `pax` literal:

Baris 854:
```html
<label class="text-xs font-medium text-muted-foreground">Harga / pax ({{ proformaForms[inv.id].currency }})</label>
```

Baris 859:
```html
× <span class="font-medium">{{ tourPax || 1 }} pax</span>
```

Baris 899:
```html
× {{ tourPax || 1 }} pax
```

- [ ] **Step 4: Patok `pax` di blade**

Di `resources/views/invoice.blade.php` baris 240:

```blade
{{ $fmt($unitPrice) }}@if($pax > 0) &times; {{ $pax }} pax @endif
```

- [ ] **Step 5: Hapus `billingUnit` dari controller**

Di `app/Http/Controllers/InvoiceController.php`, hapus baris 329 dari array data view:

```php
// HAPUS baris ini
'billingUnit'  => $this->billingUnitNoun($invoice->tour?->type),
```

Lalu hapus seluruh method beserta docblock-nya (baris 339-351):

```php
// HAPUS dari /** sampai penutup } method billingUnitNoun()
```

Jangan hapus `use App\Services\SalesLine\SalesLineRuleRegistry;` di baris 9 — Task 4 dan Task 6 masih memakainya. Bila PHPStan/IDE mengeluh "unused import" pada tahap ini, biarkan; Task 4 memakainya lagi.

- [ ] **Step 6: Jalankan test, pastikan LULUS**

Run: `php artisan test --filter=CustomerPdfUnitLabelTest`
Expected: PASS, 2 test. Nilai `billingUnit` yang sengaja salah kini diabaikan blade.

- [ ] **Step 7: Pastikan tidak ada sisa `billingUnit` di mana pun**

Run:
```bash
grep -rn "billingUnit\|BILLING_UNIT_LABELS" app resources tests
```
Expected: tidak ada keluaran sama sekali.

- [ ] **Step 8: Test penuh + build frontend**

Run: `php artisan test`
Expected: PASS semua, jumlah ≥ patokan Prasyarat.

Run: `npm run build`
Expected: build sukses tanpa galat. (`billingUnit` yang terlewat di template akan muncul sebagai galat kompilasi Vue di sini.)

- [ ] **Step 9: Commit**

```bash
git add resources/js/Components/Tours/InvoicesPanel.vue resources/views/invoice.blade.php app/Http/Controllers/InvoiceController.php tests/Feature/Invoice/CustomerPdfUnitLabelTest.php
git commit -m "revert: satuan pengali invoice kembali ke pax untuk semua jenis

Membatalkan 1be88ff dan bbde75f. Satuan per jenis (hari/dokumen/tiket)
adalah asumsi rancangan Fase 1 yang belum pernah divalidasi ke pemilik
produk; menampilkannya menaikkannya jadi pernyataan bisnis di dokumen
keuangan ke customer. Pemilik produk menegaskan satuan untuk rental,
hotel, ticketing, dan jasa guide belum diputuskan.

Memperparah: angka yang dikalikan selalu tour.pax, jadi jasa guide dengan
rombongan 20 orang berbunyi 'x 20 hari' - 20 itu jumlah orang.

SalesLineRule::unitPriceLabel() tetap ada dan tidak diubah, kembali dorman
menunggu keputusan bisnis.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

# FASE 2 — Pusatkan aturan profit ke backend

Refactor murni. Perilaku terlihat **tidak berubah**: angka profit, margin, dan isi PDF Rincian Profit harus identik sebelum-sesudah (R3). Bisa dirilis sendiri.

### Task 2: Kontrak `profitFromRevenue()` + test double bersama

**Files:**
- Modify: `app/Contracts/SalesLineInvoiceRule.php:29-31` (tambah setelah `calculateTotal`)
- Modify: `app/Services/SalesLine/BaseSalesLineRule.php`
- Modify: `app/Services/SalesLine/TourRule.php`
- Create: `tests/Support/FakeSalesLineRule.php`, `tests/Support/FakeSalesLineRuleRegistry.php`
- Modify: `tests/Feature/SalesLine/SyncProformaThroughRegistryTest.php:36-56`
- Test: `tests/Unit/SalesLine/ProfitFromRevenueTest.php` (buat)

**Interfaces:**
- Produces:
  - `SalesLineInvoiceRule::profitFromRevenue(): bool` — `true` = profit dari tagihan customer (`total_idr` − Σ cost item); `false` = profit per item (Σ sell − Σ cost).
  - `BaseSalesLineRule::profitFromRevenue()` → `false`
  - `TourRule::profitFromRevenue()` → `true`
  - `Tests\Support\FakeSalesLineRule::__construct(float $totalMultiplier = 1.0, bool $profitFromRevenue = false)` — Task 5 menambah parameter ketiga `string $totalComposition = 'per_unit'`.
  - `Tests\Support\FakeSalesLineRuleRegistry::__construct(SalesLineInvoiceRule $rule)` dengan method `for(string $salesLine): SalesLineInvoiceRule`.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Unit/SalesLine/ProfitFromRevenueTest.php`:

```php
<?php

namespace Tests\Unit\SalesLine;

use App\Services\SalesLine\SalesLineRuleRegistry;
use PHPUnit\Framework\TestCase;

/**
 * Satu-satunya aturan uang yang bercabang per jenis: profit tipe `tour`
 * dihitung dari tagihan customer, tipe lain dari selisih per item
 * (docs/logika-pembuatan-invoice/08-perbedaan-per-tipe.md §8.3). Sebelum ini
 * aturan itu terduplikasi di tiga berkas, dua bahasa — lihat spec §1.2.
 */
class ProfitFromRevenueTest extends TestCase
{
    public function test_hanya_tour_yang_menghitung_profit_dari_tagihan(): void
    {
        $registry = new SalesLineRuleRegistry();

        $this->assertTrue($registry->for('tour')->profitFromRevenue());

        foreach (['hotel', 'guide', 'rental', 'mice', 'document', 'ticketing'] as $type) {
            $this->assertFalse(
                $registry->for($type)->profitFromRevenue(),
                "Jenis {$type} seharusnya profit per item"
            );
        }
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan GAGAL**

Run: `php artisan test --filter=ProfitFromRevenueTest`
Expected: FAIL dengan `Call to undefined method App\Services\SalesLine\TourRule::profitFromRevenue()`.

- [ ] **Step 3: Tambah method ke kontrak**

Di `app/Contracts/SalesLineInvoiceRule.php`, tambahkan setelah `calculateTotal()` (baris 30):

```php
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
```

- [ ] **Step 4: Nilai bawaan di Base, timpaan di TourRule**

Di `app/Services/SalesLine/BaseSalesLineRule.php`, tambahkan setelah `calculateTotal()` (setelah baris 26):

```php
    /** Mayoritas jenis: profit per item. TourRule menimpanya. */
    public function profitFromRevenue(): bool
    {
        return false;
    }
```

Di `app/Services/SalesLine/TourRule.php`, tambahkan setelah `defaultMultipliers()`:

```php
    /**
     * Tour inbound/outbound ditagih gelondongan per pax; item invoice hanya
     * merinci modalnya. Jadi pendapatan = tagihan customer, bukan Σ sell item.
     */
    public function profitFromRevenue(): bool
    {
        return true;
    }
```

- [ ] **Step 5: Buat test double bersama**

Kelas anonim di `tests/Feature/SalesLine/SyncProformaThroughRegistryTest.php:39` sekarang **pecah dengan fatal error** karena tidak mengimplementasikan `profitFromRevenue()`. Ganti dengan test double bersama, bukan tambal di tempat.

Buat `tests/Support/FakeSalesLineRule.php`:

```php
<?php

namespace Tests\Support;

use App\Contracts\SalesLineInvoiceRule;
use App\Models\Invoice;

/**
 * Aturan jenis penjualan palsu untuk test yang perlu membuktikan jalur kode
 * benar-benar melewati registry, bukan menghitung sendiri.
 *
 * Sengaja satu kelas bersama, bukan kelas anonim di dalam test: setiap
 * penambahan method ke SalesLineInvoiceRule hanya perlu diikuti di SATU
 * tempat ini. Kelas anonim yang tersebar akan pecah dengan fatal error, bukan
 * kegagalan assertion yang menjelaskan diri.
 */
final class FakeSalesLineRule implements SalesLineInvoiceRule
{
    public function __construct(
        private float $totalMultiplier = 1.0,
        private bool $profitFromRevenue = false,
    ) {
    }

    public function unitPriceLabel(): string
    {
        return 'Harga palsu';
    }

    public function defaultMultipliers(Invoice $invoice): array
    {
        return [];
    }

    /** Mengabaikan pengali sungguhan supaya efeknya tak mungkin tertukar. */
    public function calculateTotal(float $unitPrice, array $multipliers): float
    {
        return $unitPrice * $this->totalMultiplier;
    }

    public function profitFromRevenue(): bool
    {
        return $this->profitFromRevenue;
    }
}
```

Buat juga registry palsunya di berkas yang sama direktori — `tests/Support/FakeSalesLineRuleRegistry.php`:

```php
<?php

namespace Tests\Support;

use App\Contracts\SalesLineInvoiceRule;

/**
 * Pengganti SalesLineRuleRegistry di container. Tidak meng-extend kelas
 * aslinya (yang `final`); container mengembalikan apa pun yang di-bind, dan
 * pemanggilnya hanya butuh ->for().
 */
final class FakeSalesLineRuleRegistry
{
    public function __construct(private SalesLineInvoiceRule $rule)
    {
    }

    public function for(string $salesLine): SalesLineInvoiceRule
    {
        return $this->rule;
    }
}
```

- [ ] **Step 6: Pakai test double di test yang sudah ada**

Di `tests/Feature/SalesLine/SyncProformaThroughRegistryTest.php`, ganti seluruh isi `test_syncProformaTotal_benar_benar_memakai_registry()` (baris 29-62) menjadi:

```php
    public function test_syncProformaTotal_benar_benar_memakai_registry(): void
    {
        // Registry palsu memberi aturan pengali ×1000, membuktikan
        // syncProformaTotal memanggil registry, bukan menghitung sendiri.
        $this->app->instance(
            SalesLineRuleRegistry::class,
            new FakeSalesLineRuleRegistry(new FakeSalesLineRule(totalMultiplier: 1000.0))
        );

        $invoice = $this->makeInvoice($this->makeTour('tour', ['pax' => 4]), 1_000);

        // Bila registry dipakai: 1.000 × 1000 = 1.000.000. Bila tidak: 1.000 × 4.
        $this->assertEquals(1_000_000, $invoice->total);
    }
```

Perbarui blok `use` di kepala berkas itu — hapus yang tidak lagi dipakai, tambahkan yang baru:

```php
use App\Models\Invoice;
use App\Services\SalesLine\SalesLineRuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\Support\FakeSalesLineRule;
use Tests\Support\FakeSalesLineRuleRegistry;
use Tests\TestCase;
```

(`App\Contracts\SalesLineInvoiceRule` dan `App\Services\SalesLine\Multiplier` tidak lagi dipakai berkas ini — hapus keduanya dari `use`.)

- [ ] **Step 7: Jalankan test, pastikan LULUS**

Run: `php artisan test --filter="ProfitFromRevenueTest|SyncProformaThroughRegistryTest"`
Expected: PASS. Bila `SyncProformaThroughRegistryTest` masih fatal error, berarti masih ada kelas anonim yang tertinggal — cari dengan `grep -rn "implements SalesLineInvoiceRule" tests`.

- [ ] **Step 8: Test penuh**

Run: `php artisan test`
Expected: PASS semua.

- [ ] **Step 9: Commit**

```bash
git add app/Contracts/SalesLineInvoiceRule.php app/Services/SalesLine/BaseSalesLineRule.php app/Services/SalesLine/TourRule.php tests/Support/FakeSalesLineRule.php tests/Support/FakeSalesLineRuleRegistry.php tests/Unit/SalesLine/ProfitFromRevenueTest.php tests/Feature/SalesLine/SyncProformaThroughRegistryTest.php
git commit -m "feat: aturan profit per jenis pindah ke kontrak SalesLineInvoiceRule

profitFromRevenue() jadi sumber kebenaran tunggal untuk satu-satunya aturan
uang yang bercabang per jenis (SS8.3): tour dari tagihan customer, jenis lain
dari selisih per item. Base false, TourRule true.

Test double SalesLineInvoiceRule diekstrak jadi Tests\\Support\\FakeSalesLineRule
supaya penambahan method ke kontrak hanya perlu diikuti di satu tempat -
kelas anonim yang tersebar pecah dengan fatal error, bukan assertion yang
menjelaskan diri.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

### Task 3: Salurkan aturan ke frontend lewat prop Inertia

**Files:**
- Modify: `app/Http/Controllers/TourController.php:136-169`
- Test: `tests/Feature/SalesLine/SalesLinePropPayloadTest.php` (buat)

**Interfaces:**
- Consumes: `SalesLineInvoiceRule::profitFromRevenue(): bool` (Task 2)
- Produces: prop Inertia `salesLine` pada halaman `Tours/Edit`, berbentuk:
  ```php
  ['key' => string, 'profitFromRevenue' => bool]
  ```
  Task 5 menambahkan key `totalComposition`. Task 4 mengonsumsinya di Vue.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/SalesLine/SalesLinePropPayloadTest.php`:

```php
<?php

namespace Tests\Feature\SalesLine;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * D3/D4: aturan uang per jenis hanya hidup di backend dan disalurkan ke Vue
 * lewat payload Inertia halaman Tours/Edit. D5: hanya properti yang benar-benar
 * dikonsumsi yang dikirim — unitPriceLabel TIDAK dikirim karena satuannya
 * masih ditunda (D2).
 */
class SalesLinePropPayloadTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    public function test_payload_memuat_profit_from_revenue_yang_benar_per_jenis(): void
    {
        $harapan = [
            'tour' => true, 'hotel' => false, 'guide' => false, 'rental' => false,
            'mice' => false, 'document' => false, 'ticketing' => false,
        ];

        foreach ($harapan as $type => $expected) {
            $tour = $this->makeTour($type);

            $this->actingAs($this->salesUser())
                ->get(route('tours.edit', $tour->id))
                ->assertInertia(fn ($page) => $page
                    ->where('salesLine.key', $type)
                    ->where('salesLine.profitFromRevenue', $expected));
        }
    }

    public function test_payload_tidak_mengirim_unit_price_label(): void
    {
        // D5 + D2: satuan per jenis belum diputuskan, jadi tidak boleh bocor
        // ke frontend dalam bentuk apa pun.
        $tour = $this->makeTour('rental');

        $this->actingAs($this->salesUser())
            ->get(route('tours.edit', $tour->id))
            ->assertInertia(fn ($page) => $page->missing('salesLine.unitPriceLabel'));
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan GAGAL**

Run: `php artisan test --filter=SalesLinePropPayloadTest`
Expected: FAIL — `test_payload_memuat_profit_from_revenue_yang_benar_per_jenis` gagal karena prop `salesLine` belum ada. (Test kedua kemungkinan LULUS sejak awal, sebab `missing()` pada prop yang seluruhnya belum ada juga benar — itu wajar, biarkan.)

**Catatan bila test gagal karena 403:** `Tour::isAccessibleBy()` membatasi akses per pemilik. Bila `salesUser()` tertolak, buat tour dengan pemiliknya: `$this->makeTour($type, ['user_id' => $user->id])` dengan `$user = $this->salesUser()` dibuat lebih dulu. Periksa nama kolom pemilik di `app/Models/Tour.php` method `isAccessibleBy()` sebelum menebak.

- [ ] **Step 3: Tambah prop di controller**

Di `app/Http/Controllers/TourController.php`, di dalam `edit()`, tambahkan sebelum `return Inertia::render(...)` (setelah baris 141):

```php
        $rule = app(SalesLineRuleRegistry::class)->for($tour->type ?? 'tour');
```

Lalu tambahkan entri ini ke array `Inertia::render('Tours/Edit', [...])`, sesudah `'tour' => $tour,`:

```php
            // D3/D4: satu-satunya jalan aturan uang per jenis sampai ke Vue.
            // Frontend tidak boleh punya peta jenis sendiri. D5: hanya properti
            // yang benar-benar dikonsumsi yang dikirim.
            'salesLine'   => [
                'key'               => $tour->type ?? 'tour',
                'profitFromRevenue' => $rule->profitFromRevenue(),
            ],
```

Tambahkan import di kepala berkas bila belum ada:

```php
use App\Services\SalesLine\SalesLineRuleRegistry;
```

Periksa dulu dengan `grep -n "SalesLineRuleRegistry" app/Http/Controllers/TourController.php` — bila sudah ada, jangan duplikat.

- [ ] **Step 4: Jalankan test, pastikan LULUS**

Run: `php artisan test --filter=SalesLinePropPayloadTest`
Expected: PASS, 2 test.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/TourController.php tests/Feature/SalesLine/SalesLinePropPayloadTest.php
git commit -m "feat: salurkan aturan jenis penjualan ke Vue lewat prop Inertia salesLine

Tours/Edit menerima salesLine.key dan salesLine.profitFromRevenue dari
SalesLineRuleRegistry. Ikut payload awal, bukan permintaan terpisah, jadi
tidak ada jendela kosong saat render pertama (R4).

unitPriceLabel sengaja tidak dikirim: satuan per jenis masih ditunda (D2/D5).

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

### Task 4: Tiga konsumen berhenti memutuskan sendiri

**Files:**
- Modify: `resources/js/Pages/Tours/Edit.vue:24-36` (defineProps), `:105`, `:115`
- Modify: `resources/js/Components/Tours/InvoicesPanel.vue:148`, `:162`, `:191`, `:284`, `:942`
- Modify: `resources/js/Components/Tours/CostingPanel.vue:7`, `:11-13`
- Modify: `app/Http/Controllers/InvoiceController.php:247-248`
- Test: `tests/Feature/Invoice/ProfitFormulaCharacterizationTest.php` (buat)

**Interfaces:**
- Consumes: prop `salesLine` dari Task 3 — `{ key: string, profitFromRevenue: boolean }`
- Produces: `InvoicesPanel.vue` mengekspos computed `profitFromRevenue` (menggantikan `isTourType`); `CostingPanel.vue` menerima prop `salesLine`.

- [ ] **Step 1: Tulis test karakterisasi yang harus LULUS sebelum dan sesudah**

R3 menuntut nol selisih. Test ini ditulis **sebelum** refactor dan harus lulus di kedua sisi.

Buat `tests/Feature/Invoice/ProfitFormulaCharacterizationTest.php`:

API yang dipakai di bawah sudah diverifikasi ada (30 Jul 2026): `CreatesSalesFixtures` menyediakan `salesUser()`, `makeTour()`, `makeInvoice()`, dan `approveInvoice()` — **tidak ada** `attachItem()` atau `lockAndApprove()`. Item invoice dibuat lewat `InvoiceItem::create()` seperti di `tests/Feature/Invoice/ApprovalSideEffectsTest.php:62`. Nama route `invoices.profit-pdf` sudah dipastikan lewat `php artisan route:list --name=profit`.

```php
<?php

namespace Tests\Feature\Invoice;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * R3: Fase 2 adalah refactor murni. Angka profit dan margin harus identik
 * sebelum dan sesudah aturan dipindahkan ke rule. Test ini memakukan nilainya
 * pada angka harfiah — bukan pada rumus — supaya penggeseran sekecil apa pun
 * tertangkap.
 */
class ProfitFormulaCharacterizationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    /** Item dengan cost 300.000 dan sell 400.000, qty 10 → cost 3jt, sell 4jt. */
    private function invoiceDenganItem(string $type, float $unitPrice): Invoice
    {
        $product = Product::create([
            'name' => 'Kamar Deluxe',
            'type' => 'hotel',
            'cost' => 300_000,
            'sell' => 400_000,
        ]);

        $tour    = $this->makeTour($type, ['pax' => 10]);
        $invoice = $this->makeInvoice($tour, $unitPrice);

        InvoiceItem::create([
            'invoice_id'   => $invoice->id,
            'product_id'   => $product->id,
            'product_type' => $product->type,
            'description'  => $product->name,
            'qty'          => 10,
            'nights'       => 1,
            'unit_cost'    => 300_000,
            'unit_sell'    => 400_000,
        ]);

        return $this->approveInvoice($invoice->fresh());
    }

    public function test_pdf_profit_tour_memakai_tagihan_customer(): void
    {
        // pax 10 × 1.000.000 = 10.000.000 tagihan; Σ cost item = 3.000.000.
        $invoice = $this->invoiceDenganItem('tour', 1_000_000);

        $this->assertEquals(10_000_000, $invoice->total_idr);

        $this->actingAs($this->salesUser())
            ->get(route('invoices.profit-pdf', $invoice->id))
            ->assertOk();

        // Aturan tour: revenue = total_idr (10jt), BUKAN Σ sell item (4jt).
        // profit = 10.000.000 − 3.000.000 = 7.000.000 → margin 70,0%
        $revenue = (float) $invoice->total_idr;
        $cost    = (float) $invoice->items->sum('line_cost');

        $this->assertSame(3_000_000.0, $cost);
        $this->assertSame(7_000_000.0, $revenue - $cost);
        $this->assertSame(70.0, round(($revenue - $cost) / $revenue * 100, 1));
    }

    public function test_pdf_profit_non_tour_memakai_selisih_per_item(): void
    {
        $invoice = $this->invoiceDenganItem('rental', 1_000_000);

        $this->actingAs($this->salesUser())
            ->get(route('invoices.profit-pdf', $invoice->id))
            ->assertOk();

        // Aturan non-tour: revenue = Σ sell item (4jt), BUKAN tagihan (10jt).
        // profit = 4.000.000 − 3.000.000 = 1.000.000 → margin 25,0%
        $revenue = (float) $invoice->items->sum('line_sell');
        $cost    = (float) $invoice->items->sum('line_cost');

        $this->assertSame(4_000_000.0, $revenue);
        $this->assertSame(1_000_000.0, $revenue - $cost);
        $this->assertSame(25.0, round(($revenue - $cost) / $revenue * 100, 1));
    }
}
```

**Bila `Product::create()` gagal karena kolom wajib:** lihat `tests/Feature/Invoice/ApprovalSideEffectsTest.php:52-58` untuk daftar kolom yang benar-benar dipakai di sana, dan ikuti persis. Jangan menambah kolom karangan.

**Bila `line_cost`/`line_sell` ternyata bukan accessor:** cek `app/Models/InvoiceItem.php`. Test ini bergantung padanya karena `InvoiceController::profitPdf():249-250` juga memakainya — bila namanya beda, ikuti nama di controller, bukan di rencana ini.

- [ ] **Step 2: Jalankan test, pastikan LULUS (baseline)**

Run: `php artisan test --filter=ProfitFormulaCharacterizationTest`
Expected: PASS. Ini patokan sebelum refactor. **Bila gagal di sini, perbaiki test-nya dulu** — test karakterisasi yang salah tidak bisa membuktikan apa pun.

- [ ] **Step 3: Terima & teruskan prop di Edit.vue**

Di `resources/js/Pages/Tours/Edit.vue`, tambahkan `salesLine` ke `defineProps` (baris 24):

```js
const props = defineProps({
    // ...properti yang sudah ada, jangan diubah...
    salesLine: {
        type: Object,
        // R5: nilai bawaan aman bila suatu halaman lupa mengirimnya —
        // perilaku mayoritas (profit per item), bukan galat.
        default: () => ({ key: 'tour', profitFromRevenue: false }),
    },
})
```

Teruskan ke kedua panel — baris 105:

```html
<InvoicesPanel v-if="tour.status === 'confirmed'" :tour="tour" :sales-line="salesLine" :products="products" :bank-accounts="bankAccounts" :cash-accounts="cashAccounts" />
```

Baris 115:

```html
<CostingPanel :tour="tour" :sales-line="salesLine" />
```

- [ ] **Step 4: `InvoicesPanel.vue` baca prop, bukan `tour.type`**

Tambahkan `salesLine` ke `defineProps` komponen ini (cari `const props = defineProps(` di kepala berkas), dengan nilai bawaan yang sama seperti Step 3.

Ganti baris 146-148:

```js
// Aturan profit datang dari backend (SalesLineRuleRegistry), bukan dari
// percabangan tipe di sini — lihat spec D3.
const profitFromRevenue = computed(() => props.salesLine.profitFromRevenue)
```

Ganti keempat pemakaiannya:

Baris 162 (dalam `invProfit`):
```js
    if (profitFromRevenue.value) {
```

Baris 191 (dalam `copyProfitTable`):
```js
    if (profitFromRevenue.value) {
```

Baris 284 (dalam `invMargin`):
```js
    const base = profitFromRevenue.value
```

Baris 942 (template):
```html
<span v-if="profitFromRevenue" class="block">Profit tour = Total tagihan (harga/pax × pax) − total cost item.</span>
```

- [ ] **Step 5: `CostingPanel.vue` baca prop**

Ganti baris 7:

```js
const props = defineProps({
    tour: Object,
    salesLine: {
        type: Object,
        default: () => ({ key: 'tour', profitFromRevenue: false }),
    },
})
```

Ganti baris 9-13:

```js
// Angka berasal dari invoice bila aturan jenisnya menghitung profit dari
// tagihan customer DAN sudah ada invoice yang disetujui.
const fromInvoice = computed(() =>
    props.salesLine.profitFromRevenue && (props.tour.invoices ?? []).some(i => i.approved_at)
)
```

- [ ] **Step 6: `InvoiceController::profitPdf()` baca rule**

Ganti baris 247-248:

```php
        $tour      = $invoice->tour;
        $isTour    = app(SalesLineRuleRegistry::class)->for($tour->type ?? 'tour')->profitFromRevenue();
```

Variabel tetap bernama `$isTour` supaya baris 253 dan 262 serta blade `finance.profit_breakdown` tidak perlu disentuh. Bila mau lebih jujur, ganti namanya sekalian di ketiga tempat — tapi itu menambah luas diff tanpa mengubah perilaku; putuskan saat implementasi, jangan setengah-setengah.

- [ ] **Step 7: Jalankan test karakterisasi, pastikan MASIH LULUS**

Run: `php artisan test --filter=ProfitFormulaCharacterizationTest`
Expected: PASS, angka identik dengan Step 2. **Ada selisih = berhenti**, jangan lanjut (R3).

- [ ] **Step 8: Pastikan tidak ada percabangan jenis yang tersisa di frontend**

Run:
```bash
grep -rn "tour.type ===\|tour\.type ==\|isTourType" resources/js
```
Expected: tidak ada keluaran. Bila ada di `HeaderPanel.vue` atau `QuotationPanel.vue`, **biarkan** — itu soal field mana yang tampil, wilayah Fase 5 dokumen lama, di luar lingkup (spec §7). Yang tidak boleh tersisa adalah di `InvoicesPanel.vue` dan `CostingPanel.vue`.

- [ ] **Step 9: Test penuh + build**

Run: `php artisan test`
Expected: PASS semua.

Run: `npm run build`
Expected: sukses.

- [ ] **Step 10: Commit**

```bash
git add resources/js/Pages/Tours/Edit.vue resources/js/Components/Tours/InvoicesPanel.vue resources/js/Components/Tours/CostingPanel.vue app/Http/Controllers/InvoiceController.php tests/Feature/Invoice/ProfitFormulaCharacterizationTest.php
git commit -m "refactor: tiga konsumen aturan profit baca rule, bukan tipe tour sendiri

InvoicesPanel, CostingPanel, dan InvoiceController::profitPdf() berhenti
memutuskan sendiri apakah profit dihitung dari tagihan atau per item.
Ketiganya kini membaca profitFromRevenue dari SalesLineRuleRegistry -
lewat prop Inertia salesLine untuk yang di frontend.

Sebelum ini mengubah aturan profit satu jenis menuntut penyuntingan tiga
berkas serempak; lupa satu berarti angka di panel, Ringkasan Biaya, dan PDF
Rincian Profit berbeda tanpa galat apa pun.

Refactor murni: test karakterisasi memastikan angka profit dan margin
identik sebelum-sesudah untuk tipe tour maupun non-tour.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

# FASE 3 — Mode tagihan baris-bernominal untuk rental

Perilaku terlihat **berubah untuk rental**. Punya prasyarat rilis yang tidak boleh dilewat.

## Prasyarat Rilis Fase 3 (dijalankan di PRODUCTION, bukan lokal)

- [ ] **Jalankan query hitung §5a di database production.** DB lokal tidak punya satu pun tour rental (diverifikasi 30 Jul 2026), jadi hasil lokal tidak membuktikan apa pun.

```sql
SELECT i.id, i.number, i.unit_price, t.code
FROM invoices i
JOIN tours t ON t.id = i.tour_id
WHERE t.type = 'rental' AND i.approved_at IS NULL AND i.unit_price > 0;
```

- [ ] **Bila jumlahnya sedikit (perkiraan spec: 1):** serahkan daftar invoice dari query itu ke sales sebagai daftar kerja koreksi manual. Sales memasukkan ulang rinciannya sebagai baris bernominal — hasilnya lebih baik daripada tebakan skrip, karena sales tahu rincian sebenarnya (mis. `INV-2026-13-0002`: Avanza Rp800.000 tanggal 22 Jul, Innova Reborn Rp1.050.000 + biaya luar kota Rp200.000 tanggal 25 Jul).
- [ ] **Bila jumlahnya banyak (di luar perkiraan):** BERHENTI. Tinjau ulang rencana §5 dan pertimbangkan migrasi berskrip dengan protokol penuh §7.4–7.5 dokumen lama (tulis kolom langsung, dilarang memanggil `syncProformaTotal()`, verifikasi nol selisih, uji di salinan data production, backup terverifikasi). Jangan lanjutkan diam-diam.

Banner di Task 8 tetap dibangun apa pun hasil querynya — ia menutup kasus invoice rental baru yang dibuat setelah query dijalankan tapi sebelum Fase 3 dirilis.

### Task 5: Kontrak `totalComposition()`

**Files:**
- Modify: `app/Contracts/SalesLineInvoiceRule.php`
- Modify: `app/Services/SalesLine/BaseSalesLineRule.php`
- Modify: `app/Services/SalesLine/TransportRule.php`
- Modify: `tests/Support/FakeSalesLineRule.php`
- Modify: `app/Http/Controllers/TourController.php` (prop `salesLine` bertambah key)
- Modify: `tests/Feature/SalesLine/SalesLinePropPayloadTest.php` (tambah assertion)
- Test: `tests/Unit/SalesLine/TotalCompositionTest.php` (buat)

**Interfaces:**
- Produces:
  - `SalesLineInvoiceRule::totalComposition(): string` — `'per_unit'` atau `'line_items'`
  - `BaseSalesLineRule::totalComposition()` → `'per_unit'`
  - `TransportRule::totalComposition()` → `'line_items'`
  - Prop `salesLine` menjadi `['key' => string, 'profitFromRevenue' => bool, 'totalComposition' => string]`
  - `FakeSalesLineRule::__construct(float $totalMultiplier = 1.0, bool $profitFromRevenue = false, string $totalComposition = 'per_unit')`

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Unit/SalesLine/TotalCompositionTest.php`:

```php
<?php

namespace Tests\Unit\SalesLine;

use App\Services\SalesLine\SalesLineRuleRegistry;
use PHPUnit\Framework\TestCase;

/**
 * D6/D7: rental menyusun total dari jumlah nominal baris deskripsi, enam jenis
 * lain tetap unit_price × pengali. Ditentukan per jenis lewat rule, bukan
 * dipilih sales per invoice.
 */
class TotalCompositionTest extends TestCase
{
    public function test_hanya_rental_memakai_komposisi_line_items(): void
    {
        $registry = new SalesLineRuleRegistry();

        $this->assertSame('line_items', $registry->for('rental')->totalComposition());

        foreach (['tour', 'hotel', 'guide', 'mice', 'document', 'ticketing'] as $type) {
            $this->assertSame(
                'per_unit',
                $registry->for($type)->totalComposition(),
                "Jenis {$type} tidak boleh berubah komposisinya"
            );
        }
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan GAGAL**

Run: `php artisan test --filter=TotalCompositionTest`
Expected: FAIL dengan `Call to undefined method ...::totalComposition()`.

- [ ] **Step 3: Tambah method ke kontrak**

Di `app/Contracts/SalesLineInvoiceRule.php`, setelah `profitFromRevenue()`:

```php
    /**
     * Cara total disusun:
     *   'per_unit'   = unit_price × hasil kali pengali
     *   'line_items' = jumlah nominal baris deskripsi (unit_price diabaikan)
     *
     * Rental kerap menagih beberapa unit berbeda dengan harga masing-masing
     * (Avanza + Innova + biaya luar kota), yang tidak muat di satu harga satuan.
     */
    public function totalComposition(): string;
```

- [ ] **Step 4: Nilai bawaan + timpaan**

Di `app/Services/SalesLine/BaseSalesLineRule.php`, setelah `profitFromRevenue()`:

```php
    /** Mayoritas jenis: satu harga satuan dikali kuantitas. */
    public function totalComposition(): string
    {
        return 'per_unit';
    }
```

Di `app/Services/SalesLine/TransportRule.php`, setelah `defaultMultipliers()`:

```php
    /**
     * Rental menagih beberapa unit berbeda dengan harga masing-masing, jadi
     * totalnya dijumlah dari baris bernominal — bukan satu harga × kuantitas.
     * unit_price diabaikan untuk jenis ini.
     */
    public function totalComposition(): string
    {
        return 'line_items';
    }
```

- [ ] **Step 5: Perbarui test double**

Di `tests/Support/FakeSalesLineRule.php`, tambahkan parameter konstruktor dan method:

```php
    public function __construct(
        private float $totalMultiplier = 1.0,
        private bool $profitFromRevenue = false,
        private string $totalComposition = 'per_unit',
    ) {
    }
```

```php
    public function totalComposition(): string
    {
        return $this->totalComposition;
    }
```

- [ ] **Step 6: Tambah key ke prop Inertia**

Di `app/Http/Controllers/TourController.php`, lengkapi entri `salesLine`:

```php
            'salesLine'   => [
                'key'               => $tour->type ?? 'tour',
                'profitFromRevenue' => $rule->profitFromRevenue(),
                'totalComposition'  => $rule->totalComposition(),
            ],
```

Tambahkan assertion ke `tests/Feature/SalesLine/SalesLinePropPayloadTest.php`:

```php
    public function test_payload_memuat_komposisi_total_per_jenis(): void
    {
        $harapan = [
            'rental' => 'line_items',
            'tour'   => 'per_unit',
            'hotel'  => 'per_unit',
            'guide'  => 'per_unit',
        ];

        foreach ($harapan as $type => $expected) {
            $tour = $this->makeTour($type);

            $this->actingAs($this->salesUser())
                ->get(route('tours.edit', $tour->id))
                ->assertInertia(fn ($page) => $page->where('salesLine.totalComposition', $expected));
        }
    }
```

- [ ] **Step 7: Jalankan test, pastikan LULUS**

Run: `php artisan test --filter="TotalCompositionTest|SalesLinePropPayloadTest"`
Expected: PASS.

- [ ] **Step 8: Test penuh**

Run: `php artisan test`
Expected: PASS semua.

- [ ] **Step 9: Commit**

```bash
git add app/Contracts/SalesLineInvoiceRule.php app/Services/SalesLine/BaseSalesLineRule.php app/Services/SalesLine/TransportRule.php app/Http/Controllers/TourController.php tests/Support/FakeSalesLineRule.php tests/Unit/SalesLine/TotalCompositionTest.php tests/Feature/SalesLine/SalesLinePropPayloadTest.php
git commit -m "feat: kontrak totalComposition() - rental menyusun total dari baris bernominal

Rental kerap menagih beberapa unit berbeda dengan harga masing-masing
(Avanza + Innova + biaya luar kota), yang tidak muat di rumus
unit_price x pax. Hari ini sales menjumlahkannya manual di luar sistem lalu
mengetik hasilnya ke satu field, dengan rinciannya sebagai teks bebas -
sistem tidak pernah memeriksa penjumlahan itu benar.

Base per_unit, TransportRule line_items. Belum dipakai menghitung; itu
task berikutnya.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

### Task 6: `syncProformaTotal()` menghormati komposisi total

**Files:**
- Modify: `app/Models/Invoice.php:108-135`
- Test: `tests/Feature/Invoice/LineItemsCompositionTest.php` (buat)

**Interfaces:**
- Consumes: `SalesLineInvoiceRule::totalComposition(): string` (Task 5)
- Produces: tidak ada API baru. Perilaku: untuk tour bertipe `rental`, `total` = Σ `description_lines[].amount` dan `unit_price` diabaikan.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/Invoice/LineItemsCompositionTest.php`:

```php
<?php

namespace Tests\Feature\Invoice;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * D6: total rental = jumlah nominal baris deskripsi. Enam jenis lain tetap
 * unit_price × pengali + baris bernominal (perilaku lama, tidak boleh bergeser).
 */
class LineItemsCompositionTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    public function test_total_rental_adalah_jumlah_baris_bernominal(): void
    {
        $tour    = $this->makeTour('rental', ['pax' => 10]);
        $invoice = $this->makeInvoice($tour, 2_050_000, [
            'description_lines' => [
                ['label' => 'Avanza', 'date' => '2026-07-22', 'detail' => 'Sewa harian', 'amount' => 800_000],
                ['label' => 'Innova Reborn', 'date' => '2026-07-25', 'detail' => 'Sewa harian', 'amount' => 1_050_000],
                ['label' => 'Luar kota', 'date' => '2026-07-25', 'detail' => 'Tambahan', 'amount' => 200_000],
            ],
        ]);

        // unit_price 2.050.000 DIABAIKAN; total murni dari ketiga baris.
        $this->assertEquals(2_050_000, $invoice->total);
        $this->assertEquals(2_050_000, $invoice->total_idr);
    }

    public function test_rental_tanpa_baris_bernominal_bertotal_nol(): void
    {
        // R1: inilah keadaan peralihan yang banner §5c wajib peringatkan.
        // Total Rp0 adalah perilaku yang DIINGINKAN di sini, bukan bug —
        // yang tidak boleh adalah sales tidak menyadarinya.
        $tour    = $this->makeTour('rental', ['pax' => 10]);
        $invoice = $this->makeInvoice($tour, 2_050_000);

        $this->assertEquals(0, $invoice->total);
        // unit_price lamanya TETAP UTUH supaya banner bisa menampilkannya.
        $this->assertEquals(2_050_000, $invoice->unit_price);
    }

    public function test_jenis_lain_tetap_unit_price_kali_pax_plus_baris_bernominal(): void
    {
        foreach (['tour', 'hotel', 'guide', 'mice', 'document', 'ticketing'] as $type) {
            $tour    = $this->makeTour($type, ['pax' => 4]);
            $invoice = $this->makeInvoice($tour, 1_000_000, [
                'description_lines' => [
                    ['label' => 'Dokumen', 'date' => '', 'detail' => 'Izin', 'amount' => 500_000],
                ],
            ]);

            // 1.000.000 × 4 + 500.000
            $this->assertEquals(4_500_000, $invoice->total, "Jenis {$type}");
        }
    }

    public function test_membuka_halaman_tour_rental_tidak_mengubah_total_di_database(): void
    {
        // Dasar penurunan tingkat R1 dari Tinggi ke Sedang: syncProformaTotal()
        // tidak berjalan saat halaman sekadar dibuka, jadi tidak ada kehilangan
        // diam-diam. Bila test ini gagal, R1 kembali jadi Tinggi dan rencana §5
        // harus ditinjau ulang SEBELUM rilis.
        $user    = $this->salesUser();
        $tour    = $this->makeTour('rental', ['pax' => 10]);
        $invoice = $this->makeInvoice($tour, 2_050_000, [
            'description_lines' => [
                ['label' => 'Avanza', 'date' => '2026-07-22', 'detail' => '', 'amount' => 800_000],
            ],
        ]);

        $totalSebelum     = $invoice->total;
        $unitPriceSebelum = $invoice->unit_price;

        $this->actingAs($user)->get(route('tours.edit', $tour->id))->assertOk();

        $segar = $invoice->fresh();
        $this->assertEquals($totalSebelum, $segar->total);
        $this->assertEquals($unitPriceSebelum, $segar->unit_price);
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan GAGAL**

Run: `php artisan test --filter=LineItemsCompositionTest`
Expected: FAIL. `test_total_rental_adalah_jumlah_baris_bernominal` menghasilkan 22.550.000 (2.050.000 × 10 + 2.050.000) alih-alih 2.050.000. `test_rental_tanpa_baris_bernominal_bertotal_nol` menghasilkan 20.500.000 alih-alih 0.

- [ ] **Step 3: Implementasi**

Di `app/Models/Invoice.php`, ganti baris 113-119 (blok komentar Fase 1 + `$total = $rule->calculateTotal(...)`) menjadi:

```php
        // D6: rental menyusun total dari baris bernominal saja — unit_price
        // diabaikan. Enam jenis lain tetap unit_price × pengali. Pengali masih
        // tetap pax untuk semua jenis; menggantinya ke billing_quantities
        // adalah Fase 3 dokumen lama, pekerjaan tersendiri.
        $base = $rule->totalComposition() === 'line_items'
            ? 0.0
            : $rule->calculateTotal((float) $this->unit_price, [
                new Multiplier('pax', 'Peserta', $pax),
            ]);

        $total = $base;
```

Baris 125 yang menjumlahkan `description_lines` **tidak diubah** — ia sudah ada dan sudah benar:

```php
        $total += collect($this->description_lines ?? [])->sum(fn ($l) => (float) ($l['amount'] ?? 0));
```

- [ ] **Step 4: Jalankan test, pastikan LULUS**

Run: `php artisan test --filter=LineItemsCompositionTest`
Expected: PASS, 4 test.

- [ ] **Step 5: Jalankan regresi invoice yang sudah disetujui (R2, R6)**

Run: `php artisan test --filter="ApprovedInvoiceFrozenTest|ProformaTotalCharacterizationTest|AdditionalChargeCharacterizationTest|CurrencyCharacterizationTest|PaxSourceCharacterizationTest|StageGateCharacterizationTest"`
Expected: PASS semua. Ini yang mengunci D8/R2 (invoice disetujui tidak dihitung ulang) dan R6 (`baseline_total` tetap konsisten).

- [ ] **Step 6: Test penuh**

Run: `php artisan test`
Expected: PASS semua.

- [ ] **Step 7: Commit**

```bash
git add app/Models/Invoice.php tests/Feature/Invoice/LineItemsCompositionTest.php
git commit -m "feat: total invoice rental dijumlah dari baris bernominal

syncProformaTotal() memilih basis total lewat rule->totalComposition().
Rental: basis 0, total murni dari jumlah description_lines[].amount -
unit_price diabaikan. Enam jenis lain tidak bergeser sedikit pun.

Rental tanpa baris bernominal bertotal Rp0. Itu perilaku yang diinginkan,
bukan bug - tapi sales harus menyadarinya, dan banner peringatan panel
adalah task berikutnya. unit_price lama tetap utuh di database supaya
banner bisa menampilkannya.

Regresi: membuka halaman tour rental tanpa menyimpan tidak mengubah total
maupun unit_price - dasar penurunan tingkat R1 dari Tinggi ke Sedang.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

### Task 7: Tanggal pada baris bernominal

**Files:**
- Modify: `resources/js/Components/Tours/InvoicesPanel.vue:86` (baca), `:307` (kirim), `:334` (baris baru), `:837-847` (template)

**Interfaces:**
- Consumes: prop `salesLine` (Task 3/5)
- Produces: `additional_lines[]` kini berbentuk `{ label, date, detail, amount }` — Task 9 mencetak `date` itu di PDF.

- [ ] **Step 1: Baca `date` dari server**

Di `resources/js/Components/Tours/InvoicesPanel.vue` baris 85-86, tambahkan `date` ke pemetaan `additional_lines`:

```js
                additional_lines: Array.isArray(inv.description_lines)
                    ? inv.description_lines.filter(l => l.amount !== undefined && l.amount !== null).map(l => ({ label: l.label ?? '', date: l.date ?? '', detail: l.detail ?? '', amount: Number(l.amount) || 0 }))
```

- [ ] **Step 2: Kirim `date` ke server**

Baris 307 — hentikan hardcode `date: ''`:

```js
            ...f.additional_lines.map(l => ({ label: l.label, date: l.date ?? '', detail: l.detail, amount: Number(l.amount) || 0 })),
```

- [ ] **Step 3: Baris baru punya field `date`**

Baris 334:

```js
function addAdditionalLine(invId) {
    proformaForms[invId].additional_lines.push({ label: '', date: '', detail: '', amount: '' })
}
```

- [ ] **Step 4: Tambah input tanggal di template**

Di blok baris 837-847, sisipkan input tanggal antara input label dan input keterangan:

```html
                            <div v-for="(ln, idx) in proformaForms[inv.id].additional_lines" :key="idx"
                                class="flex flex-wrap items-start gap-2 px-3 py-2">
                                <input type="text" v-model="ln.label" @blur="saveProforma(inv.id)" placeholder="Label (mis. Dokumen)"
                                    class="w-32 border rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-primary" />
                                <input type="date" v-model="ln.date" @blur="saveProforma(inv.id)"
                                    class="w-36 border rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-primary" />
                                <input type="text" v-model="ln.detail" @blur="saveProforma(inv.id)" placeholder="Keterangan"
                                    class="flex-1 min-w-[10rem] border rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-primary" />
                                <input type="number" v-model="ln.amount" @blur="saveProforma(inv.id)" min="0" placeholder="Nominal"
                                    class="w-36 border rounded px-2 py-1 text-right text-sm font-mono focus:outline-none focus:ring-1 focus:ring-primary" />
                                <button type="button" @click="removeAdditionalLine(inv.id, idx)"
                                    class="text-muted-foreground hover:text-destructive transition-colors" title="Hapus baris">✕</button>
                            </div>
```

- [ ] **Step 5: Verifikasi validasi backend menerimanya**

Validasi `description_lines.*.date` dan `.amount` **sudah ada** di `updateProforma()` dan tidak perlu diubah (spec §3 Fase 3). Buktikan, jangan percaya:

```bash
grep -n "description_lines" -A 8 app/Http/Controllers/InvoiceController.php | grep -n "date\|amount\|nullable"
```

Bila aturan `date` ternyata `nullable|date` sementara input mengirim `''` (string kosong), validasi akan gagal. Cek dan bila perlu kirim `null` alih-alih `''` di Step 2. Jangan mengubah aturan validasinya.

- [ ] **Step 6: Build**

Run: `npm run build`
Expected: sukses.

- [ ] **Step 7: Commit**

```bash
git add resources/js/Components/Tours/InvoicesPanel.vue
git commit -m "feat: baris bernominal invoice bisa punya tanggal

Sebelumnya saveProforma() selalu mengirim date: '' yang di-hardcode, jadi
tanggal per baris mustahil diisi meski backend sudah memvalidasinya. Rental
menagih per tanggal pemakaian unit (Avanza 22 Jul, Innova 25 Jul), jadi
tanggal itu bagian dari rincian yang harus sampai ke customer.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

### Task 8: Tata letak `line_items` + banner peralihan

**Files:**
- Modify: `resources/js/Components/Tours/InvoicesPanel.vue:827-864`

**Interfaces:**
- Consumes: `props.salesLine.totalComposition` (Task 5)
- Produces: computed `isLineItems` dan `warnLegacyUnitPrice` di `InvoicesPanel.vue`

- [ ] **Step 1: Tambah dua computed**

Di `resources/js/Components/Tours/InvoicesPanel.vue`, dekat `profitFromRevenue` (Task 4):

```js
// D6: rental menyusun total dari baris bernominal, jadi blok "Harga / pax"
// tidak berlaku dan baris bernominal naik jadi bagian utama.
const isLineItems = computed(() => props.salesLine.totalComposition === 'line_items')

// R1/§5c: invoice rental lama yang nilainya masih di unit_price. Totalnya
// akan terbaca Rp0 sampai sales memasukkan rinciannya sebagai baris.
function warnLegacyUnitPrice(inv) {
    if (!isLineItems.value) return 0
    const f = proformaForms[inv.id]
    if (!f) return 0
    const adaBarisBernominal = (f.additional_lines ?? []).some(l => Number(l.amount) > 0)
    return adaBarisBernominal ? 0 : (Number(f.unit_price) || 0)
}
```

- [ ] **Step 2: Banner peringatan**

Sisipkan tepat sebelum blok baris bernominal (sebelum baris 827):

```html
                    <!-- §5c: keadaan peralihan rental — mustahil terlewat -->
                    <div v-if="warnLegacyUnitPrice(inv) > 0"
                        class="rounded-md border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                        Invoice ini masih memakai harga lama
                        <span class="font-mono font-semibold">{{ fmtCur(warnLegacyUnitPrice(inv), proformaForms[inv.id].currency) }}</span>.
                        Masukkan rinciannya sebagai baris di bawah, lalu simpan.
                        Selama belum diisi, total akan terbaca Rp 0.
                    </div>
```

- [ ] **Step 3: Judul blok mengikuti komposisi**

Baris 830:

```html
                            <span class="text-xs font-semibold uppercase text-muted-foreground">{{ isLineItems ? 'Rincian Tagihan' : 'Biaya Tambahan (di luar harga/pax)' }}</span>
```

Baris 833-835 (teks kosong):

```html
                        <div v-if="proformaForms[inv.id].additional_lines.length === 0" class="px-3 py-4 text-center text-xs text-muted-foreground">
                            {{ isLineItems
                                ? 'Belum ada rincian. Klik "+ Biaya" untuk menambah tiap unit beserta tanggal dan nominalnya.'
                                : 'Belum ada biaya tambahan. Klik "+ Biaya" untuk menambah (mis. biaya dokumen, izin khusus).' }}
                        </div>
```

- [ ] **Step 4: Sembunyikan blok "Harga / pax" untuk `line_items`**

Baris 852 — tambahkan `v-if`:

```html
                    <div v-if="!isLineItems" class="flex flex-wrap items-end gap-3 rounded-md bg-muted/20 px-4 py-3">
```

Karena blok itu juga memuat satu-satunya tampilan total, tambahkan pengganti total untuk `line_items` tepat sesudahnya:

```html
                    <div v-else class="flex flex-wrap items-end justify-end gap-3 rounded-md bg-muted/20 px-4 py-3">
                        <div class="text-sm pb-1">
                            Total rincian =
                            <span class="font-mono font-semibold">{{ fmtCur(proformaTotal(inv.id), proformaForms[inv.id].currency) }}</span>
                        </div>
                    </div>
```

- [ ] **Step 5: `proformaTotal()` hormati komposisi**

Baris 139-145 masih selalu menghitung `unit_price × pax`. Tanpa ini, angka di layar berbeda dari yang backend simpan:

```js
function proformaTotal(invId) {
    const f = proformaForms[invId]
    if (!f) return 0
    const base = isLineItems.value ? 0 : (Number(f.unit_price) || 0) * Math.max(tourPax.value, 1)
    const additional = (f.additional_lines ?? []).reduce((s, l) => s + (Number(l.amount) || 0), 0)
    return base + additional
}
```

- [ ] **Step 6: Ringkasan invoice yang sudah disetujui**

Baris 896-902 mencetak `Price: ... × N pax`. Untuk `line_items` itu menyesatkan (unit_price diabaikan). Ganti baris 897-899:

```html
                        <template v-if="!isLineItems">
                            Price:
                            <span class="font-mono">{{ fmtCur(inv.unit_price, inv.currency) }}</span>
                            × {{ tourPax || 1 }} pax
                        </template>
                        <template v-else>Total rincian</template>
```

- [ ] **Step 7: Build + tinjau di aplikasi**

Run: `npm run build`
Expected: sukses.

Jalankan aplikasi dan buka satu tour rental berstatus `confirmed` dengan invoice draft. Periksa dengan mata: banner muncul bila `unit_price > 0` tanpa baris bernominal; banner hilang setelah baris diisi; blok "Harga / pax" tidak ada; total = jumlah baris. **DB lokal tidak punya tour rental** (diverifikasi 30 Jul 2026), jadi buat satu dulu lewat UI atau tinker:

```bash
php artisan tinker --execute="
\$t = App\Models\Tour::create(['type'=>'rental','status'=>'confirmed','pax'=>10,'start_date'=>'2026-08-01','end_date'=>'2026-08-05','title'=>'Uji Rental']);
echo 'tour id: '.\$t->id.PHP_EOL;
"
```

- [ ] **Step 8: Commit**

```bash
git add resources/js/Components/Tours/InvoicesPanel.vue
git commit -m "feat: panel invoice rental menampilkan rincian tagihan, bukan harga/pax

Untuk jenis dengan totalComposition line_items, blok Harga / pax tidak
dirender dan bagian baris bernominal naik jadi utama dengan judul Rincian
Tagihan. proformaTotal() ikut menghormati komposisi supaya angka di layar
tidak berbeda dari yang backend simpan.

Banner peringatan (SS5c) untuk invoice rental yang nilainya masih di
unit_price: menyebut nominal lamanya dan mengatakan total akan terbaca Rp 0
selama rincian belum diisi. Hilang sendiri begitu ada baris bernominal.
Banner ini juga menutup kasus invoice rental yang dibuat setelah query
hitung dijalankan tapi sebelum fase ini dirilis.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

### Task 9: PDF customer — tanggal baris bernominal + baris `Price` kosong

**Files:**
- Modify: `resources/views/invoice.blade.php:231-247` (baris Price), `:249-264` (baris bernominal)
- Test: `tests/Feature/Invoice/CustomerPdfUnitLabelTest.php` (tambah method)

**Interfaces:**
- Consumes: `description_lines[].date` (Task 7)

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan ke `tests/Feature/Invoice/CustomerPdfUnitLabelTest.php`:

```php
    public function test_baris_bernominal_mencetak_tanggal(): void
    {
        $invoice = $this->makeInvoice($this->makeTour('rental', ['pax' => 10]), 0, [
            'description_lines' => [
                ['label' => 'Avanza', 'date' => '2026-07-22', 'detail' => 'Sewa harian', 'amount' => 800_000],
            ],
        ]);

        $html = $this->renderInvoice($invoice, unitPrice: 0.0, pax: 10, lines: [
            ['label' => 'Avanza', 'date' => '2026-07-22', 'detail' => 'Sewa harian', 'amount' => 800_000],
        ]);

        $this->assertStringContainsString('2026-07-22', $html);
    }

    public function test_baris_price_tidak_dicetak_saat_unit_price_nol(): void
    {
        // Rental line_items: unit_price 0 tidak boleh meninggalkan baris
        // "Price :" kosong bernominal 0 di dokumen ke customer.
        $invoice = $this->makeInvoice($this->makeTour('rental', ['pax' => 10]), 0, [
            'description_lines' => [
                ['label' => 'Avanza', 'date' => '2026-07-22', 'detail' => 'Sewa harian', 'amount' => 800_000],
            ],
        ]);

        $html = $this->renderInvoice($invoice, unitPrice: 0.0, pax: 10, lines: [
            ['label' => 'Avanza', 'date' => '2026-07-22', 'detail' => 'Sewa harian', 'amount' => 800_000],
        ]);

        $this->assertStringNotContainsString('>Price<', $html);
    }
```

Helper `renderInvoice()` **sudah ada** di berkas ini sejak Task 1 — pakai apa adanya, jangan definisikan ulang. Tandanya:

```php
    private function renderInvoice(
        \App\Models\Invoice $invoice,
        float $unitPrice,
        int $pax,
        array $lines = [],
        array $extra = [],
    ): string
```

Kedua test baru di atas tidak memakai parameter `$extra` — nilai `billingUnit` yang sengaja salah hanya relevan untuk test Task 1.

- [ ] **Step 2: Jalankan test, pastikan GAGAL**

Run: `php artisan test --filter=CustomerPdfUnitLabelTest`
Expected: FAIL pada kedua test baru — tanggal belum dicetak, dan baris `Price` masih selalu dirender.

- [ ] **Step 3: Baris `Price` hanya saat ada harga satuan**

Di `resources/views/invoice.blade.php`, bungkus seluruh blok baris 232-247 dengan `@if($unitPrice > 0)`:

```blade
                {{-- Baris harga proforma — dilewati untuk jenis yang total-nya
                     tersusun dari baris bernominal (unit_price = 0), supaya
                     tidak ada baris "Price :" kosong bernominal 0. --}}
                @if($unitPrice > 0)
                <tr>
                    <td class="dcell">
                        <table class="kv">
                            <tr>
                                <td class="k">Price</td>
                                <td class="s">:</td>
                                <td>
                                    {{ $fmt($unitPrice) }}@if($pax > 0) &times; {{ $pax }} pax @endif
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td class="acell">{{ $fmt($invoice->total - collect($lines)->sum('amount')) }}</td>
                </tr>
                @endif
```

Perhatikan `@if($unitPrice > 0)` yang lama di dalam `<td>` sudah tidak perlu — kondisinya kini di luar.

- [ ] **Step 4: Baris bernominal mencetak tanggal**

Ganti blok baris 252-263:

```blade
                <tr>
                    <td class="dcell">
                        <table class="kv">
                            <tr>
                                <td class="k">{{ trim($ln['label'] ?? '') ?: 'Additional' }}</td>
                                <td class="s">:</td>
                                <td>
                                    @if(!empty($ln['date'])){{ $ln['date'] }}@if(!empty($ln['detail'])) &middot; @endif @endif{{ $ln['detail'] ?? '' }}
                                </td>
                            </tr>
                        </table>
                    </td>
                    <td class="acell">{{ $fmt($ln['amount']) }}</td>
                </tr>
```

- [ ] **Step 5: Jalankan test, pastikan LULUS**

Run: `php artisan test --filter=CustomerPdfUnitLabelTest`
Expected: PASS, 5 test.

- [ ] **Step 6: Test penuh**

Run: `php artisan test`
Expected: PASS semua.

- [ ] **Step 7: Periksa PDF sungguhan dengan mata**

Test HTML tidak membuktikan tata letak mPDF. Buka PDF invoice rental yang punya baris bernominal lewat aplikasi dan pastikan: tanggal terbaca, tidak ada baris `Price` kosong, kolom nominal lurus, total di kaki dokumen cocok dengan jumlah baris.

- [ ] **Step 8: Commit**

```bash
git add resources/views/invoice.blade.php tests/Feature/Invoice/CustomerPdfUnitLabelTest.php
git commit -m "feat: PDF customer cetak tanggal baris bernominal, sembunyikan Price kosong

Baris bernominal kini mencetak tanggalnya - rincian rental (Avanza 22 Jul,
Innova 25 Jul) sampai utuh ke customer.

Baris 'Price :' dilewati sepenuhnya saat unit_price = 0, supaya invoice
rental line_items tidak memuat baris kosong bernominal 0.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

## Penyelesaian

- [ ] **Test penuh terakhir:** `php artisan test` — semua hijau.
- [ ] **Build:** `npm run build` — sukses.
- [ ] **Tidak ada sisa peta jenis di frontend:** `grep -rn "tour.type ===" resources/js/Components/Tours/InvoicesPanel.vue resources/js/Components/Tours/CostingPanel.vue` → kosong.
- [ ] **Perbarui dokumen desain lama** bila §8.3 atau §10.4 kini menyesatkan: `docs/logika-pembuatan-invoice/08-perbedaan-per-tipe.md` dan `docs/logika-pembuatan-invoice/10-temuan.md` menyebut aturan profit dan label satuan yang berubah di rencana ini. Baca keduanya, perbarui yang sudah tidak benar, jangan menulis ulang yang masih berlaku.
- [ ] **Promosi:** merge branch ke `dev` untuk pengujian. `dev` → `main` lewat Pull Request dengan persetujuan eksplisit — jangan merge ke `main` sendiri.
- [ ] **Sebelum Fase 3 sampai production:** pastikan Prasyarat Rilis Fase 3 sudah dijalankan di database production dan daftar koreksi manual sudah diserahkan ke sales.
- [ ] **Deploy production manual:** pull + migrate + build di server. Repo ini tidak punya `.github/workflows`, jadi merge ke `main` tidak men-deploy apa pun dengan sendirinya.

## Catatan urutan

Ketiga fase bisa dirilis sendiri, tapi urutannya tidak bebas:

- **Fase 1 harus mendarat sebelum promosi `dev` → `main` berikutnya.** `dev` sekarang memuat label satuan salah yang belum pernah sampai production; promosi tanpa Fase 1 akan merilisnya.
- **Fase 2 tidak bergantung pada Fase 1** — boleh dikerjakan paralel bila perlu, tapi keduanya menyentuh `InvoicesPanel.vue` dan `InvoiceController.php`, jadi berurutan lebih murah daripada menyelesaikan konflik.
- **Fase 3 bergantung pada Fase 2** (prop `salesLine` dan `FakeSalesLineRule` dibuat di sana).
