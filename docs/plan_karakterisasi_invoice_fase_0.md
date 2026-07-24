# Fase 0 — Characterization Test Alur Invoice: Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mengunci perilaku alur invoice yang berjalan **sekarang** untuk ketujuh jenis penjualan dengan test otomatis, sebelum satu baris pun kode produksi disentuh pada Fase 1–5.

**Architecture:** Murni penambahan test. Tidak ada kode produksi yang dibuat, diubah, atau dipindah. Semua test memakai SQLite in-memory lewat `RefreshDatabase`, membuat data langsung dengan `Model::create()` mengikuti pola `tests/Feature/Finance/InvoiceNumberingTest.php` yang sudah ada.

**Tech Stack:** Laravel 13, PHPUnit, SQLite in-memory, Inertia.

**Spec:** [`docs/design_pemisahan_invoice_per_jenis_penjualan.md`](design_pemisahan_invoice_per_jenis_penjualan.md) — Fase 0 pada §4, dan jaminan §7.4.

---

## Temuan dari verifikasi empiris saat plan ini ditulis

Tiga asumsi diuji langsung ke database SQLite sebelum plan ditulis. Dua di antaranya keliru dan sudah dikoreksi di dalam test:

| Asumsi awal | Kenyataan | Akibat |
|---|---|---|
| `tours.pax` boleh `null` | **NOT NULL** | Cabang `?? $this->pax ?? 1` pada `syncProformaTotal()` tidak pernah tercapai lewat jalur normal — cabang mati |
| `total_idr` non-IDR bernilai `null` | **`0.00`** | Dokumen desain menyebutnya "dibiarkan kosong"; kenyataannya kolom punya default 0. Efek praktisnya sama, tetapi penulisan test harus mengikuti kenyataan |
| `tours.pax = 0` menghasilkan total 0 | total tetap penuh, `invoice.pax` jadi `1` | `max($pax, 1)` menutupinya — sudah sesuai dugaan |

Temuan kedua perlu dibawa kembali ke dokumen desain agar §3.3 dan §6 memakai kata yang tepat. Itu perubahan dokumen, bukan kode — dikerjakan terpisah dari Fase 0.

---

## Perbedaan penting dari TDD biasa

**Characterization test LULUS sejak pertama dijalankan.** Tidak ada fase RED.

Test ini merekam perilaku yang sudah ada, bukan mendorong perilaku baru. Karena itu langkahnya adalah: tulis test → jalankan → **harus langsung PASS** → commit.

Bila sebuah test **gagal** saat pertama dijalankan, itu bukan berarti kode produksi perlu diperbaiki. Artinya salah satu dari dua hal:

1. Test-nya salah menebak perilaku — perbaiki test-nya agar cocok dengan kenyataan
2. Ada bug nyata yang selama ini tidak terlihat — **berhenti, laporkan, jangan perbaiki kodenya di Fase 0**

Fase 0 tidak boleh mengubah perilaku produksi apa pun, termasuk memperbaiki bug yang ditemukannya. Bug dicatat sebagai temuan, ditangani di fase tersendiri.

---

## Global Constraints

- Semua path relatif terhadap `/Users/marchelinoraco/Documents/2026/erp_wm`.
- Perintah test dijalankan dari root repo: `php artisan test`.
- **Fase 0 TIDAK BOLEH menyentuh kode produksi.** Tidak ada berkas di `app/`, `routes/`, `resources/`, atau `database/migrations/` yang boleh dibuat, diubah, atau dihapus. Bila sebuah test terasa mustahil ditulis tanpa mengubah kode produksi, hentikan task itu dan laporkan.
- Komentar kode dan pesan assertion ditulis dalam **Bahasa Indonesia**, mengikuti gaya test yang sudah ada.
- erp_wm hanya punya `UserFactory`. Tour, Invoice, Product, dan Supplier dibuat langsung lewat `Model::create()` — jangan membuat factory baru.
- Setiap commit memakai prefiks Conventional Commits dan diakhiri baris:
  `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`
- Berkas test wajib berakhiran `Test.php`, kalau tidak PHPUnit tidak akan memindainya.
- Rute invoice berada di grup middleware `role:admin,sales` — setiap permintaan HTTP dalam test wajib memakai `actingAs()` dengan pengguna ber-role `sales` atau `admin`.

## Struktur Berkas

| Berkas | Tanggung jawab |
|---|---|
| `tests/Support/CreatesSalesFixtures.php` | Trait pembuat data uji bersama: pengguna sales, tour per jenis, invoice draft |
| `tests/Feature/Invoice/ProformaTotalCharacterizationTest.php` | Rumus `total = unit_price × pax` untuk ketujuh jenis |
| `tests/Feature/Invoice/PaxSourceCharacterizationTest.php` | Dari mana `pax` diambil dan urutan fallback-nya |
| `tests/Feature/Invoice/NumberingCharacterizationTest.php` | Kode tipe pada nomor invoice per jenis penjualan |
| `tests/Feature/Invoice/StageGateCharacterizationTest.php` | Gerbang: satu invoice per tour, patokan, persetujuan |
| `tests/Feature/Invoice/ApprovedInvoiceFrozenTest.php` | **Jaminan §7.4** — invoice disetujui tidak pernah dihitung ulang |
| `tests/Feature/Invoice/CurrencyCharacterizationTest.php` | Kapan `total_idr` terisi dan kapan dibiarkan kosong |
| `tests/Feature/Invoice/ApprovalSideEffectsTest.php` | `finance_number` dan Bill draft otomatis |

