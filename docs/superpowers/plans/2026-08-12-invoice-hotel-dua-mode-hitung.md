# Dua Cara Hitung Invoice Hotel — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Sales memilih per invoice hotel antara harga per pax (perilaku sekarang) dan harga per kamar per malam yang total serta tampilannya mengikuti gaya invoice rental.

**Architecture:** Mode disimpan di kolom baru `invoices.pricing_mode` (nullable, `null` = per pax). Mode memilih **kelas aturan yang berbeda** — `HotelPerPaxRule` atau `HotelPerRoomNightRule` — lewat `SalesLineRuleRegistry::forInvoice()`, bukan percabangan di dalam satu kelas. Nominal baris kamar dihitung backend dari `harga × kamar × malam` dengan malam diturunkan dari rentang tanggal, lalu ditulis ke key `amount` yang sudah ada, sehingga total, PDF, dan Rincian Profit membacanya seperti biasa.

**Tech Stack:** Laravel 11 + Inertia + Vue 3, PHPUnit, Blade + mPDF, Tailwind.

**Spec:** `docs/superpowers/specs/2026-08-12-invoice-hotel-dua-mode-hitung-design.md`

**Branch:** `feat/invoice-hotel-pricing-mode` (sudah dibuat dari `dev`)

## Global Constraints

- **`pricing_mode` bernilai `null` HARUS berperilaku persis seperti `'per_pax'`.** Seluruh invoice hotel yang sudah ada bernilai `null`, dan tidak satu pun boleh berubah nominalnya. Tidak ada backfill.
- **Migrasi hanya menambah** satu kolom nullable. Tidak mengubah, tidak menghapus, tidak menulis ulang data. Sesuai §7.1 `docs/desain/pemisahan-invoice-per-jenis.md`.
- **Nilai `pricing_mode` yang dikenal hanya `'per_pax'` dan `'per_room_night'`.** Nilai lain diperlakukan sebagai `'per_pax'`.
- **Backend adalah otoritas nominal.** `amount` baris kamar SELALU dihitung ulang di server dari `unit_price × rooms × malam`, menimpa apa pun yang dikirim browser. Frontend menghitung hanya untuk ditampilkan.
- **Malam = `date_end` − `date`** (check-in 15/08, check-out 17/08 = 2 malam). Bukan jumlah hari yang tersentuh.
- **Tidak menebak.** `date_end` kosong/lebih awal dari `date`, `rooms` kosong/0, atau `unit_price` kosong/0 → `amount` = 0. Tidak ada yang diam-diam dianggap 1 malam atau 1 kamar.
- **Penanda tiga jenis baris `description_lines` adalah kehadiran key, bukan nilainya:** tanpa `amount` = baris deskripsi; ada `amount` tanpa `rooms` = biaya tambahan (nominal diketik); ada `amount` DAN `rooms` = baris kamar (nominal dihitung). Baris kamar selalu menulis `rooms` dan `unit_price` bersamaan meski kosong; baris biaya tambahan tidak pernah menulis keduanya.
- **Jenis penjualan — dan sekarang mode — hanya dipilih di `SalesLineRuleRegistry` beserta kelas aturannya.** Tidak ada `if ($type === 'hotel')` atau `type === 'hotel'` di controller, model, blade, maupun komponen Vue.
- **`costingSource()` tetap `'tour_items'` untuk kedua kelas hotel.** Tidak diubah jadi `'invoice_items'` seperti rental.
- **Tidak menyentuh kolom `sales_line` maupun `billing_quantities`.** Pembacaan jenis tetap lewat `tour->type`, sehingga kewajiban serah-terima §3.4.2 tetap di luar cakupan.
- **Repo ini menulis komentar dan nama method test dalam bahasa Indonesia.**
- **Menjalankan test:** `php artisan test --filter=<NamaTest>` dari root `/Users/marchelinoraco/Documents/2026/erp_wm`.
- **Tidak ada test runner JavaScript.** Verifikasi perubahan Vue lewat `npm run build` (harus sukses) plus pemeriksaan manual.
- **Jangan meng-commit** `docs/design-system/13-my-jobs-manifest.md` dan `resources/js/Pages/Finance/Loans.vue` — dua berkas termodifikasi yang tidak berhubungan. Selalu `git add` berkas secara eksplisit, jangan `git add -A`.

## File Structure

| Berkas | Tanggung jawab | Aksi |
| --- | --- | --- |
| `database/migrations/2026_08_12_000000_add_pricing_mode_to_invoices.php` | Kolom `pricing_mode` | Buat |
| `app/Models/Invoice.php` | Konstanta mode + atribut `rules` | Modifikasi |
| `app/Contracts/SalesLineInvoiceRule.php` | Kontrak + `pricingModes()` | Modifikasi |
| `app/Services/SalesLine/BaseSalesLineRule.php` | Default `pricingModes()` kosong | Modifikasi |
| `app/Services/SalesLine/HotelPerRoomNightRule.php` | Aturan mode kamar (eks `HotelRule`) | Ganti nama |
| `app/Services/SalesLine/HotelPerPaxRule.php` | Aturan mode pax (default) | Buat |
| `app/Services/SalesLine/SalesLineRuleRegistry.php` | `forInvoice()` + `payloadForInvoice()` | Modifikasi |
| `app/Support/RoomChargeLine.php` | Satu-satunya aturan hitung baris kamar | Buat |
| `app/Http/Controllers/InvoiceController.php` | Validasi + hitung ulang nominal | Modifikasi |
| `resources/js/Components/Tours/InvoicesPanel.vue` | Pemilih mode + dua blok bernominal | Modifikasi |
| `tests/Support/FakeSalesLineRule.php` | Ikut kontrak baru | Modifikasi |
| `tests/Feature/SalesLine/RuleLabelsAndMultipliersTest.php` | Menunjuk nama kelas baru | Modifikasi |
| `tests/Unit/Support/RoomChargeLineTest.php` | Mengunci aturan hitung | Buat |
| `tests/Feature/SalesLine/HotelPricingModeRuleTest.php` | Pemilihan kelas per mode | Buat |
| `tests/Feature/Invoice/HotelPricingModeTest.php` | Alur HTTP + total + PDF | Buat |

---

### Task 1: Kolom `pricing_mode` dan konstantanya

**Files:**
- Create: `database/migrations/2026_08_12_000000_add_pricing_mode_to_invoices.php`
- Modify: `app/Models/Invoice.php`
- Test: `tests/Feature/Invoice/HotelPricingModeTest.php`

**Interfaces:**
- Consumes: tidak ada
- Produces: kolom `invoices.pricing_mode` (string nullable); `Invoice::PRICING_PER_PAX = 'per_pax'`, `Invoice::PRICING_PER_ROOM_NIGHT = 'per_room_night'`, `Invoice::PRICING_MODES = ['per_pax', 'per_room_night']`

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/Invoice/HotelPricingModeTest.php`:

```php
<?php

namespace Tests\Feature\Invoice;

use App\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

/**
 * Dua cara hitung invoice hotel. Pengunci terpenting di berkas ini adalah
 * bahwa pricing_mode NULL — keadaan seluruh invoice hotel yang sudah ada —
 * berperilaku persis seperti sebelum fitur ini ada.
 */
class HotelPricingModeTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    public function test_invoice_baru_bernilai_null_dan_kolomnya_ada(): void
    {
        $invoice = $this->makeInvoice($this->makeTour('hotel', ['pax' => 4]), 1_000_000);

        $this->assertNull($invoice->pricing_mode, 'Invoice baru tidak memilih mode apa pun sampai sales memilihnya');
    }

    public function test_konstanta_mode_terdefinisi(): void
    {
        $this->assertSame('per_pax', Invoice::PRICING_PER_PAX);
        $this->assertSame('per_room_night', Invoice::PRICING_PER_ROOM_NIGHT);
        $this->assertSame(['per_pax', 'per_room_night'], Invoice::PRICING_MODES);
    }

    public function test_mode_tersimpan_dan_terbaca_ulang(): void
    {
        $invoice = $this->makeInvoice($this->makeTour('hotel', ['pax' => 4]), 1_000_000);

        $invoice->update(['pricing_mode' => Invoice::PRICING_PER_ROOM_NIGHT]);

        $this->assertSame('per_room_night', $invoice->fresh()->pricing_mode);
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=HotelPricingModeTest`
Expected: FAIL — `Undefined constant App\Models\Invoice::PRICING_PER_PAX`, dan kolom `pricing_mode` belum ada.

- [ ] **Step 3: Buat migrasi**

Buat `database/migrations/2026_08_12_000000_add_pricing_mode_to_invoices.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cara hitung invoice: 'per_pax' atau 'per_room_night'.
 *
 * Nullable dan TIDAK di-backfill. NULL berarti 'per_pax' — perilaku yang
 * sudah berjalan — sehingga seluruh invoice yang sudah ada mempertahankan
 * nominalnya tanpa satu baris pun ditulis ulang (§7.1 protokol keamanan data:
 * migrasi hanya menambah).
 *
 * Saat ini hanya jenis hotel yang punya lebih dari satu mode; jenis lain
 * mengabaikan kolom ini.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('pricing_mode')->nullable()->after('sales_line');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('pricing_mode');
        });
    }
};
```

- [ ] **Step 4: Tambah konstanta ke model**

Di `app/Models/Invoice.php`, tepat setelah `use SoftDeletes;` dan sebelum `protected $guarded = [];`, tambahkan:

```php
    /** Cara hitung total invoice. NULL di database diperlakukan sebagai PRICING_PER_PAX. */
    public const PRICING_PER_PAX        = 'per_pax';
    public const PRICING_PER_ROOM_NIGHT = 'per_room_night';
    public const PRICING_MODES          = [self::PRICING_PER_PAX, self::PRICING_PER_ROOM_NIGHT];
```

- [ ] **Step 5: Jalankan test, pastikan lulus**

Run: `php artisan test --filter=HotelPricingModeTest`
Expected: PASS — 3 test hijau

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_12_000000_add_pricing_mode_to_invoices.php \
        app/Models/Invoice.php \
        tests/Feature/Invoice/HotelPricingModeTest.php
git commit -m "feat: kolom pricing_mode pada invoices"
```

