# Tata Letak PDF Invoice Hotel — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** PDF invoice hotel — kedua modenya — mengikuti dokumen acuan pemilik: `Date` berformat ringkas, `Total Pax` tampil tanpa ikut mengalikan, baris `Hotel / Room`, dan baris `Price` berpengali `x N room x N night` pada mode kamar.

**Architecture:** Tiga tata letak baris bernominal tidak muat di boolean, jadi `chargeLinesDateFirstInPdf()` diganti `chargeLineLayout()` bernilai `'default' | 'date_first' | 'hotel_room'`. Dua aturan baru (`showsTotalPaxInPdf()`, `usesCompactDateInPdf()`) menggantikan tebakan lama yang memakai komposisi total. Perangkaian teks dipusatkan di dua helper murni, dan seluruh keputusan tata letak diselesaikan sekali di `InvoiceController::invoiceViewData()` sehingga Blade hanya menampilkan.

**Tech Stack:** Laravel 11 + Inertia + Vue 3, PHPUnit, Blade + mPDF, Tailwind.

**Spec:** `docs/superpowers/specs/2026-08-12-tata-letak-pdf-invoice-hotel-design.md`

**Branch:** `feat/invoice-hotel-pdf-layout` (sudah dibuat dari `dev`)

## Global Constraints

- **Hanya invoice hotel yang berubah tampilannya.** Rental, tour, guide, MICE, document, ticketing: tata letak, format tanggal, dan angkanya wajib byte-identik dengan sebelum pekerjaan ini.
- **Tidak ada cara hitung yang berubah.** `harga × kamar × malam` dan `harga × pax` tetap apa adanya. Pekerjaan ini murni tampilan plus dua tempat penyimpanan teks.
- **`Total Pax` di mode kamar hanya keterangan** — tidak ikut mengalikan apa pun.
- **Kata `room` dan `night` ditulis tunggal berapa pun jumlahnya** (`x 1 room x 2 night`), mengikuti dokumen acuan, sama seperti `pax` yang sudah tidak dijamakkan.
- **Format tanggal ringkas:** sebulan sama `1-2 Aug 2026`; beda bulan `30 Aug - 2 Sep 2026`; beda tahun `30 Dec 2026 - 2 Jan 2027`; satu tanggal `1 Aug 2026`. Tanggal tanpa nol di depan, bulan tiga huruf.
- **Penanda baris kamar tetap kehadiran key `rooms`.** `hotel` tidak pernah menjadi penanda.
- **Migrasi hanya menambah** satu kolom nullable `invoices.hotel_room`. Tanpa backfill.
- **Jenis penjualan dan mode hanya dipilih di `SalesLineRuleRegistry` beserta kelas aturannya.** Tidak ada `if ($type === 'hotel')` di controller, model, Blade, atau Vue.
- **Blade tidak menyimpan logika.** Perangkaian teks dan pemilihan tata letak diselesaikan di `invoiceViewData()`; Blade hanya mencetak.
- **Tiga perubahan kontrak sekaligus.** Mengganti/menambah method pada `SalesLineInvoiceRule` memaksa `tests/Support/FakeSalesLineRule.php` dan `tests/Support/FakeSalesLineRuleRegistry.php` ikut, atau test yang tidak berhubungan pecah dengan fatal error. Ini bagian pasti dari pekerjaan, bukan kejutan — sudah terjadi empat kali di fitur sebelumnya.
- **Menjalankan test:** `php artisan test --filter=<NamaTest>` dari root `/Users/marchelinoraco/Documents/2026/erp_wm`.
- **Tidak ada test runner JavaScript.** Verifikasi Vue lewat `npm run build` plus pemeriksaan manual.
- **Jangan meng-commit** `docs/design-system/13-my-jobs-manifest.md`, `resources/js/Pages/Finance/Loans.vue`, dan `docs/superpowers/specs/2026-08-12-invoice-hotel-dua-mode-hitung-design.md`. Selalu `git add` eksplisit, jangan `git add -A`.

## File Structure

| Berkas | Tanggung jawab | Aksi |
| --- | --- | --- |
| `database/migrations/2026_08_12_100000_add_hotel_room_to_invoices.php` | Kolom `hotel_room` | Buat |
| `app/Support/CompactDateRange.php` | Satu-satunya aturan format tanggal ringkas | Buat |
| `app/Support/HotelRoomLabel.php` | Satu-satunya perangkai teks `Hotel / Room` | Buat |
| `app/Contracts/SalesLineInvoiceRule.php` | Kontrak: ganti 1 method, tambah 2 | Modifikasi |
| `app/Services/SalesLine/BaseSalesLineRule.php` | Default ketiganya | Modifikasi |
| `app/Services/SalesLine/TransportRule.php` | `date_first`, pax disembunyikan | Modifikasi |
| `app/Services/SalesLine/HotelPerRoomNightRule.php` | `hotel_room`, tanggal ringkas | Modifikasi |
| `app/Services/SalesLine/HotelPerPaxRule.php` | Tanggal ringkas | Modifikasi |
| `app/Http/Controllers/InvoiceController.php` | Selesaikan tata letak + validasi + simpan | Modifikasi |
| `resources/views/invoice.blade.php` | Cetak saja | Modifikasi |
| `resources/js/Components/Tours/InvoicesPanel.vue` | Dua kolom isian baru | Modifikasi |
| `tests/Support/FakeSalesLineRule.php` | Ikut kontrak baru | Modifikasi |
| `tests/Unit/Support/CompactDateRangeTest.php` | Kunci format tanggal | Buat |
| `tests/Unit/Support/HotelRoomLabelTest.php` | Kunci perangkaian teks | Buat |
| `tests/Feature/SalesLine/ChargeLineLayoutRuleTest.php` | Sapuan ketiga aturan baru | Buat |
| `tests/Feature/Invoice/HotelPdfLayoutTest.php` | Bentuk PDF hotel & non-hotel | Buat |

---

### Task 1: Kolom `invoices.hotel_room`

**Files:**
- Create: `database/migrations/2026_08_12_100000_add_hotel_room_to_invoices.php`
- Test: `tests/Feature/Invoice/HotelPdfLayoutTest.php`

**Interfaces:**
- Consumes: tidak ada
- Produces: kolom `invoices.hotel_room` (string nullable)

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/Invoice/HotelPdfLayoutTest.php`:

```php
<?php

namespace Tests\Feature\Invoice;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * Bentuk PDF invoice hotel mengikuti dokumen acuan pemilik. Berkas ini juga
 * menjaga bahwa jenis SELAIN hotel tidak ikut berubah sedikit pun.
 */
class HotelPdfLayoutTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    public function test_kolom_hotel_room_tersedia_dan_kosong_untuk_invoice_baru(): void
    {
        $invoice = $this->makeInvoice($this->makeTour('hotel', ['pax' => 2]), 705_000);

        $this->assertNull($invoice->hotel_room);

        $invoice->update(['hotel_room' => 'Paradise Hotel Golf & Resort – Deluxe Room Garden View']);

        $this->assertSame(
            'Paradise Hotel Golf & Resort – Deluxe Room Garden View',
            $invoice->fresh()->hotel_room
        );
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=HotelPdfLayoutTest`
Expected: FAIL — kolom `hotel_room` belum ada

- [ ] **Step 3: Buat migrasi**

Buat `database/migrations/2026_08_12_100000_add_hotel_room_to_invoices.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keterangan "Hotel / Room" untuk invoice hotel bermode per pax.
 *
 * Mode kamar menyimpan nama hotelnya per baris rincian (key `hotel` di JSON
 * description_lines) karena satu invoice boleh memuat dua hotel berbeda. Mode
 * pax tidak punya baris rincian sama sekali, jadi satu invoice = satu
 * keterangan, dan tempatnya di kolom ini.
 *
 * Nullable dan TIDAK di-backfill: invoice yang sudah ada tetap tercetak tanpa
 * baris Hotel / Room, persis seperti sebelumnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('hotel_room')->nullable()->after('pricing_mode');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('hotel_room');
        });
    }
};
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter=HotelPdfLayoutTest`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_08_12_100000_add_hotel_room_to_invoices.php \
        tests/Feature/Invoice/HotelPdfLayoutTest.php
git commit -m "feat: kolom hotel_room pada invoices"
```

---

### Task 2: Format tanggal ringkas

**Files:**
- Create: `app/Support/CompactDateRange.php`
- Test: `tests/Unit/Support/CompactDateRangeTest.php`

**Interfaces:**
- Consumes: tidak ada
- Produces: `App\Support\CompactDateRange::format(?Carbon $start, ?Carbon $end): string`

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Unit/Support/CompactDateRangeTest.php`:

```php
<?php

namespace Tests\Unit\Support;

use App\Support\CompactDateRange;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

/**
 * Format tanggal ringkas gaya voucher hotel (spec §6). Bulan dan tahun tidak
 * diulang bila sama, dan tanggal ditulis tanpa nol di depan.
 */
class CompactDateRangeTest extends TestCase
{
    private function tgl(string $iso): Carbon
    {
        return Carbon::createFromFormat('Y-m-d', $iso)->startOfDay();
    }

    public function test_sebulan_sama_tidak_mengulang_bulan(): void
    {
        $this->assertSame('1-2 Aug 2026', CompactDateRange::format($this->tgl('2026-08-01'), $this->tgl('2026-08-02')));
        $this->assertSame('15-17 Aug 2026', CompactDateRange::format($this->tgl('2026-08-15'), $this->tgl('2026-08-17')));
    }

    public function test_beda_bulan_mengulang_bulan_tapi_bukan_tahun(): void
    {
        $this->assertSame('30 Aug - 2 Sep 2026', CompactDateRange::format($this->tgl('2026-08-30'), $this->tgl('2026-09-02')));
    }

    public function test_beda_tahun_menulis_keduanya_lengkap(): void
    {
        $this->assertSame('30 Dec 2026 - 2 Jan 2027', CompactDateRange::format($this->tgl('2026-12-30'), $this->tgl('2027-01-02')));
    }

    public function test_satu_tanggal_saja(): void
    {
        $this->assertSame('1 Aug 2026', CompactDateRange::format($this->tgl('2026-08-01'), null));
        $this->assertSame('1 Aug 2026', CompactDateRange::format($this->tgl('2026-08-01'), $this->tgl('2026-08-01')));
    }

    public function test_tanpa_tanggal_mulai_menghasilkan_string_kosong(): void
    {
        $this->assertSame('', CompactDateRange::format(null, null));
        $this->assertSame('', CompactDateRange::format(null, $this->tgl('2026-08-02')));
    }