---

### Task 1: Trait fixture bersama + rumus total untuk ketujuh jenis

**Files:**
- Create: `tests/Support/CreatesSalesFixtures.php`
- Create: `tests/Feature/Invoice/ProformaTotalCharacterizationTest.php`

**Interfaces:**
- Consumes: model `Tour`, `Invoice`, `User` yang sudah ada.
- Produces: trait `Tests\Support\CreatesSalesFixtures` dengan method `salesUser(): User`, `makeTour(string $type, array $overrides = []): Tour`, `makeInvoice(Tour $tour, float $unitPrice, array $overrides = []): Invoice`, dan konstanta `SALES_TYPES` berisi ketujuh jenis. Task 2–7 memakai trait ini.

Kolom minimal untuk tiap model sudah diverifikasi empiris — `Supplier` butuh `name`; `Product` butuh `name`, `type`, `cost`, `sell`; `Tour` butuh `type`; `Invoice` butuh `tour_id`, `number`, `date`.

- [ ] **Step 1: Buat trait fixture**

Buat `tests/Support/CreatesSalesFixtures.php`:

```php
<?php

namespace Tests\Support;

use App\Models\Invoice;
use App\Models\Tour;
use App\Models\User;

/**
 * Pembuat data uji bersama untuk karakterisasi alur invoice.
 *
 * erp_wm hanya punya UserFactory, jadi tour dan invoice dibuat langsung lewat
 * Model::create() — mengikuti pola tests/Feature/Finance/InvoiceNumberingTest.php.
 */
trait CreatesSalesFixtures
{
    /** Ketujuh jenis penjualan seperti tersimpan di kolom tours.type. */
    public const SALES_TYPES = ['tour', 'hotel', 'guide', 'rental', 'mice', 'document', 'ticketing'];

    private int $userCounter = 0;

    /** Pengguna ber-role sales — seluruh rute invoice memakai middleware role:admin,sales. */
    protected function salesUser(): User
    {
        $this->userCounter++;

        return User::create([
            'name'     => 'Sales Uji ' . $this->userCounter,
            'email'    => 'sales' . $this->userCounter . '@test.local',
            'password' => bcrypt('password'),
            'role'     => 'sales',
        ]);
    }

    /**
     * Tour untuk satu jenis penjualan. Status confirmed karena panel invoice
     * hanya muncul pada status itu.
     */
    protected function makeTour(string $type, array $overrides = []): Tour
    {
        return Tour::create(array_replace([
            'type'       => $type,
            'status'     => 'confirmed',
            'pax'        => 10,
            'start_date' => '2026-08-01',
            'end_date'   => '2026-08-05',
        ], $overrides));
    }

    /** Invoice draft dengan harga proforma sudah terisi dan total tersinkron. */
    protected function makeInvoice(Tour $tour, float $unitPrice, array $overrides = []): Invoice
    {
        $invoice = Invoice::create(array_replace([
            'tour_id'    => $tour->id,
            'number'     => Invoice::nextNumber($tour),
            'date'       => now()->toDateString(),
            'currency'   => 'IDR',
            'unit_price' => $unitPrice,
            'status'     => 'draft',
        ], $overrides));

        $invoice->syncProformaTotal();

        return $invoice->fresh();
    }
}
```

- [ ] **Step 2: Tulis test rumus total**

Buat `tests/Feature/Invoice/ProformaTotalCharacterizationTest.php`:

```php
<?php

namespace Tests\Feature\Invoice;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * Mengunci rumus penagihan yang berlaku SEKARANG: total = unit_price × pax,
 * sama untuk ketujuh jenis penjualan tanpa kecuali.
 *
 * Fase 1-3 akan mengganti rumus ini dengan aturan per jenis. Test di berkas ini
 * SENGAJA akan gagal saat itu — dan kegagalannya adalah buktinya bahwa
 * perubahan memang mengenai sasaran, bukan tanda kerusakan.
 */
class ProformaTotalCharacterizationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    public function test_total_adalah_unit_price_kali_pax_untuk_ketujuh_jenis(): void
    {
        foreach (self::SALES_TYPES as $type) {
            $tour    = $this->makeTour($type, ['pax' => 10]);
            $invoice = $this->makeInvoice($tour, 500_000);

            $this->assertEquals(
                5_000_000,
                $invoice->total,
                "Jenis {$type}: total seharusnya 500.000 × 10 pax = 5.000.000"
            );
        }
    }

    public function test_jumlah_pax_berbeda_menghasilkan_total_berbeda_di_semua_jenis(): void
    {
        foreach (self::SALES_TYPES as $type) {
            $tour    = $this->makeTour($type, ['pax' => 3]);
            $invoice = $this->makeInvoice($tour, 1_000_000);

            $this->assertEquals(
                3_000_000,
                $invoice->total,
                "Jenis {$type}: pax ikut mengali meskipun jenis ini tidak ditagih per orang"
            );
        }
    }

    public function test_unit_price_nol_menghasilkan_total_nol(): void
    {
        $tour    = $this->makeTour('guide', ['pax' => 10]);
        $invoice = $this->makeInvoice($tour, 0);

        $this->assertEquals(0, $invoice->total);
    }

    public function test_pax_ikut_disalin_ke_invoice_saat_total_disinkronkan(): void
    {
        // syncProformaTotal() menyimpan pax yang dipakai menghitung ke invoice,
        // supaya PDF dan perhitungan tidak pernah memakai angka berbeda.
        $tour    = $this->makeTour('tour', ['pax' => 7]);
        $invoice = $this->makeInvoice($tour, 100_000);

        $this->assertEquals(7, $invoice->pax);
        $this->assertEquals(700_000, $invoice->total);
    }
}
```