---

### Task 2: Dua kelas aturan hotel dan `pricingModes()`

`HotelRule` yang ada sekarang sudah mendeskripsikan mode kamar/malam — label `'Harga / kamar / malam'` dan pengali kamar × malam. Kelas itu diganti nama, bukan ditulis ulang; assertion yang menguncinya di `RuleLabelsAndMultipliersTest` tetap berlaku apa adanya dan hanya menunjuk nama baru.

**Files:**
- Rename: `app/Services/SalesLine/HotelRule.php` → `app/Services/SalesLine/HotelPerRoomNightRule.php`
- Create: `app/Services/SalesLine/HotelPerPaxRule.php`
- Modify: `app/Contracts/SalesLineInvoiceRule.php`
- Modify: `app/Services/SalesLine/BaseSalesLineRule.php`
- Modify: `app/Services/SalesLine/SalesLineRuleRegistry.php`
- Modify: `tests/Support/FakeSalesLineRule.php`
- Modify: `tests/Feature/SalesLine/RuleLabelsAndMultipliersTest.php`
- Test: `tests/Feature/SalesLine/HotelPricingModeRuleTest.php`

**Interfaces:**
- Consumes: `Invoice::PRICING_MODES` dari Task 1
- Produces:
  - `SalesLineInvoiceRule::pricingModes(): array`
  - `HotelPerPaxRule` — `totalComposition() === 'per_unit'`, `chargeLinesDateFirstInPdf() === false`, `unitPriceLabel() === 'Harga / pax'`
  - `HotelPerRoomNightRule` — `totalComposition() === 'line_items'`, `chargeLinesDateFirstInPdf() === true`, `unitPriceLabel() === 'Harga / kamar / malam'`
  - `SalesLineRuleRegistry::for('hotel')` mengembalikan `HotelPerPaxRule`

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/SalesLine/HotelPricingModeRuleTest.php`:

```php
<?php

namespace Tests\Feature\SalesLine;

use App\Services\SalesLine\HotelPerPaxRule;
use App\Services\SalesLine\HotelPerRoomNightRule;
use App\Services\SalesLine\SalesLineRuleRegistry;
use Tests\TestCase;

/**
 * Hotel adalah satu-satunya jenis dengan lebih dari satu cara hitung. Mode
 * memilih KELAS yang berbeda, bukan mencabangkan satu kelas — sehingga tiap
 * kelas tetap menjawab satu pertanyaan dan bisa diuji sendiri.
 */
class HotelPricingModeRuleTest extends TestCase
{
    /** Kunci: default registry untuk 'hotel' adalah mode pax, yaitu perilaku yang sudah berjalan. */
    public function test_registry_untuk_hotel_default_ke_mode_pax(): void
    {
        $this->assertInstanceOf(
            HotelPerPaxRule::class,
            app(SalesLineRuleRegistry::class)->for('hotel')
        );
    }

    public function test_mode_pax_memakai_komposisi_per_unit_dan_tata_letak_pdf_lama(): void
    {
        $aturan = new HotelPerPaxRule();

        $this->assertSame('per_unit', $aturan->totalComposition());
        $this->assertFalse($aturan->chargeLinesDateFirstInPdf());
        $this->assertSame('Harga / pax', $aturan->unitPriceLabel());
    }

    public function test_mode_kamar_menyusun_total_dari_baris_dan_pakai_tata_letak_rental(): void
    {
        $aturan = new HotelPerRoomNightRule();

        $this->assertSame('line_items', $aturan->totalComposition());
        $this->assertTrue($aturan->chargeLinesDateFirstInPdf());
        $this->assertSame('Harga / kamar / malam', $aturan->unitPriceLabel());
    }

    public function test_kedua_mode_tetap_memakai_rentang_tanggal_dan_costing_tour_items(): void
    {
        // Kotak tanggal mulai & selesai pada baris bernominal hotel sudah ada
        // sebelum fitur ini dan tidak dicabut. costingSource sengaja TIDAK
        // diubah jadi invoice_items seperti rental — paket hotel tetap disusun
        // di muka.
        foreach ([new HotelPerPaxRule(), new HotelPerRoomNightRule()] as $aturan) {
            $this->assertTrue($aturan->chargeLinesUseDateRange(), get_class($aturan));
            $this->assertSame('tour_items', $aturan->costingSource(), get_class($aturan));
            $this->assertFalse($aturan->profitFromRevenue(), get_class($aturan));
        }
    }

    /** Peta harapan eksplisit: jenis baru di registry wajib memutuskan, bukan lolos diam-diam. */
    private const HARAPAN_MODE = [
        'hotel'     => ['per_pax', 'per_room_night'],
        'tour'      => [],
        'guide'     => [],
        'rental'    => [],
        'mice'      => [],
        'document'  => [],
        'ticketing' => [],
    ];