    public function test_tanggal_selesai_mendahului_mulai_tetap_ditulis_apa_adanya(): void
    {
        // Data seperti ini tidak seharusnya ada, tapi jangan melempar dan
        // jangan diam-diam menukar urutannya — cetak apa yang tersimpan.
        $this->assertSame('5-2 Aug 2026', CompactDateRange::format($this->tgl('2026-08-05'), $this->tgl('2026-08-02')));
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=CompactDateRangeTest`
Expected: FAIL — `Class "App\Support\CompactDateRange" not found`

- [ ] **Step 3: Tulis implementasinya**

Buat `app/Support/CompactDateRange.php`:

```php
<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Rentang tanggal ringkas gaya voucher hotel: `1-2 Aug 2026`.
 *
 * Bulan dan tahun hanya ditulis sekali bila kedua ujungnya sama, sehingga
 * baris Date tetap pendek pada kasus yang paling sering — menginap beberapa
 * malam dalam bulan yang sama.
 */
final class CompactDateRange
{
    public static function format(?Carbon $start, ?Carbon $end): string
    {
        if (! $start) {
            return '';
        }

        if (! $end || $end->isSameDay($start)) {
            return $start->format('j M Y');
        }

        if ($start->year !== $end->year) {
            return $start->format('j M Y') . ' - ' . $end->format('j M Y');
        }

        if ($start->month !== $end->month) {
            return $start->format('j M') . ' - ' . $end->format('j M Y');
        }

        return $start->format('j') . '-' . $end->format('j M Y');
    }
}
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter=CompactDateRangeTest`
Expected: PASS — 6 test hijau

- [ ] **Step 5: Commit**

```bash
git add app/Support/CompactDateRange.php tests/Unit/Support/CompactDateRangeTest.php
git commit -m "feat: format tanggal ringkas gaya voucher hotel"
```

---

### Task 3: Perangkai teks `Hotel / Room`

**Files:**
- Create: `app/Support/HotelRoomLabel.php`
- Test: `tests/Unit/Support/HotelRoomLabelTest.php`

**Interfaces:**
- Consumes: tidak ada
- Produces: `App\Support\HotelRoomLabel::forRoomLine(array $line): string`

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Unit/Support/HotelRoomLabelTest.php`:

```php
<?php

namespace Tests\Unit\Support;

use App\Support\HotelRoomLabel;
use PHPUnit\Framework\TestCase;

/**
 * Perangkaian baris "Hotel / Room" untuk baris kamar (spec §7.1). Bagian yang
 * kosong dilewati tanpa menyisakan pemisah menggantung — dokumen ke customer
 * tidak boleh memuat tanda hubung yang berdiri sendiri.
 */
class HotelRoomLabelTest extends TestCase
{
    public function test_lengkap(): void
    {
        $this->assertSame(
            'Paradise Hotel – 1 Deluxe Room · Twin bed',
            HotelRoomLabel::forRoomLine([
                'hotel' => 'Paradise Hotel', 'rooms' => 1,
                'label' => 'Deluxe Room', 'detail' => 'Twin bed',
            ])
        );
    }

    public function test_tanpa_keterangan(): void
    {
        $this->assertSame(
            'Paradise Hotel – 1 Deluxe Room',
            HotelRoomLabel::forRoomLine([
                'hotel' => 'Paradise Hotel', 'rooms' => 1, 'label' => 'Deluxe Room',
            ])
        );
    }

    public function test_tanpa_nama_hotel_tidak_menyisakan_tanda_hubung(): void
    {
        $hasil = HotelRoomLabel::forRoomLine(['rooms' => 2, 'label' => 'Deluxe Room']);

        $this->assertSame('2 Deluxe Room', $hasil);
        $this->assertStringNotContainsString('–', $hasil);
    }

    public function test_tanpa_tipe_kamar_hanya_nama_hotel(): void
    {
        $this->assertSame(
            'Paradise Hotel',
            HotelRoomLabel::forRoomLine(['hotel' => 'Paradise Hotel', 'rooms' => 1, 'label' => ''])
        );
    }

    public function test_semuanya_kosong_menghasilkan_string_kosong(): void
    {
        $this->assertSame('', HotelRoomLabel::forRoomLine([]));
        $this->assertSame('', HotelRoomLabel::forRoomLine(['hotel' => '', 'label' => '', 'rooms' => 0]));
    }

    public function test_jumlah_kamar_kosong_tidak_mencetak_angka(): void
    {
        $this->assertSame(
            'Paradise Hotel – Deluxe Room',
            HotelRoomLabel::forRoomLine(['hotel' => 'Paradise Hotel', 'rooms' => 0, 'label' => 'Deluxe Room'])
        );
    }

    public function test_nilai_non_skalar_tidak_melempar(): void
    {
        // description_lines didekode dengan json_decode(..., true), jadi array
        // adalah satu-satunya bentuk non-skalar yang bisa muncul di sini.
        $this->assertSame('', HotelRoomLabel::forRoomLine(['hotel' => ['x'], 'label' => ['y'], 'rooms' => ['z']]));
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=HotelRoomLabelTest`
Expected: FAIL — `Class "App\Support\HotelRoomLabel" not found`

- [ ] **Step 3: Tulis implementasinya**

Buat `app/Support/HotelRoomLabel.php`:

```php
<?php

namespace App\Support;

/**
 * Merangkai baris "Hotel / Room" dari satu baris kamar:
 * `{hotel} – {jumlah} {tipe kamar} · {keterangan}`.
 *
 * Bagian yang kosong dilewati tanpa menyisakan pemisah menggantung. Nilai
 * non-skalar diperlakukan kosong, bukan dilempar — kolom JSON yang sama bisa
 * memuat bentuk apa pun, dan satu exception di sini menggagalkan render
 * seluruh PDF.
 */
final class HotelRoomLabel
{
    public static function forRoomLine(array $line): string
    {
        $hotel  = self::teks($line['hotel'] ?? null);
        $tipe   = self::teks($line['label'] ?? null);
        $ket    = self::teks($line['detail'] ?? null);
        $kamar  = is_scalar($line['rooms'] ?? null) ? (int) $line['rooms'] : 0;

        // Jumlah kamar hanya bermakna bila ada tipe kamarnya untuk dihitung.
        $unit = $tipe === '' ? '' : trim(($kamar > 0 ? $kamar . ' ' : '') . $tipe);

        $kiri = implode(' – ', array_filter([$hotel, $unit], fn ($v) => $v !== ''));

        if ($kiri === '') {
            return '';
        }

        return $ket === '' ? $kiri : $kiri . ' · ' . $ket;
    }

    private static function teks(mixed $nilai): string
    {
        return is_scalar($nilai) ? trim((string) $nilai) : '';
    }
}
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter=HotelRoomLabelTest`
Expected: PASS — 7 test hijau

- [ ] **Step 5: Commit**

```bash
git add app/Support/HotelRoomLabel.php tests/Unit/Support/HotelRoomLabelTest.php
git commit -m "feat: perangkai baris Hotel/Room dari baris kamar"
```

---

### Task 4: Tiga aturan tata letak di kontrak

**Files:**
- Modify: `app/Contracts/SalesLineInvoiceRule.php`
- Modify: `app/Services/SalesLine/BaseSalesLineRule.php`
- Modify: `app/Services/SalesLine/TransportRule.php`
- Modify: `app/Services/SalesLine/HotelPerRoomNightRule.php`
- Modify: `app/Services/SalesLine/HotelPerPaxRule.php`
- Modify: `tests/Support/FakeSalesLineRule.php`
- Test: `tests/Feature/SalesLine/ChargeLineLayoutRuleTest.php`

**Interfaces:**
- Consumes: tidak ada
- Produces:
  - `chargeLineLayout(): string` — `'default'` | `'date_first'` | `'hotel_room'` (**menggantikan** `chargeLinesDateFirstInPdf()`)
  - `showsTotalPaxInPdf(): bool`
  - `usesCompactDateInPdf(): bool`

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/SalesLine/ChargeLineLayoutRuleTest.php`:

```php
<?php

namespace Tests\Feature\SalesLine;

use App\Services\SalesLine\HotelPerPaxRule;
use App\Services\SalesLine\HotelPerRoomNightRule;
use App\Services\SalesLine\SalesLineRuleRegistry;
use Tests\TestCase;

/**
 * Tiga tata letak baris bernominal di PDF, plus dua keputusan tampilan lain.
 *
 * Ketiganya disapu dari registry dengan peta harapan eksplisit: jenis baru
 * yang lupa memutuskan akan menggagalkan test dengan menyebut namanya, bukan
 * lolos diam-diam lewat default warisan.
 */
class ChargeLineLayoutRuleTest extends TestCase
{
    private const LAYOUT = [
        'rental'    => 'date_first',
        'hotel'     => 'default',   // registry default hotel = mode pax
        'tour'      => 'default',
        'guide'     => 'default',
        'mice'      => 'default',
        'document'  => 'default',
        'ticketing' => 'default',
    ];

    private const PAX = [
        'rental'    => false,
        'hotel'     => true,
        'tour'      => true,
        'guide'     => true,
        'mice'      => true,
        'document'  => true,
        'ticketing' => true,
    ];

    private const TANGGAL_RINGKAS = [
        'rental'    => false,
        'hotel'     => true,
        'tour'      => false,
        'guide'     => false,
        'mice'      => false,
        'document'  => false,
        'ticketing' => false,
    ];

    /** @param array<string, mixed> $harapan */
    private function sapu(array $harapan, string $method): void
    {
        $registry = app(SalesLineRuleRegistry::class);

        foreach ($registry->keys() as $key) {
            $this->assertArrayHasKey(
                $key,
                $harapan,
                "Jenis {$key} terdaftar di registry tapi belum punya keputusan {$method} di peta harapan test ini."
            );

            $this->assertSame($harapan[$key], $registry->for($key)->{$method}(), "Jenis {$key} — {$method}");
        }
    }

    public function test_tata_letak_baris_bernominal_per_jenis(): void
    {
        $this->sapu(self::LAYOUT, 'chargeLineLayout');
    }

    public function test_hanya_rental_yang_menyembunyikan_total_pax(): void
    {
        $this->sapu(self::PAX, 'showsTotalPaxInPdf');
    }

    public function test_hanya_hotel_yang_memakai_tanggal_ringkas(): void
    {
        $this->sapu(self::TANGGAL_RINGKAS, 'usesCompactDateInPdf');
    }

    public function test_mode_kamar_hotel_memakai_tata_letak_hotel_room(): void
    {
        // Tidak terjangkau lewat for('hotel') — modenya milik invoice.
        $this->assertSame('hotel_room', (new HotelPerRoomNightRule())->chargeLineLayout());
        $this->assertSame('default', (new HotelPerPaxRule())->chargeLineLayout());
    }

    public function test_kedua_mode_hotel_menampilkan_pax_dan_tanggal_ringkas(): void
    {
        foreach ([new HotelPerPaxRule(), new HotelPerRoomNightRule()] as $aturan) {
            $this->assertTrue($aturan->showsTotalPaxInPdf(), get_class($aturan));
            $this->assertTrue($aturan->usesCompactDateInPdf(), get_class($aturan));
        }
    }

    public function test_method_lama_sudah_tidak_ada(): void
    {
        // Gerbang: boolean dua keadaan tidak boleh tertinggal berdampingan
        // dengan pemilih tiga keadaan — dua sumber kebenaran akan berselisih.
        $this->assertFalse(
            method_exists(HotelPerRoomNightRule::class, 'chargeLinesDateFirstInPdf'),
            'chargeLinesDateFirstInPdf() harus DIGANTI chargeLineLayout(), bukan dibiarkan berdampingan'
        );
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=ChargeLineLayoutRuleTest`
Expected: FAIL — `Call to undefined method ...::chargeLineLayout()`

- [ ] **Step 3: Ganti dan tambah method di kontrak**

Di `app/Contracts/SalesLineInvoiceRule.php`, **hapus** deklarasi `chargeLinesDateFirstInPdf()` dan ganti dengan ketiga method berikut:

```php
    /**
     * Bentuk baris bernominal di PDF:
     *   'default'    — label di kiri, tanggal menyatu dengan keterangan
     *   'date_first' — rentang tanggal naik ke kolom kiri (rental)
     *   'hotel_room' — pasangan "Hotel / Room" + "Price" (hotel mode kamar)
     *
     * Tiga keadaan, bukan dua boolean: kombinasi tak sah jadi mustahil.
     */
    public function chargeLineLayout(): string;

    /**
     * true = jumlah peserta bermakna untuk jenis ini dan dicetak di PDF.
     *
     * Rental tidak mengenal peserta. Hotel mencetaknya sebagai keterangan
     * meski pada mode kamar pax tidak ikut mengalikan apa pun.
     */
    public function showsTotalPaxInPdf(): bool;

    /** true = baris Date memakai format ringkas gaya voucher hotel. */
    public function usesCompactDateInPdf(): bool;
```

- [ ] **Step 4: Beri default di kelas dasar**

Di `app/Services/SalesLine/BaseSalesLineRule.php`, **hapus** `chargeLinesDateFirstInPdf()` dan tambahkan:

```php
    /** Mayoritas jenis memakai bentuk baris bawaan. */
    public function chargeLineLayout(): string
    {
        return 'default';
    }

    /** Mayoritas jenis ditagih per orang, jadi jumlah peserta bermakna. */
    public function showsTotalPaxInPdf(): bool
    {
        return true;
    }

    /** Format tanggal ringkas khusus dokumen hotel. */
    public function usesCompactDateInPdf(): bool
    {
        return false;
    }
```

- [ ] **Step 5: Sesuaikan TransportRule**

Di `app/Services/SalesLine/TransportRule.php`, ganti method `chargeLinesDateFirstInPdf()` yang ada menjadi:

```php
    /** Yang pertama dicari customer adalah periode sewanya, baru unitnya. */
    public function chargeLineLayout(): string
    {
        return 'date_first';
    }

    /** Rental menyewakan unit, bukan menagih per orang — §8.7 spek lama. */
    public function showsTotalPaxInPdf(): bool
    {
        return false;
    }
```

- [ ] **Step 6: Sesuaikan HotelPerRoomNightRule**

Di `app/Services/SalesLine/HotelPerRoomNightRule.php`, ganti `chargeLinesDateFirstInPdf()` menjadi:

```php
    /** Dokumen acuan hotel: pasangan "Hotel / Room" dan "Price". */
    public function chargeLineLayout(): string
    {
        return 'hotel_room';
    }

    public function usesCompactDateInPdf(): bool
    {
        return true;
    }
```

- [ ] **Step 7: Sesuaikan HotelPerPaxRule**

Di `app/Services/SalesLine/HotelPerPaxRule.php`, tambahkan:

```php
    public function usesCompactDateInPdf(): bool
    {
        return true;
    }
```

- [ ] **Step 8: Ikuti kontrak baru di test double**

Di `tests/Support/FakeSalesLineRule.php`, ganti parameter konstruktor `$chargeLinesDateFirstInPdf` menjadi `private string $chargeLineLayout = 'default'`, tambahkan `private bool $showsTotalPaxInPdf = true` dan `private bool $usesCompactDateInPdf = false`, lalu ganti method `chargeLinesDateFirstInPdf()` dengan:

```php
    public function chargeLineLayout(): string
    {
        return $this->chargeLineLayout;
    }

    public function showsTotalPaxInPdf(): bool
    {
        return $this->showsTotalPaxInPdf;
    }

    public function usesCompactDateInPdf(): bool
    {
        return $this->usesCompactDateInPdf;
    }
```

- [ ] **Step 9: Jalankan test, pastikan lulus**

Run: `php artisan test --filter=ChargeLineLayoutRuleTest`
Expected: PASS — 6 test hijau

- [ ] **Step 10: Perbaiki seluruh pemanggil lama**

Run: `grep -rn "chargeLinesDateFirstInPdf" app resources tests`
Expected: hanya `app/Http/Controllers/InvoiceController.php` yang tersisa — itu Task 5. Bila ada yang lain, perbaiki sekarang dan catat di laporan.

- [ ] **Step 11: Jalankan seluruh test SalesLine**

Run: `php artisan test --filter=SalesLine`
Expected: PASS

- [ ] **Step 12: Commit**

```bash
git add app/Contracts/SalesLineInvoiceRule.php \
        app/Services/SalesLine/BaseSalesLineRule.php \
        app/Services/SalesLine/TransportRule.php \
        app/Services/SalesLine/HotelPerRoomNightRule.php \
        app/Services/SalesLine/HotelPerPaxRule.php \
        tests/Support/FakeSalesLineRule.php \
        tests/Feature/SalesLine/ChargeLineLayoutRuleTest.php
git commit -m "feat: pemilih tata letak tiga keadaan menggantikan boolean dua keadaan"
```

---

### Task 5: `invoiceViewData()` menyelesaikan seluruh keputusan tampilan

Blade tidak boleh menyimpan logika. Semua yang dibutuhkan dokumen diselesaikan sekali di sini, sehingga bisa diuji tanpa merender apa pun.

**Files:**
- Modify: `app/Http/Controllers/InvoiceController.php` (method `invoiceViewData()`)
- Test: `tests/Feature/Invoice/HotelPdfLayoutTest.php`

**Interfaces:**
- Consumes: `CompactDateRange::format()` (Task 2), `HotelRoomLabel::forRoomLine()` (Task 3), `chargeLineLayout()`, `showsTotalPaxInPdf()`, `usesCompactDateInPdf()` (Task 4)
- Produces variabel view baru:
  - `chargeLineLayout` (string)
  - `showsTotalPax` (bool)
  - `hotelRoomInfo` (string) — teks `Hotel / Room` untuk blok info; `''` bila tidak ada
  - `roomLines` (array) — baris kamar untuk area bernominal; kosong bila tata letaknya bukan `hotel_room`
  - `resvDate` (string) — pindah ke sini dari Blade
  - `dateFirstLines` (bool) **tetap dikirim di task ini**, diturunkan dari `chargeLineLayout() === 'date_first'`. Blade masih memakainya sampai Task 6; menghapusnya sekarang membuat suite merah di antara dua commit. Task 6 yang mencabutnya.

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan ke `tests/Feature/Invoice/HotelPdfLayoutTest.php`:

```php
    private const SATU_KAMAR = [
        ['hotel' => 'Paradise Hotel Golf & Resort', 'label' => 'Deluxe Room Garden View',
         'date' => '2026-08-01', 'date_end' => '2026-08-02', 'rooms' => 1,
         'unit_price' => 705_000, 'amount' => 705_000],
    ];

    private const DUA_KAMAR = [
        ['hotel' => 'Paradise Hotel', 'label' => 'Deluxe Room Garden View',
         'date' => '2026-08-01', 'date_end' => '2026-08-03', 'rooms' => 1,
         'unit_price' => 705_000, 'amount' => 1_410_000],
        ['hotel' => 'Ibis Manado', 'label' => 'Superior Room',
         'date' => '2026-08-03', 'date_end' => '2026-08-04', 'rooms' => 1,
         'unit_price' => 950_000, 'amount' => 950_000],
    ];

    private function dataView(\App\Models\Invoice $invoice): array
    {
        return app(\App\Http\Controllers\InvoiceController::class)->invoiceViewData($invoice->fresh());
    }

    private function invoiceKamar(array $baris): \App\Models\Invoice
    {
        $tour    = $this->makeTour('hotel', ['pax' => 2, 'start_date' => '2026-08-01', 'end_date' => '2026-08-02']);
        $invoice = $this->makeInvoice($tour, 705_000);
        $invoice->update([
            'pricing_mode'      => \App\Models\Invoice::PRICING_PER_ROOM_NIGHT,
            'description_lines' => $baris,
        ]);
        $invoice->fresh()->syncProformaTotal();

        return $invoice->fresh();
    }

    public function test_satu_baris_kamar_menaikkan_hotel_room_ke_blok_info(): void
    {
        $data = $this->dataView($this->invoiceKamar(self::SATU_KAMAR));

        $this->assertSame('hotel_room', $data['chargeLineLayout']);
        $this->assertSame('Paradise Hotel Golf & Resort – 1 Deluxe Room Garden View', $data['hotelRoomInfo']);
        $this->assertCount(1, $data['roomLines']);
    }

    public function test_dua_baris_kamar_mengosongkan_blok_info(): void
    {
        $data = $this->dataView($this->invoiceKamar(self::DUA_KAMAR));

        $this->assertSame('', $data['hotelRoomInfo'], 'Dengan dua kamar, Hotel/Room turun berpasangan ke area bernominal');
        $this->assertCount(2, $data['roomLines']);
    }

    public function test_mode_pax_memakai_kolom_hotel_room(): void
    {
        $tour    = $this->makeTour('hotel', ['pax' => 2, 'start_date' => '2026-08-01', 'end_date' => '2026-08-02']);
        $invoice = $this->makeInvoice($tour, 705_000);
        $invoice->update(['hotel_room' => 'Paradise Hotel – Deluxe Room Garden View']);

        $data = $this->dataView($invoice);

        $this->assertSame('default', $data['chargeLineLayout']);
        $this->assertSame('Paradise Hotel – Deluxe Room Garden View', $data['hotelRoomInfo']);
        $this->assertSame([], $data['roomLines']);
    }

    public function test_tanggal_ringkas_hanya_untuk_hotel(): void
    {
        $tourHotel = $this->makeTour('hotel', ['pax' => 2, 'start_date' => '2026-08-01', 'end_date' => '2026-08-02']);
        $hotel     = $this->makeInvoice($tourHotel, 705_000);

        $tourLain = $this->makeTour('tour', ['pax' => 2, 'start_date' => '2026-08-01', 'end_date' => '2026-08-02']);
        $lain     = $this->makeInvoice($tourLain, 705_000);

        $this->assertSame('1-2 Aug 2026', $this->dataView($hotel)['resvDate']);
        $this->assertSame('01 August 2026 – 02 August 2026', $this->dataView($lain)['resvDate']);
    }

    public function test_total_pax_tampil_untuk_hotel_dan_tidak_untuk_rental(): void
    {
        $hotel  = $this->makeInvoice($this->makeTour('hotel', ['pax' => 2]), 705_000);
        $rental = $this->makeInvoice($this->makeTour('rental', ['pax' => 2]), 705_000);

        $this->assertTrue($this->dataView($hotel)['showsTotalPax']);
        $this->assertFalse($this->dataView($rental)['showsTotalPax']);
    }
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=HotelPdfLayoutTest`
Expected: FAIL — `Undefined array key "chargeLineLayout"`

- [ ] **Step 3: Selesaikan keputusan tampilan di controller**

Di `app/Http/Controllers/InvoiceController.php`, method `invoiceViewData()`, tambahkan `use App\Support\CompactDateRange;` dan `use App\Support\HotelRoomLabel;` di bagian atas berkas, lalu sisipkan blok berikut setelah perhitungan `$chargeLines` yang sudah ada:

```php
        // Baris kamar yang dicetak berpasangan dengan "Price"-nya. Kosong bila
        // tata letaknya bukan hotel_room, sehingga Blade tidak perlu bertanya.
        $roomLines = $aturan->chargeLineLayout() === 'hotel_room'
            ? collect($chargeLines)->filter(fn ($l) => RoomChargeLine::isRoomLine($l))->values()->all()
            : [];

        // Spec §2.4: satu baris kamar menaikkan "Hotel / Room" ke blok info;
        // dua atau lebih menurunkannya berpasangan ke area bernominal. Mode
        // pax selalu memakai blok info, karena hanya punya satu keterangan.
        $hotelRoomInfo = match (true) {
            count($roomLines) === 1        => HotelRoomLabel::forRoomLine($roomLines[0]),
            $aturan->chargeLineLayout() === 'hotel_room' => '',
            default                        => trim((string) $invoice->hotel_room),
        };

        $resvDate = $aturan->usesCompactDateInPdf()
            ? CompactDateRange::format($invoice->tour?->start_date, $invoice->tour?->end_date)
            : ($invoice->tour?->start_date
                ? $invoice->tour->start_date->format('d F Y')
                    . ($invoice->tour->end_date ? ' – ' . $invoice->tour->end_date->format('d F Y') : '')
                : '');
```

Lalu pada array yang dikembalikan, **ganti** entri `'dateFirstLines'` dengan keenam entri berikut. `dateFirstLines` sengaja tetap dikirim: Blade masih memakainya sampai Task 6, dan mencabutnya sekarang membuat suite merah di antara dua commit.

```php
            'chargeLineLayout' => $aturan->chargeLineLayout(),
            'showsTotalPax'    => $aturan->showsTotalPaxInPdf(),
            'hotelRoomInfo'    => $hotelRoomInfo,
            'roomLines'        => $roomLines,
            'resvDate'         => $resvDate,
            // Dicabut di Task 6, saat Blade berhenti memakainya.
            'dateFirstLines'   => $aturan->chargeLineLayout() === 'date_first',
```

Blade masih menghitung `$resvDate` sendiri di blok `@php` atasnya; nilai yang dikirim di sini belum terpakai sampai Task 6 mencabut perhitungan itu. Itu disengaja — task ini hanya menambah, tidak mencabut apa pun.

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter=HotelPdfLayoutTest`
Expected: PASS — kelima test Step 1 hijau

Run: `php artisan test`
Expected: PASS — seluruh suite tetap hijau, karena task ini murni menambah kunci data view

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/InvoiceController.php tests/Feature/Invoice/HotelPdfLayoutTest.php
git commit -m "feat: keputusan tata letak PDF diselesaikan di invoiceViewData"
```

---

### Task 6: Blade — blok info

**Files:**
- Modify: `resources/views/invoice.blade.php` (blok `@php` atas, CSS `.kv td.k`, blok info)
- Modify: `app/Http/Controllers/InvoiceController.php` (cabut `dateFirstLines`)
- Test: `tests/Feature/Invoice/HotelPdfLayoutTest.php`

**Interfaces:**
- Consumes: `resvDate`, `showsTotalPax`, `hotelRoomInfo`, `chargeLineLayout` dari Task 5
- Produces: `dateFirstLines` tidak ada lagi di mana pun

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan ke `tests/Feature/Invoice/HotelPdfLayoutTest.php`:

```php
    private function render(\App\Models\Invoice $invoice): string
    {
        return view('invoice', $this->dataView($invoice))->render();
    }

    public function test_blok_info_hotel_memuat_tanggal_ringkas_pax_dan_hotel_room(): void
    {
        $html = $this->render($this->invoiceKamar(self::SATU_KAMAR));

        $this->assertStringContainsString('1-2 Aug 2026', $html);
        $this->assertStringContainsString('Total Pax', $html);
        $this->assertStringContainsString('2 pax', $html);
        $this->assertStringContainsString('Hotel / Room', $html);
        $this->assertStringContainsString('Paradise Hotel Golf &amp; Resort – 1 Deluxe Room Garden View', $html);
    }

    public function test_dua_kamar_tidak_memuat_hotel_room_di_blok_info(): void
    {
        $html = $this->render($this->invoiceKamar(self::DUA_KAMAR));

        // Baris Hotel / Room tetap ada (di area bernominal, Task 7) tapi TIDAK
        // di blok info — dibuktikan dengan urutannya relatif terhadap Total Pax.
        $posPax   = strpos($html, 'Total Pax');
        $posHotel = strpos($html, 'Hotel / Room');

        $this->assertNotFalse($posPax);
        $this->assertNotFalse($posHotel);
        $this->assertGreaterThan($posPax + 400, $posHotel, 'Hotel / Room harus jauh di bawah blok info, bukan menempel di bawah Total Pax');
    }

    public function test_rental_tidak_mencetak_total_pax(): void
    {
        $tour    = $this->makeTour('rental', ['pax' => 4, 'start_date' => '2026-08-01', 'end_date' => '2026-08-02']);
        $invoice = $this->makeInvoice($tour, 900_000);
        $invoice->update(['description_lines' => [
            ['label' => 'Avanza', 'date' => '2026-08-01', 'date_end' => '2026-08-02', 'detail' => 'sopir', 'amount' => 900_000],
        ]]);
        $invoice->fresh()->syncProformaTotal();

        $html = $this->render($invoice);

        $this->assertStringNotContainsString('Total Pax', $html);
        $this->assertStringContainsString('01/08/2026 – 02/08/2026', $html, 'Rental tetap memakai tata letak tanggal-di-kiri');
    }

    public function test_jenis_lain_tetap_memakai_tanggal_panjang(): void
    {
        $tour    = $this->makeTour('tour', ['pax' => 4, 'start_date' => '2026-08-01', 'end_date' => '2026-08-02']);
        $invoice = $this->makeInvoice($tour, 1_000_000);

        $html = $this->render($invoice);

        $this->assertStringContainsString('01 August 2026 – 02 August 2026', $html);
        $this->assertStringContainsString('Total Pax', $html);
    }

    public function test_invoice_hotel_lama_tanpa_keterangan_tetap_tercetak(): void
    {
        // Seluruh invoice hotel yang sudah ada tidak punya `hotel` maupun
        // `hotel_room`. Dokumennya harus tetap terbentuk, hanya tanpa baris
        // Hotel / Room — bukan error, bukan baris kosong berlabel.
        $tour    = $this->makeTour('hotel', ['pax' => 2, 'start_date' => '2026-08-01', 'end_date' => '2026-08-02']);
        $invoice = $this->makeInvoice($tour, 705_000);

        $html = $this->render($invoice);

        $this->assertStringNotContainsString('Hotel / Room', $html);
        $this->assertStringContainsString('Total Pax', $html);
        $this->assertStringContainsString('1-2 Aug 2026', $html);
    }
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=HotelPdfLayoutTest`
Expected: FAIL — `Undefined variable $dateFirstLines` atau tanggal masih format panjang untuk hotel

- [ ] **Step 3: Hapus perhitungan `$resvDate` dari Blade**

Di `resources/views/invoice.blade.php`, blok `@php` di bagian atas, **hapus** seluruh perhitungan `$resvDate` (variabel itu kini datang dari controller). Jangan sentuh `$custName`, `$party`, `$invNote`, maupun `$domain`.

- [ ] **Step 4: Sesuaikan lebar kolom label**

Ganti baris CSS `.kv td.k` menjadi:

```blade
    .kv td.k { width: {{ ($chargeLineLayout ?? 'default') === 'default' ? '120px' : '180px' }}; }
```

- [ ] **Step 5: Ganti gerbang Total Pax dan tambah baris Hotel / Room**

Di blok info, ganti blok `@if($tour?->pax && !($fromLineItems ?? false))` beserta komentarnya menjadi:

```blade
                            {{-- Jumlah peserta dicetak bila jenisnya mengenalinya.
                                 Rental menyewakan unit, bukan menagih per orang.
                                 Untuk hotel mode kamar angkanya keterangan saja —
                                 pax tidak ikut mengalikan apa pun di sana. --}}
                            @if($tour?->pax && ($showsTotalPax ?? true))
                            <tr><td class="k">Total Pax</td><td class="s">:</td><td>{{ $tour->pax }} pax</td></tr>
                            @endif
                            @if(($hotelRoomInfo ?? '') !== '')
                            <tr><td class="k">Hotel / Room</td><td class="s">:</td><td>{{ $hotelRoomInfo }}</td></tr>
                            @endif
```

- [ ] **Step 6: Cabut `dateFirstLines`**

Blade tidak lagi memakainya. Di `app/Http/Controllers/InvoiceController.php`, `invoiceViewData()`, hapus entri `'dateFirstLines' => ...` beserta komentar "Dicabut di Task 6" di atasnya.

Run: `grep -rn "dateFirstLines" app resources tests`
Expected: tidak ada hasil. Bila masih ada, perbaiki pemanggilnya sekarang — dua sumber kebenaran tata letak tidak boleh hidup berdampingan.

- [ ] **Step 7: Jalankan test, pastikan lulus**

Run: `php artisan test --filter=HotelPdfLayoutTest`
Expected: PASS untuk kelima test Step 1. Test area bernominal (Task 7) belum ada.

- [ ] **Step 8: Jalankan seluruh test Invoice**

Run: `php artisan test --filter=Invoice`
Expected: PASS — bila ada kegagalan pada helper test lama yang menyusun data view sendiri, perbaiki helpernya agar memakai `invoiceViewData()`; jangan melemahkan assertion mana pun.

- [ ] **Step 9: Commit**

```bash
git add resources/views/invoice.blade.php \
        app/Http/Controllers/InvoiceController.php \
        tests/Feature/Invoice/HotelPdfLayoutTest.php
git commit -m "feat: blok info PDF hotel pakai tanggal ringkas, Total Pax, dan Hotel/Room"
```

---

### Task 7: Blade — area baris bernominal

**Files:**
- Modify: `resources/views/invoice.blade.php` (loop baris bernominal)
- Test: `tests/Feature/Invoice/HotelPdfLayoutTest.php`

**Interfaces:**
- Consumes: `chargeLineLayout`, `roomLines` dari Task 5
- Produces: tidak ada

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan ke `tests/Feature/Invoice/HotelPdfLayoutTest.php`:

```php
    public function test_satu_kamar_mencetak_baris_price_berpengali_room_dan_night(): void
    {
        $html = $this->render($this->invoiceKamar(self::SATU_KAMAR));

        $this->assertStringContainsString('IDR 705.000 x 1 room x 1 night', $html);
        $this->assertStringContainsString('>Price<', $html);
    }

    public function test_dua_kamar_mencetak_dua_pasang_hotel_room_dan_price(): void
    {
        $html = $this->render($this->invoiceKamar(self::DUA_KAMAR));

        $this->assertSame(2, substr_count($html, 'Hotel / Room'));
        $this->assertStringContainsString('Paradise Hotel – 1 Deluxe Room Garden View', $html);
        $this->assertStringContainsString('Ibis Manado – 1 Superior Room', $html);
        $this->assertStringContainsString('IDR 705.000 x 1 room x 2 night', $html);
        $this->assertStringContainsString('IDR 950.000 x 1 room x 1 night', $html);
    }

    public function test_mode_pax_mencetak_price_berpengali_pax(): void
    {
        $tour    = $this->makeTour('hotel', ['pax' => 2, 'start_date' => '2026-08-01', 'end_date' => '2026-08-02']);
        $invoice = $this->makeInvoice($tour, 705_000);
        $invoice->update(['hotel_room' => 'Paradise Hotel – Deluxe Room Garden View']);

        $html = $this->render($invoice);

        $this->assertStringContainsString('&times; 2 pax', $html);
        $this->assertStringNotContainsString('room x', $html);
    }

    public function test_rental_tetap_mencetak_seluruh_baris_bernominalnya(): void
    {
        $baris = [
            ['label' => 'Avanza', 'date' => '2026-08-01', 'date_end' => '2026-08-02', 'detail' => 'sopir', 'amount' => 900_000],
            ['label' => 'Innova', 'date' => '2026-08-02', 'date_end' => '2026-08-03', 'detail' => 'sopir', 'amount' => 1_100_000],
        ];

        $tour    = $this->makeTour('rental', ['pax' => 4, 'start_date' => '2026-08-01', 'end_date' => '2026-08-03']);
        $invoice = $this->makeInvoice($tour, 2_000_000);
        $invoice->update(['description_lines' => $baris]);
        $invoice->fresh()->syncProformaTotal();

        $html = $this->render($invoice);

        $this->assertStringContainsString('Avanza', $html);
        $this->assertStringContainsString('Innova', $html);
        $this->assertStringNotContainsString('Hotel / Room', $html);
    }
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=HotelPdfLayoutTest`
Expected: FAIL — belum ada baris `Price` berpengali `x 1 room x 1 night`

- [ ] **Step 3: Cetak pasangan Hotel/Room + Price untuk tata letak hotel_room**

Di `resources/views/invoice.blade.php`, tepat **sebelum** loop `@foreach($chargeLines as $ln)` yang sudah ada, sisipkan blok berikut:

```blade
                {{-- Tata letak dokumen hotel: tiap baris kamar mencetak
                     "Hotel / Room" lalu "Price" berpengali kamar × malam.
                     Baris Hotel/Room dilewati bila hanya ada satu kamar — di
                     kasus itu ia sudah naik ke blok info (spec §2.4). --}}
                @foreach($roomLines as $rl)
                @php
                    $rlNights = \App\Support\RoomChargeLine::nights($rl);
                    $rlRooms  = is_scalar($rl['rooms'] ?? null) ? (int) $rl['rooms'] : 0;
                    $rlHarga  = is_scalar($rl['unit_price'] ?? null) ? (float) $rl['unit_price'] : 0;
                    $rlLabel  = \App\Support\HotelRoomLabel::forRoomLine($rl);
                @endphp
                @if(count($roomLines) > 1 && $rlLabel !== '')
                <tr>
                    <td class="dcell">
                        <table class="kv">
                            <tr><td class="k">Hotel / Room</td><td class="s">:</td><td>{{ $rlLabel }}</td></tr>
                        </table>
                    </td>
                    <td class="acell"></td>
                </tr>
                @endif
                <tr>
                    <td class="dcell">
                        <table class="kv">
                            <tr>
                                <td class="k">Price</td>
                                <td class="s">:</td>
                                <td>{{ $fmt($rlHarga) }} x {{ $rlRooms }} room x {{ $rlNights }} night</td>
                            </tr>
                        </table>
                    </td>
                    <td class="acell">{{ $fmt($rl['amount'] ?? 0) }}</td>
                </tr>
                @endforeach
```

- [ ] **Step 4: Jangan cetak baris kamar dua kali**

Loop `@foreach($chargeLines as $ln)` yang sudah ada masih akan mencetak baris kamar sebagai baris bernominal biasa. Tambahkan satu baris tepat setelah `@continue(empty($ln['amount']))` di dalam loop itu:

```blade
                @continue(($chargeLineLayout ?? 'default') === 'hotel_room' && \App\Support\RoomChargeLine::isRoomLine($ln))
```

- [ ] **Step 5: Jalankan test, pastikan lulus**

Run: `php artisan test --filter=HotelPdfLayoutTest`
Expected: PASS — seluruh test di kelas itu hijau

- [ ] **Step 6: Jalankan seluruh test Invoice dan SalesLine**

Run: `php artisan test --filter=Invoice && php artisan test --filter=SalesLine`
Expected: PASS

- [ ] **Step 7: Commit**

```bash
git add resources/views/invoice.blade.php tests/Feature/Invoice/HotelPdfLayoutTest.php
git commit -m "feat: PDF hotel mode kamar mencetak pasangan Hotel/Room dan Price"
```

---

### Task 8: Menyimpan `hotel` dan `hotel_room`

**Files:**
- Modify: `app/Http/Controllers/InvoiceController.php` (method `updateProforma()`)
- Test: `tests/Feature/Invoice/HotelPdfLayoutTest.php`

**Interfaces:**
- Consumes: kolom `hotel_room` (Task 1)
- Produces: `hotel` bertahan pada baris kamar; `hotel_room` bertahan pada invoice

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan ke `tests/Feature/Invoice/HotelPdfLayoutTest.php`:

```php
    public function test_nama_hotel_per_baris_dan_keterangan_mode_pax_tersimpan(): void
    {
        $invoice = $this->makeInvoice($this->makeTour('hotel', ['pax' => 2]), 705_000);

        $this->actingAs($this->salesUser())
            ->patch(route('invoices.proforma', $invoice), [
                'currency'          => 'IDR',
                'unit_price'        => 705_000,
                'pricing_mode'      => 'per_room_night',
                'hotel_room'        => 'Paradise Hotel – Deluxe Room Garden View',
                'description_lines' => [
                    ['hotel' => 'Paradise Hotel Golf & Resort', 'label' => 'Deluxe Room Garden View',
                     'date' => '2026-08-01', 'date_end' => '2026-08-02',
                     'rooms' => 1, 'unit_price' => 705_000, 'amount' => 0],
                ],
            ])
            ->assertRedirect();

        $segar = $invoice->fresh();

        $this->assertSame('Paradise Hotel Golf & Resort', $segar->description_lines[0]['hotel']);
        $this->assertSame('Paradise Hotel – Deluxe Room Garden View', $segar->hotel_room);
        $this->assertEquals(705_000, $segar->description_lines[0]['amount'], 'Server tetap yang menghitung nominalnya');
    }

    public function test_permintaan_tanpa_hotel_room_tidak_menghapus_yang_tersimpan(): void
    {
        $invoice = $this->makeInvoice($this->makeTour('hotel', ['pax' => 2]), 705_000);
        $invoice->update(['hotel_room' => 'Paradise Hotel – Deluxe Room']);

        $this->actingAs($this->salesUser())
            ->patch(route('invoices.proforma', $invoice), [
                'currency' => 'IDR', 'unit_price' => 705_000,
            ])
            ->assertRedirect();

        $this->assertSame('Paradise Hotel – Deluxe Room', $invoice->fresh()->hotel_room);
    }
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=HotelPdfLayoutTest`
Expected: FAIL — `hotel` dan `hotel_room` dibuang validasi

- [ ] **Step 3: Tambah aturan validasi**

Di `app/Http/Controllers/InvoiceController.php`, method `updateProforma()`, tambahkan `hotel_room` sejajar dengan `pricing_mode`, dan `description_lines.*.hotel` tepat setelah `description_lines.*.unit_price`:

```php
            'hotel_room'                     => 'nullable|string|max:255',
```

```php
            'description_lines.*.hotel'      => 'nullable|string|max:255',
```

- [ ] **Step 4: Simpan kolomnya**

Pada blok `$invoice->fill([...])`, tambahkan tepat setelah `'pricing_mode'`:

```php
            'hotel_room'        => $data['hotel_room'] ?? $invoice->hotel_room,
```

- [ ] **Step 5: Jalankan test, pastikan lulus**

Run: `php artisan test --filter=HotelPdfLayoutTest`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/InvoiceController.php tests/Feature/Invoice/HotelPdfLayoutTest.php
git commit -m "feat: simpan nama hotel per baris kamar dan keterangan Hotel/Room mode pax"
```

---

### Task 9: Dua kolom isian baru di form

**Files:**
- Modify: `resources/js/Components/Tours/InvoicesPanel.vue`

**Interfaces:**
- Consumes: field `hotel` pada baris kamar dan `hotel_room` pada invoice, keduanya diterima `updateProforma` (Task 8)
- Produces: tidak ada

- [ ] **Step 1: Bawa `hotel` di baris kamar**

Pada blok `watch`, pemetaan `room_lines`, tambahkan `hotel: l.hotel ?? ''` ke objek yang dipetakan. Pada `addRoomLine()`, tambahkan `hotel: ''` ke baris baru. Pada `saveProforma()`, pemetaan `room_lines`, tambahkan `hotel: l.hotel ?? ''`.

- [ ] **Step 2: Bawa `hotel_room` di form invoice**

Pada blok `watch`, objek `proformaForms[inv.id]`, tambahkan tepat setelah `pricing_mode`:

```js
                hotel_room:        inv.hotel_room ?? '',
```

`saveProforma()` menyebar `...f`, jadi `hotel_room` ikut terkirim tanpa perubahan lain.

- [ ] **Step 3: Tambah kolom NAMA HOTEL di blok Rincian Kamar**

Pada baris judul kolom blok Rincian Kamar, sisipkan `<span class="w-40">Nama Hotel</span>` sebagai kolom pertama, sebelum `Mulai`. Pada baris isiannya, sisipkan sebagai input pertama:

```vue
                                <input type="text" v-model="ln.hotel" @blur="saveProforma(inv.id)" placeholder="Nama Hotel"
                                    class="w-40 border rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-primary" />
```

- [ ] **Step 4: Tambah kolom Hotel / Room untuk mode pax**

Tepat sebelum blok "Harga / pax", tambahkan:

```vue
                    <!-- Keterangan Hotel / Room mode pax — mode kamar memakai
                         nama hotel per baris rincian, bukan kolom ini. -->
                    <div v-if="!pricingModeIsRoom(inv.id) && (inv.rules?.pricingModes ?? []).length > 1" class="space-y-1">
                        <label class="text-xs font-medium text-muted-foreground">Hotel / Room (tampil di PDF)</label>
                        <input type="text" v-model="proformaForms[inv.id].hotel_room" @blur="saveProforma(inv.id)"
                            placeholder="mis. Paradise Hotel Golf &amp; Resort – Deluxe Room Garden View"
                            class="block w-full border rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-primary" />
                    </div>
```

- [ ] **Step 5: Pastikan build sukses**

Run: `npm run build`
Expected: selesai tanpa error

- [ ] **Step 6: Verifikasi manual di browser**

Jalankan `composer dev`. Pada tour hotel dengan invoice belum disetujui:
- mode kamar → kolom NAMA HOTEL muncul paling kiri, terisi dan bertahan setelah halaman dimuat ulang
- mode pax → kolom "Hotel / Room" muncul, kolom NAMA HOTEL tidak
- tour non-hotel → tidak ada satu pun dari keduanya

- [ ] **Step 7: Commit**

```bash
git add resources/js/Components/Tours/InvoicesPanel.vue
git commit -m "feat: kolom Nama Hotel per baris kamar dan Hotel/Room untuk mode pax"
```

---

### Task 10: Verifikasi menyeluruh

**Files:** tidak ada perubahan kode

- [ ] **Step 1: Jalankan seluruh test suite**

Run: `php artisan test`
Expected: PASS

- [ ] **Step 2: Pastikan build produksi sukses**

Run: `npm run build`
Expected: selesai tanpa error

- [ ] **Step 3: Buktikan migrasi bolak-balik**

Run:
```bash
T=/tmp/mig-$$.sqlite && touch $T
DB_CONNECTION=sqlite DB_DATABASE=$T php artisan migrate --force
DB_CONNECTION=sqlite DB_DATABASE=$T php artisan migrate:rollback --step=1 --force
DB_CONNECTION=sqlite DB_DATABASE=$T php artisan migrate --force
rm -f $T
```
Expected: ketiganya sukses tanpa error

- [ ] **Step 4: Pastikan tidak ada sisa method lama**

Run: `grep -rn "chargeLinesDateFirstInPdf\|dateFirstLines" app resources tests`
Expected: tidak ada hasil sama sekali

- [ ] **Step 5: Pastikan berkas tak berhubungan tidak ikut ter-commit**

Run: `git status --short`
Expected: hanya `docs/design-system/13-my-jobs-manifest.md`, `resources/js/Pages/Finance/Loans.vue`, dan `docs/superpowers/specs/2026-08-12-invoice-hotel-dua-mode-hitung-design.md`