- [ ] **Step 3: Jalankan — harus langsung LULUS**

Run: `php artisan test --filter=ProformaTotalCharacterizationTest`
Expected: PASS, 4 test.

Bila ada yang GAGAL: jangan ubah kode produksi. Baca pesan assertion, perbaiki ekspektasi test agar cocok dengan perilaku nyata, lalu catat selisihnya sebagai temuan di laporan.

- [ ] **Step 4: Jalankan seluruh suite untuk memastikan tidak ada regresi**

Run: `php artisan test`
Expected: seluruh test lama tetap lulus, ditambah 4 test baru.

- [ ] **Step 5: Commit**

```bash
git add tests/Support/CreatesSalesFixtures.php tests/Feature/Invoice/ProformaTotalCharacterizationTest.php
git commit -m "test: kunci rumus total invoice yang berlaku sekarang untuk 7 jenis penjualan

Characterization test — merekam perilaku yang ada, bukan mengubahnya.
total = unit_price × pax berlaku sama untuk ketujuh jenis, termasuk jenis
yang sebenarnya tidak ditagih per orang. Fase 1-3 akan menggantinya dengan
aturan per jenis; kegagalan test ini saat itu adalah bukti perubahan
mengenai sasaran.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 2: Sumber `pax` dan urutan fallback-nya

**Files:**
- Create: `tests/Feature/Invoice/PaxSourceCharacterizationTest.php`

**Interfaces:**
- Consumes: trait `Tests\Support\CreatesSalesFixtures` (Task 1).
- Produces: tidak ada yang dipakai task lain.

Ini mengunci akar masalah yang akan diperbaiki: `pax` dibaca dari **tour**, bukan invoice, sehingga tagihan bergerak mengikuti data tour.

- [ ] **Step 1: Tulis test**

Buat `tests/Feature/Invoice/PaxSourceCharacterizationTest.php`:

```php
<?php

namespace Tests\Feature\Invoice;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * Mengunci dari mana pengali tagihan diambil sekarang:
 *
 *     $pax = (int) ($this->tour?->pax ?? $this->pax ?? 1);
 *
 * Tour menang lebih dulu. Inilah sebab invoice untuk jasa guide ikut dikali
 * jumlah rombongan — akar masalah yang diperbaiki Fase 3.
 */
class PaxSourceCharacterizationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    public function test_pax_tour_menang_atas_pax_invoice(): void
    {
        $tour = $this->makeTour('guide', ['pax' => 20]);

        // Invoice sengaja diberi pax berbeda — nilainya harus DIABAIKAN.
        $invoice = $this->makeInvoice($tour, 100_000, ['pax' => 3]);

        $this->assertEquals(
            2_000_000,
            $invoice->total,
            'Pengali diambil dari tour.pax (20), bukan dari invoice.pax (3)'
        );
        $this->assertEquals(20, $invoice->pax, 'invoice.pax ditimpa nilai dari tour');
    }

    public function test_mengubah_pax_tour_menggeser_total_invoice_draft(): void
    {
        $tour    = $this->makeTour('guide', ['pax' => 10]);
        $invoice = $this->makeInvoice($tour, 100_000);

        $this->assertEquals(1_000_000, $invoice->total);

        // Orang lain mengubah ukuran rombongan di panel Header tour.
        $tour->update(['pax' => 12]);

        $invoice->refresh();
        $invoice->syncProformaTotal();

        $this->assertEquals(
            1_200_000,
            $invoice->fresh()->total,
            'Total invoice draft mengikuti perubahan pax tour pada sinkronisasi berikutnya'
        );
    }

    public function test_pax_tour_nol_diperlakukan_sebagai_satu(): void
    {
        // max($pax, 1) menutupi nilai 0 agar total tidak menjadi 0.
        $tour    = $this->makeTour('document', ['pax' => 0]);
        $invoice = $this->makeInvoice($tour, 750_000);

        $this->assertEquals(750_000, $invoice->total);
        $this->assertEquals(1, $invoice->pax);
    }

    public function test_pax_tour_tidak_boleh_null_di_database(): void
    {
        // Sudah diverifikasi empiris: kolom tours.pax bersifat NOT NULL, jadi
        // cabang `?? $this->pax ?? 1` pada syncProformaTotal() tidak pernah
        // tercapai lewat jalur normal. Dicatat di sini supaya Fase 1-3 tahu
        // cabang itu memang mati, bukan terlewat diuji.
        $this->expectException(\Illuminate\Database\QueryException::class);

        $this->makeTour('ticketing', ['pax' => null]);
    }
}
```

- [ ] **Step 2: Jalankan — harus langsung LULUS**

Run: `php artisan test --filter=PaxSourceCharacterizationTest`
Expected: PASS, 4 test.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Invoice/PaxSourceCharacterizationTest.php
git commit -m "test: kunci sumber pengali tagihan dan urutan fallback-nya

Merekam bahwa tour.pax menang atas invoice.pax, dan bahwa mengubah pax tour
menggeser total invoice draft pada sinkronisasi berikutnya. Ini akar masalah
yang diperbaiki Fase 3 lewat kolom billing_quantities.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3: Kode tipe pada nomor invoice per jenis penjualan

**Files:**
- Create: `tests/Feature/Invoice/NumberingCharacterizationTest.php`

**Interfaces:**
- Consumes: trait `Tests\Support\CreatesSalesFixtures` (Task 1).
- Produces: tidak ada yang dipakai task lain.

Penomoran **tidak berubah** pada Fase 1–5. Test ini memastikan refactor tidak diam-diam menggesernya.

- [ ] **Step 1: Tulis test**

Buat `tests/Feature/Invoice/NumberingCharacterizationTest.php`:

```php
<?php