    public function test_hanya_hotel_yang_punya_pilihan_mode(): void
    {
        $registry = app(SalesLineRuleRegistry::class);

        foreach ($registry->keys() as $key) {
            $this->assertArrayHasKey(
                $key,
                self::HARAPAN_MODE,
                "Jenis {$key} terdaftar di registry tapi belum punya keputusan pricingModes di peta HARAPAN_MODE test ini."
            );

            $this->assertSame(self::HARAPAN_MODE[$key], $registry->for($key)->pricingModes(), "Jenis {$key}");
        }
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=HotelPricingModeRuleTest`
Expected: FAIL — `Class "App\Services\SalesLine\HotelPerPaxRule" not found`

- [ ] **Step 3: Tambah `pricingModes()` ke kontrak**

Di `app/Contracts/SalesLineInvoiceRule.php`, tambahkan setelah `chargeLinesDateFirstInPdf()`:

```php
    /**
     * Mode hitung yang boleh dipilih sales untuk jenis ini, urut tampil.
     *
     * Kosong = jenis ini hanya punya satu cara hitung, tidak ada yang perlu
     * dipilih. Frontend menampilkan pemilih mode dari daftar ini, sehingga
     * komponen tidak perlu bertanya "apakah jenisnya hotel?".
     *
     * @return string[] nilai dari Invoice::PRICING_MODES
     */
    public function pricingModes(): array;
```

- [ ] **Step 4: Default kosong di kelas dasar**

Di `app/Services/SalesLine/BaseSalesLineRule.php`, tambahkan setelah `chargeLinesDateFirstInPdf()`:

```php
    /** Mayoritas jenis hanya punya satu cara hitung. */
    public function pricingModes(): array
    {
        return [];
    }
```

- [ ] **Step 5: Ganti nama `HotelRule` menjadi `HotelPerRoomNightRule`**

```bash
git mv app/Services/SalesLine/HotelRule.php app/Services/SalesLine/HotelPerRoomNightRule.php
```

Lalu di berkas itu ganti nama kelasnya dan tambahkan tiga method, sehingga isinya menjadi:

```php
<?php

namespace App\Services\SalesLine;

use App\Models\Invoice;

/** Hotel mode kamar: harga per kamar per malam. Total disusun dari baris rincian kamar. */
final class HotelPerRoomNightRule extends BaseSalesLineRule
{
    public function unitPriceLabel(): string
    {
        return 'Harga / kamar / malam';
    }

    public function defaultMultipliers(Invoice $invoice): array
    {
        return [
            new Multiplier('rooms', 'Kamar', 1),
            new Multiplier('nights', 'Malam', $this->nightsOf($invoice)),
        ];
    }

    /**
     * Satu invoice hotel bisa memuat beberapa tipe kamar dengan periode dan
     * harga masing-masing, jadi totalnya dijumlah dari baris rincian — bukan
     * satu harga satuan dikali kuantitas.
     */
    public function totalComposition(): string
    {
        return 'line_items';
    }

    /** Yang pertama dicari customer adalah periode menginapnya, baru tipe kamarnya. */
    public function chargeLinesDateFirstInPdf(): bool
    {
        return true;
    }

    public function pricingModes(): array
    {
        return Invoice::PRICING_MODES;
    }
}
```

- [ ] **Step 6: Buat `HotelPerPaxRule`**

Buat `app/Services/SalesLine/HotelPerPaxRule.php`:

```php
<?php

namespace App\Services\SalesLine;

use App\Models\Invoice;

/**
 * Hotel mode pax: satu harga dikali jumlah peserta — cara hitung yang selama
 * ini benar-benar berjalan untuk hotel, dan tetap menjadi default agar seluruh
 * invoice lama (pricing_mode NULL) tidak berubah nominalnya.
 */
final class HotelPerPaxRule extends BaseSalesLineRule
{
    public function unitPriceLabel(): string
    {
        return 'Harga / pax';
    }

    public function defaultMultipliers(Invoice $invoice): array
    {
        return [new Multiplier('pax', 'Peserta', $this->paxOf($invoice))];
    }

    public function pricingModes(): array
    {
        return Invoice::PRICING_MODES;
    }
}
```

- [ ] **Step 7: Daftarkan mode pax sebagai default hotel**

Di `app/Services/SalesLine/SalesLineRuleRegistry.php`, ganti baris `'hotel' => new HotelRule(),` menjadi:

```php
            // Hotel punya dua cara hitung. Yang terdaftar di sini adalah
            // default-nya (pricing_mode NULL); forInvoice() memilih kelas
            // satunya saat invoice memintanya.
            'hotel'     => new HotelPerPaxRule(),
```

- [ ] **Step 8: Ikuti kontrak baru di test double**

Di `tests/Support/FakeSalesLineRule.php`, tambahkan parameter konstruktor setelah `$chargeLinesDateFirstInPdf`:

```php
        private array $pricingModes = [],
```

dan tambahkan method di akhir kelas:

```php
    public function pricingModes(): array
    {
        return $this->pricingModes;
    }
```

- [ ] **Step 9: Arahkan test lama ke nama kelas baru**

Di `tests/Feature/SalesLine/RuleLabelsAndMultipliersTest.php`, ganti `use App\Services\SalesLine\HotelRule;` menjadi `use App\Services\SalesLine\HotelPerRoomNightRule;`, lalu ganti seluruh `new HotelRule()` menjadi `new HotelPerRoomNightRule()`. Isi assertion-nya JANGAN diubah — label dan pengali kamar × malam memang tetap milik kelas ini.

- [ ] **Step 10: Jalankan test, pastikan lulus**

Run: `php artisan test --filter=HotelPricingModeRuleTest`
Expected: PASS — 5 test hijau

- [ ] **Step 11: Jalankan seluruh test SalesLine sebagai jaring pengaman**

Run: `php artisan test --filter=SalesLine`
Expected: PASS — menambah method ke kontrak akan menggagalkan implementasi mana pun yang terlewat

- [ ] **Step 12: Commit**

```bash
git add app/Contracts/SalesLineInvoiceRule.php \
        app/Services/SalesLine/BaseSalesLineRule.php \
        app/Services/SalesLine/HotelPerRoomNightRule.php \
        app/Services/SalesLine/HotelPerPaxRule.php \
        app/Services/SalesLine/SalesLineRuleRegistry.php \
        tests/Support/FakeSalesLineRule.php \
        tests/Feature/SalesLine/RuleLabelsAndMultipliersTest.php \
        tests/Feature/SalesLine/HotelPricingModeRuleTest.php
git commit -m "feat: dua kelas aturan hotel dan daftar mode hitung per jenis"
```

---

### Task 3: `forInvoice()` dan payload aturan per invoice

**Files:**
- Modify: `app/Services/SalesLine/SalesLineRuleRegistry.php`
- Test: `tests/Feature/SalesLine/HotelPricingModeRuleTest.php` (tambah test)

**Interfaces:**
- Consumes: `HotelPerPaxRule`, `HotelPerRoomNightRule` dari Task 2
- Produces:
  - `SalesLineRuleRegistry::forInvoice(?Invoice $invoice): SalesLineInvoiceRule`
  - `SalesLineRuleRegistry::payloadForInvoice(?Invoice $invoice): array` — bentuk sama dengan `payloadFor()` ditambah key `pricingModes` dan `pricingMode`

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan ke `tests/Feature/SalesLine/HotelPricingModeRuleTest.php`. Kelas ini belum memakai database, jadi tambahkan dulu dua baris berikut tepat di bawah pembuka kelas:

```php
    use \Illuminate\Foundation\Testing\RefreshDatabase;
    use \Tests\Support\CreatesSalesFixtures;
```

Lalu tambahkan test berikut di akhir kelas:

```php
    public function test_for_invoice_memilih_kelas_sesuai_mode(): void
    {
        $tour = $this->makeTour('hotel', ['pax' => 4]);

        $harapan = [
            null                              => HotelPerPaxRule::class,
            'per_pax'                         => HotelPerPaxRule::class,
            'per_room_night'                  => HotelPerRoomNightRule::class,
            'mode-yang-tidak-pernah-ada'      => HotelPerPaxRule::class,
        ];

        foreach ($harapan as $mode => $kelas) {
            $invoice = $this->makeInvoice($tour, 1_000_000);
            $invoice->update(['pricing_mode' => $mode === '' ? null : $mode]);

            $this->assertInstanceOf(
                $kelas,
                app(SalesLineRuleRegistry::class)->forInvoice($invoice->fresh()),
                'Mode ' . var_export($mode, true)
            );
        }
    }

    public function test_for_invoice_tanpa_invoice_jatuh_ke_aturan_tour(): void
    {
        $this->assertInstanceOf(
            \App\Services\SalesLine\TourRule::class,
            app(SalesLineRuleRegistry::class)->forInvoice(null)
        );
    }

    public function test_mode_tidak_mempengaruhi_jenis_selain_hotel(): void
    {
        // Kolomnya berlaku umum secara teknis, tapi hanya hotel yang punya
        // lebih dari satu mode. Nilai nyasar pada jenis lain tidak boleh
        // menggeser aturannya.
        $invoice = $this->makeInvoice($this->makeTour('tour', ['pax' => 4]), 1_000_000);
        $invoice->update(['pricing_mode' => 'per_room_night']);

        $aturan = app(SalesLineRuleRegistry::class)->forInvoice($invoice->fresh());

        $this->assertInstanceOf(\App\Services\SalesLine\TourRule::class, $aturan);
        $this->assertSame('per_unit', $aturan->totalComposition());
    }

    public function test_payload_per_invoice_memuat_mode_aktif_dan_pilihannya(): void
    {
        $invoice = $this->makeInvoice($this->makeTour('hotel', ['pax' => 4]), 1_000_000);
        $invoice->update(['pricing_mode' => 'per_room_night']);

        $payload = app(SalesLineRuleRegistry::class)->payloadForInvoice($invoice->fresh());

        $this->assertSame('hotel', $payload['key']);
        $this->assertSame('line_items', $payload['totalComposition']);
        $this->assertSame(['per_pax', 'per_room_night'], $payload['pricingModes']);
        $this->assertSame('per_room_night', $payload['pricingMode']);
        $this->assertTrue($payload['chargeLinesUseDateRange']);
    }

    public function test_payload_per_invoice_melaporkan_mode_pax_saat_kolomnya_null(): void
    {
        $invoice = $this->makeInvoice($this->makeTour('hotel', ['pax' => 4]), 1_000_000);

        $payload = app(SalesLineRuleRegistry::class)->payloadForInvoice($invoice->fresh());

        $this->assertSame('per_pax', $payload['pricingMode'], 'NULL dilaporkan sebagai per_pax, bukan null');
        $this->assertSame('per_unit', $payload['totalComposition']);
    }
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=HotelPricingModeRuleTest`
Expected: FAIL — `Call to undefined method ...::forInvoice()`

- [ ] **Step 3: Tulis `forInvoice()` dan `payloadForInvoice()`**

Di `app/Services/SalesLine/SalesLineRuleRegistry.php`, tambahkan `use App\Models\Invoice;` di bagian atas, lalu tambahkan kedua method berikut setelah `for()`:

```php
    /**
     * Aturan untuk satu invoice — memperhitungkan cara hitung yang dipilih
     * sales, bukan hanya jenis penjualannya.
     *
     * Inilah satu-satunya tempat mode diterjemahkan menjadi kelas aturan.
     * Mode tak dikenal dan invoice yang tidak ada sama-sama jatuh ke perilaku
     * yang sudah berjalan, sehingga data nyasar tidak pernah menggeser nominal.
     */
    public function forInvoice(?Invoice $invoice): SalesLineInvoiceRule
    {
        $rule = $this->for($invoice?->tour?->type ?? 'tour');

        if ($rule instanceof HotelPerPaxRule && $invoice?->pricing_mode === Invoice::PRICING_PER_ROOM_NIGHT) {
            return new HotelPerRoomNightRule();
        }

        return $rule;
    }

    /**
     * Payload aturan milik SATU invoice. Mode adalah milik invoice, bukan
     * tour, dan satu tour bisa memuat beberapa invoice dengan mode berbeda —
     * jadi payloadFor() tingkat tour tidak cukup.
     *
     * @return array{key: string, profitFromRevenue: bool, totalComposition: string, chargeLinesUseDateRange: bool, pricingModes: string[], pricingMode: string}
     */
    public function payloadForInvoice(?Invoice $invoice): array
    {
        $key  = $invoice?->tour?->type ?? 'tour';
        $rule = $this->forInvoice($invoice);

        return [
            'key'                     => $key,
            'profitFromRevenue'       => $rule->profitFromRevenue(),
            'totalComposition'        => $rule->totalComposition(),
            'chargeLinesUseDateRange' => $rule->chargeLinesUseDateRange(),
            'pricingModes'            => $rule->pricingModes(),
            // NULL dilaporkan sebagai per_pax supaya frontend tidak perlu tahu
            // bahwa ketiadaan nilai berarti mode default.
            'pricingMode'             => $invoice?->pricing_mode ?? Invoice::PRICING_PER_PAX,
        ];
    }
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter=HotelPricingModeRuleTest`
Expected: PASS — 10 test hijau

- [ ] **Step 5: Commit**

```bash
git add app/Services/SalesLine/SalesLineRuleRegistry.php \
        tests/Feature/SalesLine/HotelPricingModeRuleTest.php
git commit -m "feat: registry memilih aturan berdasarkan mode hitung invoice"
```

---

### Task 4: Aturan hitung baris kamar

Helper murni tanpa ketergantungan Eloquent, sehingga bisa diuji cepat dan dipanggil dari controller maupun model tanpa menyiapkan database.

**Files:**
- Create: `app/Support/RoomChargeLine.php`
- Test: `tests/Unit/Support/RoomChargeLineTest.php`

**Interfaces:**
- Consumes: tidak ada
- Produces:
  - `App\Support\RoomChargeLine::isRoomLine(array $line): bool`
  - `App\Support\RoomChargeLine::nights(array $line): int`
  - `App\Support\RoomChargeLine::amount(array $line): float`
  - `App\Support\RoomChargeLine::recalculate(array $lines): array`

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Unit/Support/RoomChargeLineTest.php`:

```php
<?php

namespace Tests\Unit\Support;

use App\Support\RoomChargeLine;
use PHPUnit\Framework\TestCase;

/**
 * Aturan hitung baris kamar invoice hotel (spec §4).
 *
 * Dua hal yang dikunci di sini: penanda baris kamar adalah KEHADIRAN key
 * `rooms` (bukan nilainya), dan tidak ada keadaan kosong yang ditebak menjadi
 * 1 malam atau 1 kamar — semuanya menghasilkan 0 agar sales melihat sendiri
 * apa yang belum diisi.
 */
class RoomChargeLineTest extends TestCase
{
    public function test_penanda_baris_kamar_adalah_kehadiran_key_rooms(): void
    {
        $this->assertTrue(RoomChargeLine::isRoomLine(['amount' => 0, 'rooms' => 2]));
        // Nilai kosong tetap baris kamar — sama seperti `amount` 0 yang tetap
        // baris bernominal. Kalau tidak, baris kamar yang baru diketik
        // tanggalnya akan turun pangkat jadi biaya tambahan setelah disimpan.
        $this->assertTrue(RoomChargeLine::isRoomLine(['amount' => 0, 'rooms' => '']));
        $this->assertTrue(RoomChargeLine::isRoomLine(['amount' => 0, 'rooms' => null]));

        $this->assertFalse(RoomChargeLine::isRoomLine(['amount' => 500000]));
        $this->assertFalse(RoomChargeLine::isRoomLine(['label' => 'Hotel', 'date' => '13-15 Aug']));
    }

    public function test_malam_adalah_selisih_hari(): void
    {
        $this->assertSame(2, RoomChargeLine::nights(['date' => '2026-08-15', 'date_end' => '2026-08-17']));
        $this->assertSame(1, RoomChargeLine::nights(['date' => '2026-08-17', 'date_end' => '2026-08-18']));
        $this->assertSame(31, RoomChargeLine::nights(['date' => '2026-12-15', 'date_end' => '2027-01-15']));
    }

    public function test_malam_nol_untuk_tanggal_yang_tidak_membentuk_rentang(): void
    {
        $this->assertSame(0, RoomChargeLine::nights(['date' => '2026-08-15']));
        $this->assertSame(0, RoomChargeLine::nights(['date' => '2026-08-15', 'date_end' => '']));
        $this->assertSame(0, RoomChargeLine::nights(['date' => '2026-08-15', 'date_end' => '2026-08-15']));
        $this->assertSame(0, RoomChargeLine::nights(['date' => '2026-08-17', 'date_end' => '2026-08-15']));
        $this->assertSame(0, RoomChargeLine::nights([]));
    }

    public function test_malam_nol_untuk_tanggal_yang_bukan_iso(): void
    {
        // Baris deskripsi memakai teks bebas di kolom yang sama; jangan sampai
        // melempar exception yang menggagalkan penyimpanan seluruh invoice.
        $this->assertSame(0, RoomChargeLine::nights(['date' => '13-15 Aug 2026', 'date_end' => 'entah']));
        $this->assertSame(0, RoomChargeLine::nights(['date' => '2026-02-30', 'date_end' => '2026-03-02']));
    }

    public function test_nominal_adalah_harga_kali_kamar_kali_malam(): void
    {
        $baris = ['rooms' => 2, 'unit_price' => 1_500_000, 'date' => '2026-08-15', 'date_end' => '2026-08-17'];

        $this->assertSame(6_000_000.0, RoomChargeLine::amount($baris));
    }

    public function test_nominal_nol_untuk_tiap_isian_yang_kosong(): void
    {
        $lengkap = ['rooms' => 2, 'unit_price' => 1_500_000, 'date' => '2026-08-15', 'date_end' => '2026-08-17'];

        $this->assertSame(0.0, RoomChargeLine::amount(array_merge($lengkap, ['rooms' => 0])));
        $this->assertSame(0.0, RoomChargeLine::amount(array_merge($lengkap, ['rooms' => ''])));
        $this->assertSame(0.0, RoomChargeLine::amount(array_merge($lengkap, ['unit_price' => 0])));
        $this->assertSame(0.0, RoomChargeLine::amount(array_merge($lengkap, ['unit_price' => ''])));
        $this->assertSame(0.0, RoomChargeLine::amount(array_merge($lengkap, ['date_end' => ''])));
    }

    public function test_recalculate_menimpa_nominal_baris_kamar(): void
    {
        // Browser boleh mengirim nominal apa pun; server yang menentukan.
        $hasil = RoomChargeLine::recalculate([
            ['label' => 'Deluxe', 'rooms' => 2, 'unit_price' => 1_500_000, 'date' => '2026-08-15', 'date_end' => '2026-08-17', 'amount' => 999],
        ]);

        $this->assertSame(6_000_000.0, $hasil[0]['amount']);
    }

    public function test_recalculate_tidak_menyentuh_baris_lain(): void
    {
        $hasil = RoomChargeLine::recalculate([
            ['label' => 'Antar-jemput', 'detail' => 'PP bandara', 'amount' => 750_000],
            ['label' => 'Hotel', 'date' => '13-15 Aug 2026', 'detail' => 'Deluxe Room'],
        ]);

        $this->assertSame(750_000, $hasil[0]['amount'], 'Biaya tambahan tetap nominal yang diketik sales');
        $this->assertArrayNotHasKey('amount', $hasil[1], 'Baris deskripsi tidak mendapat amount');
    }

    public function test_recalculate_mempertahankan_urutan_dan_key_lain(): void
    {
        $hasil = RoomChargeLine::recalculate([
            ['label' => 'Deluxe', 'detail' => 'Twin bed', 'rooms' => 1, 'unit_price' => 1_000_000, 'date' => '2026-08-15', 'date_end' => '2026-08-16'],
            ['label' => 'Antar-jemput', 'amount' => 750_000],
        ]);

        $this->assertCount(2, $hasil);
        $this->assertSame('Deluxe', $hasil[0]['label']);
        $this->assertSame('Twin bed', $hasil[0]['detail']);
        $this->assertSame('Antar-jemput', $hasil[1]['label']);
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=RoomChargeLineTest`
Expected: FAIL — `Class "App\Support\RoomChargeLine" not found`

- [ ] **Step 3: Tulis implementasinya**

Buat `app/Support/RoomChargeLine.php`:

```php
<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Aturan hitung baris kamar pada invoice hotel bermode per kamar per malam.
 *
 * Baris kamar dikenali dari KEHADIRAN key `rooms`, bukan nilainya — meneruskan
 * cara yang sudah dipakai `amount` untuk memisahkan baris bernominal dari
 * baris deskripsi. Baris yang baru diketik tanggalnya, dengan kamar masih
 * kosong, tetap baris kamar.
 *
 * Jumlah malam tidak pernah disimpan: ia selalu diturunkan dari `date` dan
 * `date_end` di baris yang sama, sehingga tidak mungkin berselisih dengan
 * tanggal yang tertulis.
 */
final class RoomChargeLine
{
    public static function isRoomLine(array $line): bool
    {
        return array_key_exists('rooms', $line);
    }

    /** Selisih hari check-in ke check-out. 0 bila tidak membentuk rentang yang sah. */
    public static function nights(array $line): int
    {
        $mulai   = self::tanggal($line['date'] ?? null);
        $selesai = self::tanggal($line['date_end'] ?? null);

        if (! $mulai || ! $selesai || $selesai <= $mulai) {
            return 0;
        }

        return (int) $mulai->diffInDays($selesai);
    }

    /** harga per kamar per malam × jumlah kamar × jumlah malam. */
    public static function amount(array $line): float
    {
        $harga = (float) ($line['unit_price'] ?? 0);
        $kamar = (int) ($line['rooms'] ?? 0);

        return $harga * $kamar * self::nights($line);
    }

    /**
     * Menulis ulang `amount` setiap baris kamar. Baris biaya tambahan dan
     * baris deskripsi dikembalikan apa adanya.
     *
     * Dipanggil di server SEBELUM menyimpan, sehingga nominal yang dikirim
     * browser tidak pernah menjadi sumber kebenaran uang.
     */
    public static function recalculate(array $lines): array
    {
        return array_map(function (array $line) {
            if (self::isRoomLine($line)) {
                $line['amount'] = self::amount($line);
            }

            return $line;
        }, $lines);
    }

    /** Hanya menerima YYYY-MM-DD yang benar-benar ada di kalender. */
    private static function tanggal(?string $nilai): ?Carbon
    {
        $nilai = trim((string) $nilai);

        if ($nilai === '' || ! Carbon::hasFormat($nilai, 'Y-m-d')) {
            return null;
        }

        $tanggal = Carbon::createFromFormat('Y-m-d', $nilai)->startOfDay();

        // hasFormat() hanya memeriksa rentang angka, bukan kalender:
        // 2026-02-30 lolos lalu digulung jadi 2 Maret.
        return $tanggal->format('Y-m-d') === $nilai ? $tanggal : null;
    }
}
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter=RoomChargeLineTest`
Expected: PASS — 9 test hijau

- [ ] **Step 5: Commit**

```bash
git add app/Support/RoomChargeLine.php tests/Unit/Support/RoomChargeLineTest.php
git commit -m "feat: aturan hitung baris kamar invoice hotel"
```

---

### Task 5: Total invoice mengikuti mode

Ini jalur uang. `syncProformaTotal()` masih memilih aturan dari `tour->type` saja, sehingga hotel selalu `per_unit` berapa pun modenya.

**Files:**
- Modify: `app/Models/Invoice.php` (method `syncProformaTotal()`)
- Test: `tests/Feature/Invoice/HotelPricingModeTest.php` (tambah test)

**Interfaces:**
- Consumes: `SalesLineRuleRegistry::forInvoice()` dari Task 3
- Produces: total invoice hotel mode kamar = jumlah `amount` seluruh baris

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan ke `tests/Feature/Invoice/HotelPricingModeTest.php`:

```php
    /** Dua tipe kamar dengan periode berbeda — kasus yang mode ini layani. */
    private const BARIS_KAMAR = [
        ['label' => 'Deluxe', 'detail' => 'Twin bed', 'date' => '2026-08-15', 'date_end' => '2026-08-17', 'rooms' => 2, 'unit_price' => 1_500_000, 'amount' => 6_000_000],
        ['label' => 'Suite', 'detail' => 'King bed', 'date' => '2026-08-17', 'date_end' => '2026-08-18', 'rooms' => 1, 'unit_price' => 2_500_000, 'amount' => 2_500_000],
    ];

    public function test_mode_kamar_menjumlah_baris_dan_mengabaikan_harga_pax(): void
    {
        $tour    = $this->makeTour('hotel', ['pax' => 4]);
        $invoice = $this->makeInvoice($tour, 1_000_000);

        $invoice->update([
            'pricing_mode'      => Invoice::PRICING_PER_ROOM_NIGHT,
            'description_lines' => self::BARIS_KAMAR,
        ]);
        $invoice->fresh()->syncProformaTotal();

        // 1.000.000 × 4 pax = 4.000.000 SENGAJA tidak muncul di mana pun.
        $this->assertEquals(8_500_000, $invoice->fresh()->total);
    }

    public function test_mode_pax_tetap_harga_kali_pax(): void
    {
        // Pasangan pengunci: mode default tidak ikut berubah jadi penjumlahan baris.
        $tour    = $this->makeTour('hotel', ['pax' => 4]);
        $invoice = $this->makeInvoice($tour, 1_000_000);

        $this->assertEquals(4_000_000, $invoice->fresh()->total);
    }

    public function test_mode_kamar_menjumlah_baris_kamar_dan_biaya_tambahan(): void
    {
        $tour    = $this->makeTour('hotel', ['pax' => 4]);
        $invoice = $this->makeInvoice($tour, 1_000_000);

        $invoice->update([
            'pricing_mode'      => Invoice::PRICING_PER_ROOM_NIGHT,
            'description_lines' => array_merge(self::BARIS_KAMAR, [
                ['label' => 'Antar-jemput', 'detail' => 'PP bandara', 'amount' => 750_000],
            ]),
        ]);
        $invoice->fresh()->syncProformaTotal();

        $this->assertEquals(9_250_000, $invoice->fresh()->total, '8.500.000 kamar + 750.000 biaya tambahan');
    }

    public function test_mode_null_pada_hotel_identik_dengan_sebelum_fitur_ini(): void
    {
        // Pengunci terpenting: seluruh invoice hotel yang sudah ada bernilai
        // NULL, dan nominalnya tidak boleh bergeser sedikit pun.
        $tour    = $this->makeTour('hotel', ['pax' => 7]);
        $invoice = $this->makeInvoice($tour, 350_000);

        $invoice->update(['description_lines' => [
            ['label' => 'Dokumen', 'detail' => 'Visa', 'amount' => 200_000],
        ]]);
        $invoice->fresh()->syncProformaTotal();

        $this->assertNull($invoice->fresh()->pricing_mode);
        $this->assertEquals(2_650_000, $invoice->fresh()->total, '350.000 × 7 pax + 200.000');
    }
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=HotelPricingModeTest`
Expected: FAIL pada `test_mode_kamar_menjumlah_baris_dan_mengabaikan_harga_pax` — totalnya `12.500.000` (4.000.000 dari pax + 8.500.000 dari baris), bukan `8.500.000`, karena aturan masih `per_unit`.

- [ ] **Step 3: Pilih aturan lewat `forInvoice()`**

Di `app/Models/Invoice.php`, method `syncProformaTotal()`, ganti baris pemilihan aturan:

```php
        $rule = app(SalesLineRuleRegistry::class)->for($this->tour?->type ?? 'tour');
```

menjadi:

```php
        // forInvoice(), bukan for(): hotel punya dua cara hitung dan yang
        // menentukan adalah mode invoice ini, bukan jenis penjualannya saja.
        $rule = app(SalesLineRuleRegistry::class)->forInvoice($this);
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter=HotelPricingModeTest`
Expected: PASS — 7 test hijau

- [ ] **Step 5: Jalankan seluruh test Invoice dan SalesLine**

Run: `php artisan test --filter=Invoice && php artisan test --filter=SalesLine`
Expected: PASS — jalur uang menyentuh banyak test karakterisasi; semuanya harus tetap hijau

- [ ] **Step 6: Commit**

```bash
git add app/Models/Invoice.php tests/Feature/Invoice/HotelPricingModeTest.php
git commit -m "feat: total invoice hotel mengikuti mode hitung yang dipilih"
```

---

### Task 6: Menyimpan mode dan baris kamar lewat HTTP

**Files:**
- Modify: `app/Http/Controllers/InvoiceController.php` (method `updateProforma()`)
- Test: `tests/Feature/Invoice/HotelPricingModeTest.php` (tambah test)

**Interfaces:**
- Consumes: `RoomChargeLine::recalculate()` dari Task 4; `Invoice::PRICING_MODES` dari Task 1
- Produces: `pricing_mode`, `rooms`, dan `unit_price` bertahan melewati validasi; `amount` baris kamar selalu hasil hitungan server

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan ke `tests/Feature/Invoice/HotelPricingModeTest.php`:

```php
    public function test_mode_dan_isian_kamar_tersimpan_lewat_proforma(): void
    {
        $invoice = $this->makeInvoice($this->makeTour('hotel', ['pax' => 4]), 1_000_000);

        $this->actingAs($this->salesUser())
            ->patch(route('invoices.proforma', $invoice), [
                'currency'          => 'IDR',
                'unit_price'        => 1_000_000,
                'pricing_mode'      => 'per_room_night',
                'description_lines' => [
                    ['label' => 'Deluxe', 'detail' => 'Twin bed', 'date' => '2026-08-15', 'date_end' => '2026-08-17', 'rooms' => 2, 'unit_price' => 1_500_000, 'amount' => 0],
                ],
            ])
            ->assertRedirect();

        $segar = $invoice->fresh();
        $baris = $segar->description_lines[0];

        $this->assertSame('per_room_night', $segar->pricing_mode);
        $this->assertEquals(2, $baris['rooms']);
        $this->assertEquals(1_500_000, $baris['unit_price']);
    }

    public function test_nominal_baris_kamar_dihitung_server_bukan_dipercaya_dari_browser(): void
    {
        // Browser mengirim nominal yang mengada-ada; server harus menimpanya.
        $invoice = $this->makeInvoice($this->makeTour('hotel', ['pax' => 4]), 1_000_000);

        $this->actingAs($this->salesUser())
            ->patch(route('invoices.proforma', $invoice), [
                'currency'          => 'IDR',
                'unit_price'        => 1_000_000,
                'pricing_mode'      => 'per_room_night',
                'description_lines' => [
                    ['label' => 'Deluxe', 'date' => '2026-08-15', 'date_end' => '2026-08-17', 'rooms' => 2, 'unit_price' => 1_500_000, 'amount' => 1],
                ],
            ])
            ->assertRedirect();

        $segar = $invoice->fresh();

        $this->assertEquals(6_000_000, $segar->description_lines[0]['amount'], '2 kamar × 2 malam × 1.500.000');
        $this->assertEquals(6_000_000, $segar->total);
    }

    public function test_biaya_tambahan_mempertahankan_nominal_yang_diketik(): void
    {
        $invoice = $this->makeInvoice($this->makeTour('hotel', ['pax' => 4]), 1_000_000);

        $this->actingAs($this->salesUser())
            ->patch(route('invoices.proforma', $invoice), [
                'currency'          => 'IDR',
                'unit_price'        => 1_000_000,
                'pricing_mode'      => 'per_room_night',
                'description_lines' => [
                    ['label' => 'Antar-jemput', 'detail' => 'PP bandara', 'amount' => 750_000],
                ],
            ])
            ->assertRedirect();

        $baris = $invoice->fresh()->description_lines[0];

        $this->assertEquals(750_000, $baris['amount'], 'Tanpa key rooms, nominalnya tidak dihitung ulang');
        $this->assertArrayNotHasKey('rooms', $baris);
    }

    public function test_mode_tak_dikenal_ditolak_validasi(): void
    {
        $invoice = $this->makeInvoice($this->makeTour('hotel', ['pax' => 4]), 1_000_000);

        $this->actingAs($this->salesUser())
            ->patch(route('invoices.proforma', $invoice), [
                'currency'     => 'IDR',
                'unit_price'   => 1_000_000,
                'pricing_mode' => 'per_kucing',
            ])
            ->assertSessionHasErrors('pricing_mode');
    }

    public function test_invoice_yang_sudah_disetujui_menolak_ganti_mode(): void
    {
        $invoice = $this->makeInvoice($this->makeTour('hotel', ['pax' => 4]), 1_000_000);
        $disetujui = $this->approveInvoice($invoice);

        $this->actingAs($this->salesUser())
            ->patch(route('invoices.proforma', $disetujui), [
                'currency'     => 'IDR',
                'unit_price'   => 1_000_000,
                'pricing_mode' => 'per_room_night',
            ])
            ->assertForbidden();

        $this->assertNull($disetujui->fresh()->pricing_mode);
    }

    public function test_ganti_mode_bolak_balik_tidak_menghilangkan_data(): void
    {
        $invoice = $this->makeInvoice($this->makeTour('hotel', ['pax' => 4]), 1_000_000);
        $sales   = $this->salesUser();

        $barisKamar = [
            ['label' => 'Deluxe', 'date' => '2026-08-15', 'date_end' => '2026-08-17', 'rooms' => 2, 'unit_price' => 1_500_000, 'amount' => 0],
        ];

        $this->actingAs($sales)->patch(route('invoices.proforma', $invoice), [
            'currency' => 'IDR', 'unit_price' => 1_000_000,
            'pricing_mode' => 'per_room_night', 'description_lines' => $barisKamar,
        ])->assertRedirect();

        // Kembali ke mode pax — baris kamar TIDAK dihapus.
        $this->actingAs($sales)->patch(route('invoices.proforma', $invoice), [
            'currency' => 'IDR', 'unit_price' => 1_000_000,
            'pricing_mode' => 'per_pax', 'description_lines' => $barisKamar,
        ])->assertRedirect();

        $segar = $invoice->fresh();

        $this->assertSame('per_pax', $segar->pricing_mode);
        $this->assertEquals(1_000_000, $segar->unit_price, 'Harga/pax tetap utuh');
        $this->assertCount(1, $segar->description_lines, 'Baris kamar tetap tersimpan');
        $this->assertEquals(2, $segar->description_lines[0]['rooms']);
    }
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=HotelPricingModeTest`
Expected: FAIL — `pricing_mode`, `rooms`, dan `unit_price` dibuang validasi, jadi tidak tersimpan.

- [ ] **Step 3: Tambah aturan validasi**

Di `app/Http/Controllers/InvoiceController.php`, method `updateProforma()`, tambahkan tiga aturan pada array validasi — `pricing_mode` sejajar dengan `unit_price` di atas, dan dua sisanya tepat setelah `description_lines.*.date_end`:

```php
            'pricing_mode'                   => 'nullable|string|in:' . implode(',', Invoice::PRICING_MODES),
```

```php
            'description_lines.*.rooms'      => 'nullable|integer|min:0',
            'description_lines.*.unit_price' => 'nullable|numeric|min:0',
```

- [ ] **Step 4: Simpan mode dan hitung ulang nominal baris kamar**

Masih di `updateProforma()`, pada blok `$invoice->fill([...])`, ganti baris `description_lines` dan tambahkan `pricing_mode`:

```php
            // Nominal baris kamar SELALU dihitung server — nilai yang dikirim
            // browser hanya untuk ditampilkan dan tidak pernah dipercaya.
            'description_lines' => RoomChargeLine::recalculate(array_values($data['description_lines'] ?? [])),
            'pricing_mode'      => $data['pricing_mode'] ?? $invoice->pricing_mode,
```

Tambahkan `use App\Support\RoomChargeLine;` di bagian atas berkas bila belum ada. `App\Models\Invoice` sudah di-import.

- [ ] **Step 5: Jalankan test, pastikan lulus**

Run: `php artisan test --filter=HotelPricingModeTest`
Expected: PASS — 13 test hijau

- [ ] **Step 6: Jalankan seluruh test Invoice**

Run: `php artisan test --filter=Invoice`
Expected: PASS — `recalculate()` kini melewati SETIAP penyimpanan proforma untuk semua jenis; test karakterisasi yang ada membuktikan baris non-kamar tidak tersentuh

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/InvoiceController.php tests/Feature/Invoice/HotelPricingModeTest.php
git commit -m "feat: simpan mode hitung dan hitung ulang nominal baris kamar di server"
```

---

### Task 7: PDF mengikuti mode

Tata letak tanggal-di-kiri sudah ada dan digerbangi `chargeLinesDateFirstInPdf()`. Yang kurang: `InvoiceController::build()` masih menanyakannya lewat `for($type)`, bukan `forInvoice()`, sehingga mode invoice tidak pernah terbaca.

**Files:**
- Modify: `app/Http/Controllers/InvoiceController.php` (method `build()`)
- Test: `tests/Feature/Invoice/HotelPricingModeTest.php` (tambah test)

**Interfaces:**
- Consumes: `SalesLineRuleRegistry::forInvoice()` dari Task 3
- Produces: variabel view `dateFirstLines` dan `fromLineItems` mengikuti mode invoice

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan ke `tests/Feature/Invoice/HotelPricingModeTest.php`.

**Penting:** helper ini memanggil `invoiceViewData()` milik controller — data view yang SUNGGUHAN dipakai produksi — bukan menyusun ulang datanya sendiri. Kalau helper menurunkan sendiri `dateFirstLines`, ia hanya menguji blade dan aturan, sementara penyambungan di controller tetap tak tersentuh dan bisa salah tanpa ada test yang gagal.

```php
    /** Data view yang sama persis dengan yang dipakai InvoiceController::build(). */
    private function renderInvoice(Invoice $invoice): string
    {
        $data = app(\App\Http\Controllers\InvoiceController::class)
            ->invoiceViewData($invoice->fresh());

        return view('invoice', $data)->render();
    }

    public function test_pdf_mode_kamar_memakai_tata_letak_tanggal_di_kiri(): void
    {
        $invoice = $this->makeInvoice($this->makeTour('hotel', ['pax' => 4]), 1_000_000);
        $invoice->update([
            'pricing_mode'      => Invoice::PRICING_PER_ROOM_NIGHT,
            'description_lines' => self::BARIS_KAMAR,
        ]);

        $html = $this->renderInvoice($invoice);

        $this->assertStringContainsString('<td class="k">15/08/2026 – 17/08/2026</td>', $html);
        $this->assertStringNotContainsString('<td class="k">Deluxe</td>', $html);
    }

    public function test_pdf_mode_kamar_tidak_mencetak_harga_per_malam_maupun_jumlah_kamar(): void
    {
        // Keduanya hanya dasar perhitungan internal — customer melihat hasilnya.
        $invoice = $this->makeInvoice($this->makeTour('hotel', ['pax' => 4]), 1_000_000);
        $invoice->update([
            'pricing_mode'      => Invoice::PRICING_PER_ROOM_NIGHT,
            'description_lines' => self::BARIS_KAMAR,
        ]);

        $html = $this->renderInvoice($invoice);

        $this->assertStringNotContainsString('1.500.000', $html, 'Harga per malam tidak tercetak');
        $this->assertStringNotContainsString('2 kamar', $html);
        $this->assertStringNotContainsString('2 malam', $html);
    }

    public function test_pdf_mode_pax_tidak_berubah(): void
    {
        $invoice = $this->makeInvoice($this->makeTour('hotel', ['pax' => 4]), 1_000_000);
        $invoice->update(['description_lines' => [
            ['label' => 'Dokumen', 'date' => '2026-08-15', 'detail' => 'Visa', 'amount' => 200_000],
        ]]);
        $invoice->fresh()->syncProformaTotal();

        $html = $this->renderInvoice($invoice);

        $this->assertStringContainsString('<td class="k">Dokumen</td>', $html);
        $this->assertStringContainsString('>Price<', $html);
    }
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=HotelPricingModeTest`
Expected: FAIL — `Call to undefined method App\Http\Controllers\InvoiceController::invoiceViewData()`

- [ ] **Step 3: Pisahkan penyusunan data view agar bisa diuji**

Di `app/Http/Controllers/InvoiceController.php`, keluarkan penyusunan array view dari `build()` menjadi method publik tersendiri. Isinya dipindahkan apa adanya dari `build()`, dengan HANYA dua entri yang berubah — keduanya kini diselesaikan lewat satu aturan yang memperhitungkan mode invoice:

```php
    /**
     * Data view PDF invoice. Publik supaya bisa diuji langsung: menyusun ulang
     * data ini di dalam test hanya akan menguji blade, sementara penyambungan
     * aturan di sini — bagian yang paling mudah salah — tak tersentuh.
     */
    public function invoiceViewData(Invoice $invoice): array
    {
        // Satu aturan untuk seluruh dokumen, diselesaikan lewat invoice supaya
        // mode hitung hotel ikut terbaca — bukan hanya jenis penjualannya.
        $aturan = app(SalesLineRuleRegistry::class)->forInvoice($invoice);

        $paid        = (float) $invoice->payments->sum('amount');
        $outstanding = (float) $invoice->total - $paid;

        return [
            'invoice'      => $invoice,
            'company'      => config('quotation.company'),
            'bank'         => $this->bankAccounts($invoice),
            'paymentTerms' => config('quotation.payment_terms', ''),
            'logo'         => $this->logoDataUri(),
            'lines'        => $invoice->description_lines ?? [],
            'unitPrice'    => (float) $invoice->unit_price,
            // Jenis yang totalnya tersusun dari baris bernominal tidak punya
            // harga satuan yang bermakna — unit_price lamanya sengaja dibiarkan
            // utuh di database (agar banner panel bisa menampilkannya), jadi
            // nilainya TIDAK bisa dipakai menyimpulkan ini. Aturannya yang tahu.
            'fromLineItems'  => $aturan->totalComposition() === 'line_items',
            'dateFirstLines' => $aturan->chargeLinesDateFirstInPdf(),
            // Pax milik INVOICE (bukan tour) — invoice suplemen biaya tambahan
            // pakai pax 1 agar baris "harga × pax" cocok dengan totalnya.
            'pax'          => (int) ($invoice->pax ?? $invoice->tour?->pax ?? 0),
            'paid'         => $paid,
            'outstanding'  => $outstanding,
        ];
    }
```

Lalu di `build()`, ganti seluruh blok `$html = view('invoice', [ ... ])->render();` beserta perhitungan `$paid`/`$outstanding` yang kini pindah, menjadi:

```php
        $data        = $this->invoiceViewData($invoice);
        $paid        = $data['paid'];
        $outstanding = $data['outstanding'];
```

tepat sebelum blok watermark yang memakai keduanya, dan:

```php
        $html = view('invoice', $data)->render();
```

di tempat pemanggilan view semula.

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter=HotelPricingModeTest`
Expected: PASS — 16 test hijau

- [ ] **Step 4b: Buktikan gerbangnya nyata, bukan kebetulan**

Ubah sementara `forInvoice($invoice)` di `invoiceViewData()` menjadi `for($invoice->tour?->type ?? 'tour')`, jalankan `php artisan test --filter=HotelPricingModeTest`, dan pastikan `test_pdf_mode_kamar_memakai_tata_letak_tanggal_di_kiri` GAGAL. Lalu kembalikan. Sertakan output kegagalannya di laporan — tanpa itu, test ini tak terbukti bisa gagal.

- [ ] **Step 5: Jalankan seluruh test Invoice**

Run: `php artisan test --filter=Invoice`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/InvoiceController.php tests/Feature/Invoice/HotelPricingModeTest.php
git commit -m "feat: PDF invoice mengikuti mode hitung, bukan hanya jenis penjualan"
```

---

### Task 8: Aturan per invoice sampai ke frontend

Prop `salesLine` dibangun dari tipe tour dan dikirim satu kali per halaman. Mode adalah milik invoice, dan satu tour bisa memuat beberapa invoice dengan mode berbeda.

**Files:**
- Modify: `app/Models/Invoice.php`
- Test: `tests/Feature/SalesLine/HotelPricingModeRuleTest.php` (tambah test)

**Interfaces:**
- Consumes: `SalesLineRuleRegistry::payloadForInvoice()` dari Task 3
- Produces: setiap invoice terserialisasi membawa key `rules` berisi payload aturannya — dipakai Task 9 dan 10 sebagai `inv.rules.*`

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan ke `tests/Feature/SalesLine/HotelPricingModeRuleTest.php`:

```php
    public function test_setiap_invoice_membawa_aturannya_sendiri_ke_frontend(): void
    {
        $tour = $this->makeTour('hotel', ['pax' => 4]);

        $paxInvoice   = $this->makeInvoice($tour, 1_000_000);
        $kamarInvoice = $this->makeInvoice($tour, 1_000_000);
        $kamarInvoice->update(['pricing_mode' => 'per_room_night']);

        $this->actingAs($this->salesUser())
            ->get(route('tours.edit', $tour->id))
            ->assertInertia(function ($page) use ($paxInvoice, $kamarInvoice) {
                $invoices = collect($page->toArray()['props']['tour']['invoices']);

                $pax   = $invoices->firstWhere('id', $paxInvoice->id);
                $kamar = $invoices->firstWhere('id', $kamarInvoice->id);

                // Dua invoice pada tour yang SAMA membawa aturan berbeda —
                // inilah yang tidak bisa diwakili prop salesLine tingkat tour.
                $this->assertSame('per_unit', $pax['rules']['totalComposition']);
                $this->assertSame('per_pax', $pax['rules']['pricingMode']);
                $this->assertSame('line_items', $kamar['rules']['totalComposition']);
                $this->assertSame('per_room_night', $kamar['rules']['pricingMode']);
                $this->assertSame(['per_pax', 'per_room_night'], $kamar['rules']['pricingModes']);
            });
    }

    public function test_invoice_jenis_lain_membawa_daftar_mode_kosong(): void
    {
        $tour    = $this->makeTour('tour', ['pax' => 4]);
        $invoice = $this->makeInvoice($tour, 1_000_000);

        $this->actingAs($this->salesUser())
            ->get(route('tours.edit', $tour->id))
            ->assertInertia(function ($page) use ($invoice) {
                $baris = collect($page->toArray()['props']['tour']['invoices'])->firstWhere('id', $invoice->id);

                $this->assertSame([], $baris['rules']['pricingModes'], 'Tanpa pilihan mode, pemilihnya tidak muncul');
            });
    }
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=HotelPricingModeRuleTest`
Expected: FAIL — `Undefined array key "rules"`

- [ ] **Step 3: Lekatkan atribut `rules` pada model**

Di `app/Models/Invoice.php`, tambahkan properti `$appends` tepat setelah `$casts`, lalu accessornya:

```php
    /**
     * Aturan jenis penjualan MILIK INVOICE INI, ikut setiap kali invoice
     * diserialisasi ke frontend.
     *
     * Dilekatkan pada model, bukan ditambahkan di TourController dan
     * FinanceController satu per satu, supaya halaman berikutnya yang
     * menampilkan invoice tidak bisa lupa mengirimkannya — dan supaya mode
     * yang berbeda antar-invoice pada satu tour tidak tertimpa satu prop
     * tingkat tour.
     */
    protected $appends = ['rules'];

    public function getRulesAttribute(): array
    {
        return app(SalesLineRuleRegistry::class)->payloadForInvoice($this);
    }
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter=HotelPricingModeRuleTest`
Expected: PASS — 12 test hijau

- [ ] **Step 5: Jalankan seluruh suite**

Run: `php artisan test`
Expected: PASS — `$appends` mengubah bentuk setiap serialisasi invoice; suite penuh membuktikan tidak ada assertion bentuk payload yang pecah

- [ ] **Step 6: Commit**

```bash
git add app/Models/Invoice.php tests/Feature/SalesLine/HotelPricingModeRuleTest.php
git commit -m "feat: setiap invoice membawa aturan jenis penjualannya sendiri"
```

---

### Task 9: Pemilih mode di panel invoice

**Files:**
- Modify: `resources/js/Components/Tours/InvoicesPanel.vue`

**Interfaces:**
- Consumes: `inv.rules.pricingModes` (array) dan `inv.rules.pricingMode` (string) dari Task 8; field `pricing_mode` yang diterima `updateProforma` dari Task 6
- Produces: `pricingMode(inv)` dan `modeOptions(inv)` — dipakai Task 10

- [ ] **Step 1: Tambah state dan helper mode**

Di `resources/js/Components/Tours/InvoicesPanel.vue`, pada blok `watch` atas `props.tour.invoices`, tambahkan satu key ke objek `proformaForms[inv.id]`, tepat setelah `guest_name`:

```js
                pricing_mode:      inv.rules?.pricingMode ?? 'per_pax',
```

Lalu tepat setelah computed `useDateRange`, tambahkan:

```js
// Label mode dipetakan di sini, tapi DAFTAR mode-nya datang dari backend —
// komponen tidak pernah bertanya "apakah jenisnya hotel?".
const LABEL_MODE = {
    per_pax:        'Harga / pax',
    per_room_night: 'Harga / kamar / malam',
}
function modeOptions(inv) {
    return (inv.rules?.pricingModes ?? []).map(m => ({ value: m, label: LABEL_MODE[m] ?? m }))
}
function pricingMode(inv) {
    return proformaForms[inv.id]?.pricing_mode ?? 'per_pax'
}
function setPricingMode(invId, mode) {
    proformaForms[invId].pricing_mode = mode
    saveProforma(invId)
}
```

- [ ] **Step 2: Kirim mode hanya untuk jenis yang punya pilihan**

`saveProforma()` menyebar `...f`, sehingga `pricing_mode` akan ikut terkirim untuk SETIAP invoice — termasuk tour dan rental yang tidak punya pilihan mode. Itu akan menulis `'per_pax'` ke kolom yang semestinya tetap `null` bagi jenis-jenis itu.

Di `saveProforma()`, tepat setelah `delete payload.additional_lines`, tambahkan:

```js
    // Jenis tanpa pilihan mode tidak boleh ikut menulis kolom ini — biarkan
    // NULL, yang artinya memang "tidak memilih apa pun".
    const inv = (props.tour.invoices ?? []).find(i => i.id === invId)
    if ((inv?.rules?.pricingModes ?? []).length < 2) delete payload.pricing_mode
```

- [ ] **Step 2b: Kunci perilaku itu dengan test**

Tambahkan ke `tests/Feature/Invoice/HotelPricingModeTest.php`:

```php
    public function test_jenis_tanpa_pilihan_mode_tetap_null_setelah_disimpan(): void
    {
        // Backend tidak boleh bergantung pada frontend untuk ini: permintaan
        // tanpa pricing_mode wajib membiarkan kolomnya apa adanya.
        $invoice = $this->makeInvoice($this->makeTour('tour', ['pax' => 4]), 1_000_000);

        $this->actingAs($this->salesUser())
            ->patch(route('invoices.proforma', $invoice), [
                'currency'   => 'IDR',
                'unit_price' => 1_000_000,
            ])
            ->assertRedirect();

        $this->assertNull($invoice->fresh()->pricing_mode);
    }
```

Run: `php artisan test --filter=HotelPricingModeTest`
Expected: PASS — aturan `?? $invoice->pricing_mode` di Task 6 sudah menjamin ini; test ini menguncinya agar tidak hilang.

- [ ] **Step 3: Tambah blok pemilih mode**

Tepat sebelum blok "Mata uang + kurs" (`<!-- Mata uang + kurs -->`), tambahkan:

```vue
                    <!-- Pemilih cara hitung — hanya muncul untuk jenis yang
                         memang punya lebih dari satu, menurut backend. -->
                    <div v-if="modeOptions(inv).length > 1" class="flex flex-wrap items-center gap-3">
                        <span class="text-xs font-medium text-muted-foreground">Cara Hitung</span>
                        <label v-for="opt in modeOptions(inv)" :key="opt.value"
                            class="flex items-center gap-1.5 text-sm border rounded px-2.5 py-1.5 cursor-pointer hover:bg-muted/30"
                            :class="pricingMode(inv) === opt.value ? 'border-primary bg-primary/5 font-medium' : ''">
                            <input type="radio" :name="'mode-' + inv.id" :value="opt.value"
                                :checked="pricingMode(inv) === opt.value"
                                @change="setPricingMode(inv.id, opt.value)"
                                class="h-4 w-4 border-input" />
                            {{ opt.label }}
                        </label>
                    </div>
```

- [ ] **Step 4: Pastikan build sukses**

Run: `npm run build`
Expected: selesai tanpa error

- [ ] **Step 5: Verifikasi manual di browser**

Jalankan `composer dev`, buka tour **hotel** berstatus confirmed dengan invoice belum disetujui. Periksa: dua pilihan "Harga / pax" dan "Harga / kamar / malam" muncul, pilihan aktif tersorot, memilih yang lain langsung tersimpan dan bertahan setelah halaman dimuat ulang. Buka tour **tour**: tidak ada pemilih mode sama sekali.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Components/Tours/InvoicesPanel.vue
git commit -m "feat: pemilih cara hitung di panel invoice"
```

---

### Task 10: Blok Rincian Kamar dan Biaya Tambahan

**Files:**
- Modify: `resources/js/Components/Tours/InvoicesPanel.vue`

**Interfaces:**
- Consumes: `pricingMode(inv)` dari Task 9; `inv.rules.totalComposition` dari Task 8
- Produces: tidak ada

- [ ] **Step 1: Pisahkan baris kamar dari biaya tambahan saat hidrasi**

Di blok `watch`, ganti pemetaan `additional_lines` sehingga baris kamar dipisah ke koleksi sendiri. Tambahkan `room_lines` dan ubah filter `additional_lines`:

```js
                // Penanda baris kamar adalah KEHADIRAN key rooms, bukan
                // nilainya — sama seperti `amount` memisahkan baris bernominal
                // dari baris deskripsi. Baris kamar yang kamarnya masih kosong
                // tetap baris kamar.
                room_lines: Array.isArray(inv.description_lines)
                    ? inv.description_lines.filter(l => l.rooms !== undefined).map(l => ({
                        label: l.label ?? '', date: l.date ?? '', date_end: l.date_end ?? '',
                        detail: l.detail ?? '', rooms: l.rooms ?? '', unit_price: l.unit_price ?? '',
                    }))
                    : [],
                additional_lines: Array.isArray(inv.description_lines)
                    ? inv.description_lines.filter(l => l.amount !== undefined && l.amount !== null && l.rooms === undefined).map(l => ({ label: l.label ?? '', date: l.date ?? '', date_end: l.date_end ?? '', detail: l.detail ?? '', amount: Number(l.amount) || 0 }))
                    : [],
```

- [ ] **Step 2: Hitung malam dan nominal untuk ditampilkan**

Setelah fungsi `chargeLineDate()`, tambahkan cerminan `App\Support\RoomChargeLine` — hanya untuk tampilan; server tetap yang menentukan:

```js
// Cerminan App\Support\RoomChargeLine, HANYA untuk ditampilkan sementara sales
// mengetik. Nominal yang tersimpan selalu hasil hitungan server.
function roomNights(ln) {
    const a = String(ln.date ?? '').trim()
    const b = String(ln.date_end ?? '').trim()
    if (!POLA_ISO.test(a) || !POLA_ISO.test(b)) return 0
    const selisih = (new Date(b + 'T00:00:00') - new Date(a + 'T00:00:00')) / 86400000
    return selisih > 0 ? Math.round(selisih) : 0
}
function roomAmount(ln) {
    return (Number(ln.unit_price) || 0) * (Number(ln.rooms) || 0) * roomNights(ln)
}
```

- [ ] **Step 3: Kirim baris kamar bersama baris lain**

Di `saveProforma()`, tambahkan `room_lines` ke `description_lines` dan buang key sementaranya:

```js
    const payload = {
        ...f,
        description_lines: [
            ...f.description_lines,
            ...f.room_lines.map(l => ({
                label: l.label, date: l.date ?? '', date_end: l.date_end ?? '', detail: l.detail,
                rooms: Number(l.rooms) || 0, unit_price: Number(l.unit_price) || 0,
                // Nominal disertakan agar bentuk barisnya utuh; server
                // menimpanya lewat RoomChargeLine::recalculate().
                amount: roomAmount(l),
            })),
            ...f.additional_lines.map(l => ({ label: l.label, date: l.date ?? '', date_end: l.date_end ?? '', detail: l.detail, amount: Number(l.amount) || 0 })),
        ],
    }
    delete payload.additional_lines
    delete payload.room_lines
```

- [ ] **Step 4: Tambah dan hapus baris kamar**

Setelah `removeAdditionalLine()`, tambahkan:

```js
function addRoomLine(invId) {
    proformaForms[invId].room_lines.push({ label: '', date: '', date_end: '', detail: '', rooms: '', unit_price: '' })
}
function removeRoomLine(invId, idx) {
    proformaForms[invId].room_lines.splice(idx, 1)
    saveProforma(invId)
}
```

- [ ] **Step 5: Ikutkan baris kamar ke total yang ditampilkan**

Di `proformaTotal()`, ganti isinya menjadi:

```js
function proformaTotal(invId) {
    const f = proformaForms[invId]
    if (!f) return 0
    const perKamar = pricingModeIsRoom(invId)
    const base = (isLineItems.value || perKamar) ? 0 : (Number(f.unit_price) || 0) * Math.max(tourPax.value, 1)
    const kamar = perKamar ? (f.room_lines ?? []).reduce((s, l) => s + roomAmount(l), 0) : 0
    const additional = (f.additional_lines ?? []).reduce((s, l) => s + (Number(l.amount) || 0), 0)
    return base + kamar + additional
}
```

dan tambahkan helper-nya tepat di atasnya:

```js
function pricingModeIsRoom(invId) {
    return (proformaForms[invId]?.pricing_mode ?? 'per_pax') === 'per_room_night'
}
```

- [ ] **Step 6: Tambah blok Rincian Kamar di template**

Tepat sebelum blok bernominal yang sudah ada (`<!-- Baris bernominal: rincian utama untuk rental, biaya tambahan untuk jenis lain -->`), tambahkan:

```vue
                    <!-- Rincian Kamar — hanya pada mode per kamar per malam.
                         MALAM dan NOMINAL adalah hasil hitungan, bukan isian. -->
                    <div v-if="pricingModeIsRoom(inv.id)" class="rounded-md border">
                        <div class="flex items-center justify-between px-3 py-2 border-b bg-blue-50/30">
                            <span class="text-xs font-semibold uppercase text-muted-foreground">Rincian Kamar</span>
                            <Button size="sm" variant="outline" @click="addRoomLine(inv.id)">+ Kamar</Button>
                        </div>
                        <div v-if="proformaForms[inv.id].room_lines.length === 0" class="px-3 py-4 text-center text-xs text-muted-foreground">
                            Belum ada kamar. Klik "+ Kamar" untuk menambah tipe kamar beserta periode menginap, harga per malam, dan jumlah kamarnya.
                        </div>
                        <div v-else class="divide-y">
                            <div class="flex flex-wrap items-center gap-2 px-3 py-1.5 bg-muted/20 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                                <span class="w-36">Mulai</span>
                                <span class="w-36">Selesai</span>
                                <span class="w-32">Tipe Kamar</span>
                                <span class="flex-1 min-w-[8rem]">Keterangan</span>
                                <span class="w-32 text-right">Harga/Malam</span>
                                <span class="w-16 text-right">Kamar</span>
                                <span class="w-14 text-right">Malam</span>
                                <span class="w-32 text-right">Nominal</span>
                                <span class="w-4"></span>
                            </div>
                            <div v-for="(ln, idx) in proformaForms[inv.id].room_lines" :key="idx"
                                class="flex flex-wrap items-center gap-2 px-3 py-2">
                                <input type="date" v-model="ln.date" @blur="saveProforma(inv.id)"
                                    class="w-36 border rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-primary" />
                                <input type="date" v-model="ln.date_end" @blur="saveProforma(inv.id)"
                                    class="w-36 border rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-primary" />
                                <input type="text" v-model="ln.label" @blur="saveProforma(inv.id)" placeholder="Tipe Kamar (mis. Deluxe)"
                                    class="w-32 border rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-primary" />
                                <input type="text" v-model="ln.detail" @blur="saveProforma(inv.id)" placeholder="Keterangan"
                                    class="flex-1 min-w-[8rem] border rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-primary" />
                                <input type="number" v-model="ln.unit_price" @blur="saveProforma(inv.id)" min="0" placeholder="Harga/malam"
                                    class="w-32 border rounded px-2 py-1 text-right text-sm font-mono focus:outline-none focus:ring-1 focus:ring-primary" />
                                <input type="number" v-model="ln.rooms" @blur="saveProforma(inv.id)" min="0" placeholder="Kamar"
                                    class="w-16 border rounded px-2 py-1 text-right text-sm font-mono focus:outline-none focus:ring-1 focus:ring-primary" />
                                <span class="w-14 text-right text-sm font-mono text-muted-foreground">{{ roomNights(ln) }}</span>
                                <span class="w-32 text-right text-sm font-mono font-medium">{{ fmtCur(roomAmount(ln), proformaForms[inv.id].currency) }}</span>
                                <button type="button" @click="removeRoomLine(inv.id, idx)"
                                    class="text-muted-foreground hover:text-destructive transition-colors" title="Hapus baris">✕</button>
                            </div>
                        </div>
                    </div>
```

- [ ] **Step 7: Sembunyikan blok "Harga / pax" pada mode kamar**

Pada blok harga per pax, ganti `v-if="!isLineItems"` menjadi:

```vue
                    <div v-if="!isLineItems && !pricingModeIsRoom(inv.id)" class="flex flex-wrap items-end gap-3 rounded-md bg-muted/20 px-4 py-3">
```

dan pada blok penggantinya di bawah, ganti `v-else` menjadi:

```vue
                    <div v-else class="flex flex-wrap items-end justify-end gap-3 rounded-md bg-muted/20 px-4 py-3">
```

(tidak berubah — `v-else` otomatis mengikuti kondisi baru di atasnya).

- [ ] **Step 8: Pastikan build sukses**

Run: `npm run build`
Expected: selesai tanpa error

- [ ] **Step 9: Verifikasi manual di browser**

Pada tour hotel dengan invoice belum disetujui:
- pilih "Harga / kamar / malam" → blok "Rincian Kamar" muncul, blok "Harga / pax" hilang, blok "Biaya Tambahan" tetap ada
- tambah satu kamar: isi 15/08 → 17/08, harga 1.500.000, kamar 2 → kolom MALAM menampilkan 2 dan NOMINAL menampilkan 6.000.000
- muat ulang halaman → baris kamar kembali di blok Rincian Kamar, bukan di Biaya Tambahan
- tambah satu Biaya Tambahan 750.000 → total menjadi 6.750.000
- kembali ke "Harga / pax" → blok Rincian Kamar hilang, harga/pax kembali, dan baris kamarnya tidak hilang saat mode dikembalikan lagi

- [ ] **Step 10: Commit**

```bash
git add resources/js/Components/Tours/InvoicesPanel.vue
git commit -m "feat: blok Rincian Kamar terpisah dari Biaya Tambahan pada mode kamar"
```

---

### Task 11: Verifikasi menyeluruh

**Files:** tidak ada perubahan kode

- [ ] **Step 1: Jalankan seluruh test suite**

Run: `php artisan test`
Expected: PASS — seluruh suite hijau

- [ ] **Step 2: Pastikan build produksi sukses**

Run: `npm run build`
Expected: selesai tanpa error

- [ ] **Step 3: Buktikan migrasi bisa maju dan mundur**

Run: `php artisan migrate:fresh --seed 2>/dev/null || php artisan migrate:fresh`
Expected: sukses tanpa error

Run: `php artisan migrate:rollback --step=1 && php artisan migrate`
Expected: kolom `pricing_mode` hilang lalu kembali tanpa error

- [ ] **Step 4: Pastikan berkas tak berhubungan tidak ikut ter-commit**

Run: `git status --short`
Expected: hanya `docs/design-system/13-my-jobs-manifest.md` dan `resources/js/Pages/Finance/Loans.vue` yang tersisa belum ter-commit

Run: `git log --oneline dev..HEAD`
Expected: 10 commit fitur ditambah 1 commit spesifikasi, tanpa commit yang menyentuh `docs/design-system/` atau `Pages/Finance/`