namespace Tests\Feature\Invoice;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * Mengunci kode tipe pada nomor invoice: INV-<tahun>-<kode>-NNNN.
 *
 * Penomoran TIDAK termasuk yang diubah Fase 1-5. Test ini adalah penjaga agar
 * refactor tidak menggesernya tanpa sengaja.
 */
class NumberingCharacterizationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    /** Kode angka per jenis penjualan, dari Tour::TYPE_CODES & resolveTypeCode(). */
    private const EXPECTED_CODES = [
        'rental'    => '13',
        'guide'     => '14',
        'mice'      => '15',
        'hotel'     => '16',
        'document'  => '17',
        'ticketing' => '18',
    ];

    public function test_kode_tipe_muncul_pada_nomor_invoice(): void
    {
        $year = now()->year;

        foreach (self::EXPECTED_CODES as $type => $code) {
            $tour   = $this->makeTour($type);
            $number = Invoice::nextNumber($tour);

            $this->assertStringStartsWith(
                "INV-{$year}-{$code}-",
                $number,
                "Jenis {$type} seharusnya memakai kode {$code}"
            );
        }
    }

    public function test_tour_dibedakan_menurut_arah(): void
    {
        $year = now()->year;

        $inbound  = $this->makeTour('tour', ['tour_direction' => 'inbound']);
        $outbound = $this->makeTour('tour', ['tour_direction' => 'outbound']);

        $this->assertStringStartsWith("INV-{$year}-11-", Invoice::nextNumber($inbound));
        $this->assertStringStartsWith("INV-{$year}-12-", Invoice::nextNumber($outbound));
    }

    public function test_arah_kosong_jatuh_ke_inbound(): void
    {
        $year = now()->year;
        $tour = $this->makeTour('tour', ['tour_direction' => null]);

        $this->assertStringStartsWith("INV-{$year}-11-", Invoice::nextNumber($tour));
    }

    public function test_urutan_berjalan_per_jenis_bukan_global(): void
    {
        $guide = $this->makeTour('guide');
        $hotel = $this->makeTour('hotel');

        $this->makeInvoice($guide, 100_000);

        // Hotel belum punya invoice sama sekali, jadi tetap mulai dari 0001.
        $this->assertStringEndsWith('0001', Invoice::nextNumber($hotel));

        // Guide sudah punya satu, jadi lanjut ke 0002.
        $this->assertStringEndsWith('0002', Invoice::nextNumber($guide));
    }
}
```

- [ ] **Step 2: Jalankan — harus langsung LULUS**

Run: `php artisan test --filter=NumberingCharacterizationTest`
Expected: PASS, 4 test.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Invoice/NumberingCharacterizationTest.php
git commit -m "test: kunci kode tipe pada nomor invoice per jenis penjualan

Penomoran tidak termasuk yang diubah Fase 1-5; test ini penjaga agar refactor
tidak menggesernya tanpa sengaja. Termasuk pembedaan tour inbound (11) dan
outbound (12), serta urutan yang berjalan per jenis bukan global.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 4: Gerbang tahapan — satu invoice per tour, patokan, persetujuan

**Files:**
- Create: `tests/Feature/Invoice/StageGateCharacterizationTest.php`

**Interfaces:**
- Consumes: trait `Tests\Support\CreatesSalesFixtures` (Task 1).
- Produces: tidak ada yang dipakai task lain.

- [ ] **Step 1: Tulis test**

Buat `tests/Feature/Invoice/StageGateCharacterizationTest.php`:

```php
<?php

namespace Tests\Feature\Invoice;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * Mengunci gerbang tiga tahap: baseline → detail → approved.
 *
 * Seluruh gerbang ini TIDAK berubah pada Fase 1-5 (spec §3.4). Test ini
 * memastikan refactor rumus tidak merembet ke aturan alurnya.
 */
class StageGateCharacterizationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    public function test_satu_tour_hanya_boleh_punya_satu_invoice(): void
    {
        $tour = $this->makeTour('tour');
        $user = $this->salesUser();

        $this->actingAs($user)
            ->post(route('invoices.store', $tour))
            ->assertRedirect();

        $this->assertDatabaseCount('invoices', 1);

        $this->actingAs($user)
            ->post(route('invoices.store', $tour))
            ->assertSessionHasErrors('invoice');

        $this->assertDatabaseCount('invoices', 1);
    }

    public function test_invoice_baru_dimulai_pada_tahap_baseline(): void
    {
        $tour = $this->makeTour('hotel');

        $this->actingAs($this->salesUser())
            ->post(route('invoices.store', $tour))
            ->assertRedirect();

        $invoice = $tour->invoices()->firstOrFail();

        $this->assertSame('draft', $invoice->status);
        $this->assertSame('baseline', $invoice->stage);
        $this->assertNull($invoice->approved_at);
        $this->assertEquals(0, $invoice->baseline_total);
    }

    public function test_patokan_ditolak_bila_total_masih_nol(): void
    {
        $tour    = $this->makeTour('guide');
        $invoice = $this->makeInvoice($tour, 0);

        $this->actingAs($this->salesUser())
            ->patch(route('invoices.baseline', $invoice))
            ->assertSessionHasErrors('invoice');

        $this->assertEquals(0, $invoice->fresh()->baseline_total);
    }

    public function test_patokan_terkunci_memindahkan_tahap_ke_detail(): void
    {
        $tour    = $this->makeTour('guide');
        $invoice = $this->makeInvoice($tour, 500_000);

        $this->actingAs($this->salesUser())
            ->patch(route('invoices.baseline', $invoice))
            ->assertRedirect();

        $invoice->refresh();

        $this->assertEquals(5_000_000, $invoice->baseline_total);
        $this->assertSame('detail', $invoice->stage);
    }

    public function test_persetujuan_ditolak_bila_patokan_belum_terkunci(): void
    {
        $tour    = $this->makeTour('mice');
        $invoice = $this->makeInvoice($tour, 500_000);

        $this->actingAs($this->salesUser())
            ->post(route('invoices.approve', $invoice))
            ->assertSessionHasErrors('invoice');

        $this->assertNull($invoice->fresh()->approved_at);
    }

    public function test_persetujuan_non_idr_wajib_menyertakan_kurs(): void
    {
        $tour    = $this->makeTour('tour');
        $invoice = $this->makeInvoice($tour, 1_000, ['currency' => 'USD']);

        $invoice->update(['baseline_total' => $invoice->total]);

        $this->actingAs($this->salesUser())
            ->post(route('invoices.approve', $invoice))
            ->assertSessionHasErrors('exchange_rate');

        $this->assertNull($invoice->fresh()->approved_at);
    }
}
```

- [ ] **Step 2: Jalankan — harus langsung LULUS**

Run: `php artisan test --filter=StageGateCharacterizationTest`
Expected: PASS, 6 test.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Invoice/StageGateCharacterizationTest.php
git commit -m "test: kunci gerbang tiga tahap alur invoice

Satu invoice per tour, patokan menolak total nol, persetujuan menolak patokan
kosong, dan non-IDR wajib menyertakan kurs. Seluruh gerbang ini tidak berubah
pada Fase 1-5; test ini memastikan refactor rumus tidak merembet ke sana.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 5: Jaminan §7.4 — invoice yang sudah disetujui tidak pernah dihitung ulang

**Files:**
- Create: `tests/Feature/Invoice/ApprovedInvoiceFrozenTest.php`

**Interfaces:**
- Consumes: trait `Tests\Support\CreatesSalesFixtures` (Task 1).
- Produces: tidak ada yang dipakai task lain.

**Ini test paling penting di seluruh Fase 0.** Spec §7.4 menjadikannya gerbang wajib lolos sebelum Fase 2 boleh menyentuh production. Ia membuktikan bahwa data keuangan yang sudah masuk pembukuan tidak dapat berubah, apa pun yang dilakukan Fase 1–5 pada rumus.

- [ ] **Step 1: Tulis test**

Buat `tests/Feature/Invoice/ApprovedInvoiceFrozenTest.php`:

```php
<?php

namespace Tests\Feature\Invoice;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * JAMINAN INTI (spec §7.4): invoice yang sudah disetujui tidak akan pernah
 * dihitung ulang, apa pun yang berubah pada rumus atau pada data tour.
 *
 * syncProformaTotal() dipanggil di tiga tempat — updateProforma(), lockBaseline(),
 * dan approve() — dan ketiganya didahului ensureNotApproved(). Test ini menutup
 * ketiga jalur itu sekaligus.
 *
 * Test ini WAJIB tetap hijau di setiap fase. Bila ia merah, hentikan pekerjaan:
 * artinya data keuangan yang sudah masuk pembukuan bisa berubah.
 */
class ApprovedInvoiceFrozenTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    private function approvedInvoice(): Invoice
    {
        $tour    = $this->makeTour('tour', ['pax' => 10]);
        $invoice = $this->makeInvoice($tour, 500_000);

        $invoice->update(['baseline_total' => $invoice->total]);

        $this->actingAs($this->salesUser())
            ->post(route('invoices.approve', $invoice))
            ->assertRedirect();

        $invoice->refresh();

        $this->assertNotNull($invoice->approved_at, 'Prasyarat: invoice harus benar-benar tersetujui');
        $this->assertEquals(5_000_000, $invoice->total);

        return $invoice;
    }

    public function test_perubahan_pax_tour_tidak_menggeser_total_invoice_yang_disetujui(): void
    {
        $invoice = $this->approvedInvoice();

        // Ubah pax tour secara drastis SETELAH invoice disetujui.
        $invoice->tour->update(['pax' => 99]);

        // Penting: mengubah pax saja tidak memanggil syncProformaTotal(), jadi
        // memeriksa total di sini tanpa berbuat apa-apa akan lolos meski
        // penjaganya dihapus. Test harus benar-benar MENCOBA menyinkronkan
        // lewat jalur yang dijaga, lalu membuktikan percobaan itu ditolak.
        $this->actingAs($this->salesUser())
            ->patch(route('invoices.baseline', $invoice))
            ->assertSessionHasErrors('invoice');

        $this->assertEquals(
            5_000_000,
            $invoice->fresh()->total,
            'Total invoice yang sudah masuk Keuangan tidak boleh ikut berubah'
        );
        $this->assertEquals(
            10,
            $invoice->fresh()->pax,
            'pax invoice tetap 10 seperti saat disetujui, bukan 99 dari tour'
        );
    }

    public function test_ketiga_jalur_perhitungan_ulang_ditolak_setelah_disetujui(): void
    {
        $invoice = $this->approvedInvoice();
        $user    = $this->salesUser();

        // Jalur 1 — updateProforma()
        $this->actingAs($user)
            ->patch(route('invoices.proforma', $invoice), [
                'currency'   => 'IDR',
                'unit_price' => 1,
            ])
            ->assertSessionHasErrors('invoice');

        // Jalur 2 — lockBaseline()
        $this->actingAs($user)
            ->patch(route('invoices.baseline', $invoice))
            ->assertSessionHasErrors('invoice');

        // Jalur 3 — approve()
        $this->actingAs($user)
            ->post(route('invoices.approve', $invoice))
            ->assertSessionHasErrors('invoice');

        $this->assertEquals(5_000_000, $invoice->fresh()->total, 'Total tetap utuh setelah ketiga percobaan');
        $this->assertEquals(500_000, $invoice->fresh()->unit_price, 'unit_price tetap utuh');
    }

    public function test_invoice_yang_disetujui_tidak_bisa_dihapus(): void
    {
        $invoice = $this->approvedInvoice();

        $this->actingAs($this->salesUser())
            ->delete(route('invoices.destroy', $invoice))
            ->assertSessionHasErrors('invoice');

        // Invoice::find() menghormati SoftDeletingScope; $invoice->fresh() TIDAK
        // (ia memakai newQueryWithoutScopes), sehingga fresh() akan tetap
        // mengembalikan baris yang sudah ter-soft-delete dan asersinya jadi mati.
        $this->assertNotNull(
            Invoice::find($invoice->id),
            'Invoice yang sudah masuk Keuangan tidak boleh hilang'
        );
        $this->assertNull(
            $invoice->fresh()->deleted_at,
            'Invoice tidak boleh ter-soft-delete'
        );
    }

    public function test_item_tidak_bisa_diubah_setelah_invoice_disetujui(): void
    {
        $invoice = $this->approvedInvoice();

        $this->actingAs($this->salesUser())
            ->post(route('invoice-items.bulk', $invoice), [
                'items' => [['description' => 'Sisipan setelah disetujui', 'unit_cost' => 1, 'unit_sell' => 2]],
            ])
            ->assertSessionHasErrors('invoice');

        $this->assertCount(0, $invoice->fresh()->items);
    }
}
```

- [ ] **Step 2: Jalankan — harus langsung LULUS**

Run: `php artisan test --filter=ApprovedInvoiceFrozenTest`
Expected: PASS, 4 test.

Bila ada yang GAGAL, **hentikan seluruh pekerjaan dan laporkan**. Kegagalan di sini berarti data keuangan yang sudah masuk pembukuan dapat berubah — temuan yang jauh lebih penting daripada refactor ini.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Invoice/ApprovedInvoiceFrozenTest.php
git commit -m "test: jaminan invoice yang disetujui tidak pernah dihitung ulang

Gerbang wajib lolos dari spec §7.4 sebelum Fase 2 boleh menyentuh production.
Menutup ketiga jalur yang memanggil syncProformaTotal (updateProforma,
lockBaseline, approve) sekaligus membuktikan perubahan pax tour tidak
menggeser total invoice yang sudah masuk Keuangan.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 6: Perilaku mata uang — kapan `total_idr` terisi

**Files:**
- Create: `tests/Feature/Invoice/CurrencyCharacterizationTest.php`

**Interfaces:**
- Consumes: trait `Tests\Support\CreatesSalesFixtures` (Task 1).
- Produces: tidak ada yang dipakai task lain.

`syncProformaTotal()` menulis `total_idr` hanya untuk IDR. Fase 2 memindahkan perhitungan ini ke registry, jadi perilakunya harus terkunci lebih dulu.

- [ ] **Step 1: Tulis test**

Buat `tests/Feature/Invoice/CurrencyCharacterizationTest.php`:

```php
<?php

namespace Tests\Feature\Invoice;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * Mengunci kapan total_idr terisi.
 *
 * IDR → terisi langsung saat sinkronisasi. Non-IDR → sengaja dibiarkan kosong
 * sampai disetujui, supaya laporan IDR tidak terdistorsi kurs placeholder
 * sebelum kurs pasti diinput.
 */
class CurrencyCharacterizationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    public function test_idr_mengisi_total_idr_langsung(): void
    {
        $tour    = $this->makeTour('tour', ['pax' => 4]);
        $invoice = $this->makeInvoice($tour, 250_000);

        $this->assertEquals(1_000_000, $invoice->total);
        $this->assertEquals(1_000_000, $invoice->total_idr);
    }

    public function test_non_idr_membiarkan_total_idr_kosong_sebelum_disetujui(): void
    {
        $tour    = $this->makeTour('tour', ['pax' => 4]);
        $invoice = $this->makeInvoice($tour, 250, ['currency' => 'USD']);

        $this->assertEquals(1_000, $invoice->total);

        // CATATAN: nilainya 0.00, BUKAN null — sudah diverifikasi empiris.
        // Dokumen desain menyebutnya "dibiarkan kosong"; kenyataannya kolom
        // punya default 0. Efek praktisnya sama (laporan IDR tidak terdistorsi
        // kurs placeholder), tapi penulisan test harus mengikuti kenyataan.
        $this->assertEquals(
            0,
            $invoice->total_idr,
            'total_idr tetap 0 sampai kurs pasti diinput saat persetujuan'
        );
    }

    public function test_persetujuan_non_idr_mengisi_total_idr_dari_kurs(): void
    {
        $tour    = $this->makeTour('tour', ['pax' => 4]);
        $invoice = $this->makeInvoice($tour, 250, ['currency' => 'USD']);

        $invoice->update(['baseline_total' => $invoice->total]);

        $this->actingAs($this->salesUser())
            ->post(route('invoices.approve', $invoice), ['exchange_rate' => 16_000])
            ->assertRedirect();

        $invoice->refresh();

        $this->assertEquals(16_000, $invoice->exchange_rate);
        $this->assertEquals(16_000_000, $invoice->total_idr, '1.000 USD × 16.000');
    }

    public function test_persetujuan_idr_memakai_kurs_satu(): void
    {
        $tour    = $this->makeTour('hotel', ['pax' => 2]);
        $invoice = $this->makeInvoice($tour, 500_000);

        $invoice->update(['baseline_total' => $invoice->total]);

        $this->actingAs($this->salesUser())
            ->post(route('invoices.approve', $invoice))
            ->assertRedirect();

        $invoice->refresh();

        $this->assertEquals(1, $invoice->exchange_rate);
        $this->assertEquals(1_000_000, $invoice->total_idr);
    }
}
```

- [ ] **Step 2: Jalankan — harus langsung LULUS**

Run: `php artisan test --filter=CurrencyCharacterizationTest`
Expected: PASS, 4 test.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Invoice/CurrencyCharacterizationTest.php
git commit -m "test: kunci perilaku mata uang dan pengisian total_idr

IDR mengisi total_idr langsung; non-IDR sengaja dibiarkan kosong sampai kurs
pasti diinput saat persetujuan. Fase 2 memindahkan perhitungan ini ke registry,
jadi perilakunya dikunci lebih dulu.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 7: Efek samping persetujuan — nomor keuangan dan Bill draft

**Files:**
- Create: `tests/Feature/Invoice/ApprovalSideEffectsTest.php`

**Interfaces:**
- Consumes: trait `Tests\Support\CreatesSalesFixtures` (Task 1); model `Product`, `Supplier`, `Bill`, `InvoiceItem` yang sudah ada.
- Produces: tidak ada yang dipakai task lain.

Spec §3.4 menyatakan Keuangan tidak tersentuh. Test ini adalah buktinya — bila refactor merembet ke sini, test langsung merah.

- [ ] **Step 1: Tulis test**

Buat `tests/Feature/Invoice/ApprovalSideEffectsTest.php`:

```php
<?php

namespace Tests\Feature\Invoice;

use App\Models\Bill;
use App\Models\InvoiceItem;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * Mengunci apa yang terjadi saat invoice disetujui: nomor keuangan terbit dan
 * item bersupplier menjadi Bill draft bernominal 0.
 *
 * Spec §3.4 menyatakan Keuangan tidak tersentuh Fase 1-5. Test ini buktinya.
 */
class ApprovalSideEffectsTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    public function test_nomor_keuangan_terbit_saat_disetujui(): void
    {
        $tour    = $this->makeTour('tour');
        $invoice = $this->makeInvoice($tour, 500_000);

        $this->assertNull($invoice->finance_number, 'Sebelum disetujui belum punya nomor keuangan');

        $invoice->update(['baseline_total' => $invoice->total]);

        $this->actingAs($this->salesUser())
            ->post(route('invoices.approve', $invoice))
            ->assertRedirect();

        $invoice->refresh();

        $this->assertStringStartsWith('INV-' . now()->year . '-', $invoice->finance_number);
        $this->assertSame('sent', $invoice->status);
        $this->assertNotNull($invoice->approved_at);
    }

    public function test_nomor_keuangan_tidak_membedakan_jenis_penjualan(): void
    {
        // Berbeda dari `number` yang mengandung kode tipe, finance_number urut
        // global mengikuti urutan masuk Keuangan.
        $year = now()->year;

        foreach (['guide', 'hotel'] as $index => $type) {
            $tour    = $this->makeTour($type);
            $invoice = $this->makeInvoice($tour, 500_000);
            $invoice->update(['baseline_total' => $invoice->total]);

            $this->actingAs($this->salesUser())
                ->post(route('invoices.approve', $invoice))
                ->assertRedirect();

            $this->assertSame(
                sprintf('INV-%d-%04d', $year, $index + 1),
                $invoice->fresh()->finance_number
            );
        }
    }

    public function test_item_bersupplier_menjadi_bill_draft_bernominal_nol(): void
    {
        $supplier = Supplier::create(['name' => 'Hotel Mitra']);
        $product  = Product::create([
            'name'        => 'Kamar Deluxe',
            'type'        => 'hotel',
            'cost'        => 500_000,
            'sell'        => 750_000,
            'supplier_id' => $supplier->id,
        ]);

        $tour    = $this->makeTour('tour');
        $invoice = $this->makeInvoice($tour, 500_000);

        InvoiceItem::create([
            'invoice_id'   => $invoice->id,
            'product_id'   => $product->id,
            'product_type' => $product->type,
            'description'  => $product->name,
            'qty'          => 1,
            'nights'       => 1,
            'unit_cost'    => $product->cost,
            'unit_sell'    => $product->sell,
        ]);

        $invoice->update(['baseline_total' => $invoice->total]);

        $this->actingAs($this->salesUser())
            ->post(route('invoices.approve', $invoice))
            ->assertRedirect();

        $bill = Bill::where('tour_id', $tour->id)->firstOrFail();

        $this->assertEquals($supplier->id, $bill->supplier_id);
        $this->assertEquals(0, $bill->amount, 'Nominal sengaja 0 — akuntan yang mengisi');
        $this->assertSame('unpaid', $bill->status);
    }

    public function test_item_tanpa_supplier_tidak_membuat_bill(): void
    {
        $tour    = $this->makeTour('guide');
        $invoice = $this->makeInvoice($tour, 500_000);

        // Item manual hasil tempel massal — tidak punya product_id sama sekali.
        InvoiceItem::create([
            'invoice_id'  => $invoice->id,
            'description' => 'Item manual tanpa produk',
            'qty'         => 1,
            'nights'      => 1,
            'unit_cost'   => 100_000,
            'unit_sell'   => 150_000,
        ]);

        $invoice->update(['baseline_total' => $invoice->total]);

        $this->actingAs($this->salesUser())
            ->post(route('invoices.approve', $invoice))
            ->assertRedirect();

        $this->assertSame(0, Bill::where('tour_id', $tour->id)->count());
    }
}
```

- [ ] **Step 2: Jalankan — harus langsung LULUS**

Run: `php artisan test --filter=ApprovalSideEffectsTest`
Expected: PASS, 4 test.

- [ ] **Step 3: Jalankan seluruh suite**

Run: `php artisan test`
Expected: seluruh test lama tetap lulus, ditambah 30 test baru dari Fase 0 (Task 1: 4, Task 2: 4, Task 3: 4, Task 4: 6, Task 5: 4, Task 6: 4, Task 7: 4).

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/Invoice/ApprovalSideEffectsTest.php
git commit -m "test: kunci efek samping persetujuan invoice

Nomor keuangan terbit dan urut global tanpa membedakan jenis penjualan; item
bersupplier menjadi Bill draft bernominal 0; item manual tanpa produk dilewati.
Spec §3.4 menyatakan Keuangan tidak tersentuh Fase 1-5 — test ini buktinya.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Verifikasi Akhir Fase 0

```bash
php artisan test
php artisan test --filter=Characterization
php artisan test --filter=ApprovedInvoiceFrozenTest
```

Yang harus terbukti oleh test, bukan oleh keyakinan:

| Properti | Dibuktikan oleh |
|---|---|
| Rumus `total = unit_price × pax` berlaku sama untuk 7 jenis | `ProformaTotalCharacterizationTest` |
| Pengali diambil dari tour, bukan invoice | `PaxSourceCharacterizationTest::test_pax_tour_menang_atas_pax_invoice` |
| **Invoice disetujui tidak pernah dihitung ulang** | `ApprovedInvoiceFrozenTest` — gerbang §7.4 |
| Penomoran per jenis tidak bergeser | `NumberingCharacterizationTest` |
| Gerbang tiga tahap utuh | `StageGateCharacterizationTest` |
| `total_idr` hanya terisi untuk IDR sebelum persetujuan | `CurrencyCharacterizationTest` |
| Keuangan tidak tersentuh | `ApprovalSideEffectsTest` |

**Definisi selesai:** seluruh 30 test baru hijau, seluruh test lama tetap hijau, dan **nol berkas di luar `tests/` yang berubah**. Verifikasi terakhir dengan:

```bash
git diff --stat dev...HEAD -- ':!tests' ':!docs'
```

Expected: keluaran kosong. Bila ada berkas produksi yang berubah, Fase 0 belum selesai.

> Pekerjaan ini dijalankan di branch fitur yang bercabang dari `dev`, mengikuti alur branch repo (`main` = production, `dev` = testing, fitur selalu di branch sendiri). Buat branch-nya lebih dulu:
> ```bash
> git checkout dev && git pull origin dev
> git checkout -b test/karakterisasi-invoice
> ```

## Cakupan Spec

| Bagian spec | Task |
|---|---|
| §4 Fase 0 — characterization test ketujuh jenis | Task 1–7 |
| §7.4 — jaminan invoice disetujui tidak pernah dihitung ulang | Task 5 |
| §3.3 rumus lama yang akan diganti | Task 1, 2 |
| §3.4 yang TIDAK berubah (gerbang, Keuangan) | Task 4, 7 |
| §5 R1 backfill mempertahankan nilai | Task 1 menyediakan angka pembanding; verifikasi backfill sendiri ada di Fase 2 |

**Bagian spec yang sengaja belum dikerjakan di plan ini:** seluruh Fase 1–5 dan §7.1–7.3, §7.5–7.8 yang menyangkut eksekusi migrasi di production. Masing-masing mendapat plan sendiri.
