# Tanggal Selesai & Urutan Baru Rincian Tagihan — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Baris bernominal invoice untuk jenis rental dan hotel mendapat tanggal selesai, dengan urutan kolom tanggal mulai → selesai → nama/unit → keterangan → nominal, dan PDF mencetak periodenya sebagai rentang `15/08/2026 – 17/08/2026`.

**Architecture:** Baris bernominal disimpan sebagai elemen JSON di kolom `invoices.description_lines`, jadi tidak ada migrasi — cukup key opsional `date_end`. Pemilihan jenis penjualan yang memakai rentang dilakukan lewat method baru `chargeLinesUseDateRange()` pada `SalesLineInvoiceRule`, bukan percabangan `if type ===` di controller atau komponen. Penulisan tanggal dipusatkan di satu helper PHP murni (`App\Support\ChargeLineDate`) yang dipakai blade PDF, dengan cerminannya di sisi Vue untuk ringkasan layar.

**Tech Stack:** Laravel 11 + Inertia + Vue 3, PHPUnit, Blade + dompdf, Tailwind.

**Spec:** `docs/superpowers/specs/2026-08-11-invoice-rincian-tagihan-tanggal-selesai-design.md`

**Branch:** `feat/invoice-tanggal-selesai-rental-hotel` (sudah dibuat dari `dev`)

## Global Constraints

- **Tidak ada migrasi database.** `invoices.description_lines` sudah bertipe JSON. Baris lama tanpa `date_end` wajib tetap sah dan tampil seperti sebelumnya.
- **Tidak boleh ada percabangan `if type === 'rental'`** di controller, model, blade, atau komponen Vue. Pemilihan jenis hanya lewat `SalesLineRuleRegistry` beserta implementasi aturannya.
- **Jenis yang memakai rentang tanggal hanya `rental` dan `hotel`.** Kelima jenis lain (`tour`, `guide`, `mice`, `document`, `ticketing`) tidak berubah sama sekali.
- **Format tanggal:** `d/m/Y` (contoh `15/08/2026`). Rentang ditulis `15/08/2026 – 17/08/2026`.
- **Tanda pisah adalah en dash Unicode `–` dengan spasi di kedua sisi**, bukan entitas `&ndash;`. Mengikuti `$resvDate` di `invoice.blade.php` yang sudah memakainya dan tercetak normal di PDF produksi. Entitas HTML akan tercetak mentah karena nilainya dirakit di PHP lalu dicetak lewat `{{ }}` yang meng-escape.
- **Pemformatan hanya berlaku untuk nilai berpola `YYYY-MM-DD`.** Nilai lain (mis. `Aug 15, 2026` dari `CostRequestController`, atau teks bebas sales seperti `13-15 Aug 2026`) dicetak apa adanya. Ini yang menjamin invoice yang sudah terbit tidak berubah tampilannya.
- **Aturan pemformatan PDF tidak dibatasi jenis penjualan** — patokannya bentuk nilai, bukan `chargeLinesUseDateRange`. Yang dibatasi rental & hotel hanyalah kotak tanggal kedua di form.
- **Penanda baris bernominal tetap kehadiran key `amount`.** `date_end` tidak pernah dipakai sebagai penanda.
- **Menjalankan test:** `php artisan test --filter=<NamaTest>` dari root `/Users/marchelinoraco/Documents/2026/erp_wm`.
- **Tidak ada test runner JavaScript di repo ini.** Verifikasi perubahan Vue lewat `npm run build` (harus sukses) ditambah pemeriksaan manual di browser.
- **Jangan meng-commit** `docs/design-system/13-my-jobs-manifest.md` — berkas termodifikasi yang tidak berhubungan dengan pekerjaan ini. Selalu `git add` berkas secara eksplisit, jangan `git add -A`.

## File Structure

| Berkas | Tanggung jawab | Aksi |
| --- | --- | --- |
| `app/Support/ChargeLineDate.php` | Satu-satunya aturan penulisan tanggal baris bernominal | Buat |
| `tests/Unit/Support/ChargeLineDateTest.php` | Mengunci aturan penulisan itu | Buat |
| `app/Contracts/SalesLineInvoiceRule.php` | Kontrak aturan per jenis | Modifikasi |
| `app/Services/SalesLine/BaseSalesLineRule.php` | Perilaku default (`false`) | Modifikasi |
| `app/Services/SalesLine/TransportRule.php` | Rental → `true` | Modifikasi |
| `app/Services/SalesLine/HotelRule.php` | Hotel → `true` | Modifikasi |
| `app/Services/SalesLine/SalesLineRuleRegistry.php` | Menyalurkan penanda ke frontend | Modifikasi |
| `tests/Feature/SalesLine/ChargeLineDateRangeRuleTest.php` | Menyapu seluruh jenis terdaftar | Buat |
| `tests/Feature/SalesLine/SalesLinePropPayloadTest.php` | Penanda sampai ke payload Inertia | Modifikasi |
| `resources/views/invoice.blade.php` | Mencetak rentang di PDF customer | Modifikasi |
| `tests/Feature/Invoice/CustomerPdfUnitLabelTest.php` | Sudah memuat test tanggal baris bernominal | Modifikasi |
| `app/Http/Controllers/InvoiceController.php` | Validasi `date_end` | Modifikasi |
| `tests/Feature/Invoice/ProformaAdditionalChargeTest.php` | `date_end` tersimpan & terbaca ulang | Modifikasi |
| `resources/js/Components/Tours/InvoicesPanel.vue` | Form proforma + ringkasan invoice disetujui | Modifikasi |

---

### Task 1: Helper penulisan tanggal baris bernominal

Helper murni tanpa ketergantungan apa pun ke Eloquent, sehingga bisa diuji sebagai unit test cepat dan dipakai blade tanpa menyiapkan database.

**Files:**
- Create: `app/Support/ChargeLineDate.php`
- Test: `tests/Unit/Support/ChargeLineDateTest.php`

**Interfaces:**
- Consumes: tidak ada (task pertama)
- Produces: `App\Support\ChargeLineDate::format(?string $start, ?string $end = null): string` — dipakai Task 3 (blade PDF) dan dicerminkan di Task 6 (Vue)

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Unit/Support/ChargeLineDateTest.php`:

```php
<?php

namespace Tests\Unit\Support;

use App\Support\ChargeLineDate;
use PHPUnit\Framework\TestCase;

/**
 * Aturan penulisan tanggal baris bernominal invoice (spec §6).
 *
 * Inti aturannya: HANYA nilai berpola YYYY-MM-DD yang diformat. Nilai lain
 * dicetak apa adanya, karena kolom yang sama menampung teks bebas dari sales
 * dan format 'M d, Y' dari CostRequestController. Tanpa penjagaan itu,
 * invoice yang sudah terbit akan berubah tampilannya.
 */
class ChargeLineDateTest extends TestCase
{
    public function test_dua_tanggal_iso_ditulis_sebagai_rentang(): void
    {
        $this->assertSame(
            '15/08/2026 – 17/08/2026',
            ChargeLineDate::format('2026-08-15', '2026-08-17')
        );
    }

    public function test_tanpa_tanggal_selesai_hanya_tanggal_mulai(): void
    {
        $this->assertSame('15/08/2026', ChargeLineDate::format('2026-08-15', ''));
        $this->assertSame('15/08/2026', ChargeLineDate::format('2026-08-15', null));
        $this->assertSame('15/08/2026', ChargeLineDate::format('2026-08-15'));
    }

    public function test_tidak_ada_tanda_pisah_menggantung(): void
    {
        $this->assertStringNotContainsString('–', ChargeLineDate::format('2026-08-15', ''));
    }

    public function test_tanggal_mulai_dan_selesai_sama_tidak_diulang(): void
    {
        $this->assertSame('15/08/2026', ChargeLineDate::format('2026-08-15', '2026-08-15'));
    }

    public function test_teks_bebas_dicetak_apa_adanya(): void
    {
        // Ditulis CostRequestController::appendAdditionalCharge() dengan format 'M d, Y'.
        $this->assertSame('Aug 15, 2026', ChargeLineDate::format('Aug 15, 2026'));
        // Diketik sales pada baris deskripsi.
        $this->assertSame('13-15 Aug 2026', ChargeLineDate::format('13-15 Aug 2026'));
    }

    public function test_tanggal_mustahil_diperlakukan_sebagai_teks_bebas(): void
    {
        // Berpola YYYY-MM-DD tapi bukan tanggal yang ada. Tidak boleh melempar
        // exception yang menggagalkan seluruh render PDF.
        $this->assertSame('2026-13-45', ChargeLineDate::format('2026-13-45'));
    }

    public function test_tanpa_tanggal_menghasilkan_string_kosong(): void
    {
        $this->assertSame('', ChargeLineDate::format(null, null));
        $this->assertSame('', ChargeLineDate::format('', ''));
        $this->assertSame('', ChargeLineDate::format('   '));
    }

    public function test_hanya_tanggal_selesai_yang_terisi_tetap_tercetak(): void
    {
        $this->assertSame('17/08/2026', ChargeLineDate::format('', '2026-08-17'));
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=ChargeLineDateTest`
Expected: FAIL — `Class "App\Support\ChargeLineDate" not found`

- [ ] **Step 3: Tulis implementasi minimal**

Buat `app/Support/ChargeLineDate.php`:

```php
<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Menulis tanggal baris bernominal invoice (baris description_lines yang
 * punya `amount`).
 *
 * Nilainya bisa berupa tanggal ISO dari input bertipe date ("2026-08-15")
 * ATAU teks bebas — diketik sales pada baris deskripsi, atau ditulis
 * CostRequestController dengan format 'M d, Y'. Hanya yang berpola ISO yang
 * diformat; sisanya dikembalikan apa adanya supaya invoice yang sudah terbit
 * tidak berubah tampilannya.
 */
final class ChargeLineDate
{
    /** En dash Unicode — sama dengan $resvDate di invoice.blade.php. */
    private const PEMISAH = ' – ';

    public static function format(?string $start, ?string $end = null): string
    {
        $awal  = self::satu($start);
        $akhir = self::satu($end);

        if ($awal === '') {
            return $akhir;
        }

        // Tanpa tanggal selesai, atau rentang sehari: satu tanggal saja.
        // Menjaga agar tidak pernah ada tanda pisah menggantung.
        if ($akhir === '' || $akhir === $awal) {
            return $awal;
        }

        return $awal . self::PEMISAH . $akhir;
    }

    private static function satu(?string $nilai): string
    {
        $nilai = trim((string) $nilai);

        // hasFormat() menolak "Aug 15, 2026" DAN "2026-13-45" sekaligus, jadi
        // teks bebas dan tanggal mustahil sama-sama lolos tanpa exception.
        if ($nilai === '' || ! Carbon::hasFormat($nilai, 'Y-m-d')) {
            return $nilai;
        }

        return Carbon::createFromFormat('Y-m-d', $nilai)->format('d/m/Y');
    }
}
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter=ChargeLineDateTest`
Expected: PASS — 8 test hijau

- [ ] **Step 5: Commit**

```bash
git add app/Support/ChargeLineDate.php tests/Unit/Support/ChargeLineDateTest.php
git commit -m "feat: helper penulisan tanggal baris bernominal invoice"
```

---

### Task 2: Aturan jenis penjualan yang memakai rentang tanggal

**Files:**
- Modify: `app/Contracts/SalesLineInvoiceRule.php`
- Modify: `app/Services/SalesLine/BaseSalesLineRule.php`
- Modify: `app/Services/SalesLine/TransportRule.php`
- Modify: `app/Services/SalesLine/HotelRule.php`
- Modify: `app/Services/SalesLine/SalesLineRuleRegistry.php` (method `payloadFor()`)
- Create: `tests/Feature/SalesLine/ChargeLineDateRangeRuleTest.php`
- Test: `tests/Feature/SalesLine/SalesLinePropPayloadTest.php` (tambah satu test)

**Interfaces:**
- Consumes: tidak ada dari Task 1
- Produces:
  - `SalesLineInvoiceRule::chargeLinesUseDateRange(): bool`
  - key `chargeLinesUseDateRange` (bool) di dalam array hasil `SalesLineRuleRegistry::payloadFor()`, yaitu prop Inertia `salesLine` — dipakai Task 5 dan Task 6

- [ ] **Step 1: Tulis test yang gagal — sapuan seluruh jenis terdaftar**

Buat `tests/Feature/SalesLine/ChargeLineDateRangeRuleTest.php`:

```php
<?php

namespace Tests\Feature\SalesLine;

use App\Services\SalesLine\SalesLineRuleRegistry;
use Tests\TestCase;

/**
 * Rental dan hotel menagih layanan yang berjalan sepanjang rentang tanggal
 * (sewa kendaraan, menginap), jadi baris bernominalnya punya tanggal mulai
 * DAN selesai. Jenis lain menagih biaya pada satu titik tanggal.
 *
 * Disapu lewat registry, bukan daftar yang ditulis ulang di test: menambah
 * jenis baru tanpa memutuskan perilakunya akan langsung menggagalkan test ini.
 */
class ChargeLineDateRangeRuleTest extends TestCase
{
    private const MEMAKAI_RENTANG = ['rental', 'hotel'];

    public function test_hanya_rental_dan_hotel_yang_memakai_rentang_tanggal(): void
    {
        $registry = app(SalesLineRuleRegistry::class);

        foreach ($registry->keys() as $key) {
            $this->assertSame(
                in_array($key, self::MEMAKAI_RENTANG, true),
                $registry->for($key)->chargeLinesUseDateRange(),
                "Jenis {$key}"
            );
        }
    }

    public function test_jenis_tak_dikenal_tidak_memakai_rentang(): void
    {
        // Registry menjatuhkan jenis tak dikenal ke TourRule.
        $this->assertFalse(
            app(SalesLineRuleRegistry::class)->for('jenis-yang-belum-ada')->chargeLinesUseDateRange()
        );
    }
}
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=ChargeLineDateRangeRuleTest`
Expected: FAIL — `Call to undefined method ...::chargeLinesUseDateRange()`

- [ ] **Step 3: Tambah method ke kontrak**

Di `app/Contracts/SalesLineInvoiceRule.php`, tambahkan setelah method `costingSource()`:

```php
    /**
     * true = baris bernominal invoice punya tanggal mulai DAN tanggal selesai.
     *
     * Sewa kendaraan dan menginap berjalan sepanjang rentang tanggal, sedangkan
     * biaya dokumen atau izin terjadi pada satu titik tanggal.
     */
    public function chargeLinesUseDateRange(): bool;
```

- [ ] **Step 4: Beri perilaku default di kelas dasar**

Di `app/Services/SalesLine/BaseSalesLineRule.php`, tambahkan setelah `costingSource()`:

```php
    /** Mayoritas jenis menagih pada satu titik tanggal. */
    public function chargeLinesUseDateRange(): bool
    {
        return false;
    }
```

- [ ] **Step 5: Override di TransportRule dan HotelRule**

Di `app/Services/SalesLine/TransportRule.php`, tambahkan setelah `costingSource()`:

```php
    /** Sewa kendaraan/kapal berjalan dari tanggal mulai sampai tanggal selesai. */
    public function chargeLinesUseDateRange(): bool
    {
        return true;
    }
```

Di `app/Services/SalesLine/HotelRule.php`, tambahkan setelah `defaultMultipliers()`:

```php
    /** Menginap berjalan dari tanggal check-in sampai check-out. */
    public function chargeLinesUseDateRange(): bool
    {
        return true;
    }
```

- [ ] **Step 6: Jalankan test, pastikan lulus**

Run: `php artisan test --filter=ChargeLineDateRangeRuleTest`
Expected: PASS — 2 test hijau

- [ ] **Step 7: Tulis test yang gagal untuk payload Inertia**

Tambahkan ke `tests/Feature/SalesLine/SalesLinePropPayloadTest.php`, setelah `test_payload_memuat_komposisi_total_per_jenis()`:

```php
    public function test_payload_memuat_penanda_rentang_tanggal_baris_tagihan(): void
    {
        // Vue tidak boleh menyimpulkan sendiri jenis mana yang memakai dua
        // kotak tanggal — aturannya datang dari backend, seperti
        // profitFromRevenue dan totalComposition.
        $harapan = [
            'rental'    => true,
            'hotel'     => true,
            'tour'      => false,
            'guide'     => false,
            'mice'      => false,
            'document'  => false,
            'ticketing' => false,
        ];

        foreach ($harapan as $type => $expected) {
            $tour = $this->makeTour($type);

            // assertInertia() hanya menerima closure — tidak ada parameter
            // pesan. Jenisnya ikut diperiksa lewat salesLine.key supaya
            // kegagalan menunjuk jenis yang salah, bukan sekadar nilai boolean.
            $this->actingAs($this->salesUser())
                ->get(route('tours.edit', $tour->id))
                ->assertInertia(fn ($page) => $page
                    ->where('salesLine.key', $type)
                    ->where('salesLine.chargeLinesUseDateRange', $expected));
        }
    }
```

- [ ] **Step 8: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=SalesLinePropPayloadTest`
Expected: FAIL — properti `salesLine.chargeLinesUseDateRange` tidak ada di payload

- [ ] **Step 9: Kirim penanda lewat payloadFor()**

Di `app/Services/SalesLine/SalesLineRuleRegistry.php`, method `payloadFor()`, tambahkan satu key pada array yang dikembalikan dan perbarui docblock `@return`:

```php
    /**
     * @return array{key: string, profitFromRevenue: bool, totalComposition: string, chargeLinesUseDateRange: bool}
     */
    public function payloadFor(?string $salesLine): array
    {
        $key  = $salesLine ?? 'tour';
        $rule = $this->for($key);

        return [
            'key'                     => $key,
            'profitFromRevenue'       => $rule->profitFromRevenue(),
            'totalComposition'        => $rule->totalComposition(),
            'chargeLinesUseDateRange' => $rule->chargeLinesUseDateRange(),
        ];
    }
```

- [ ] **Step 10: Jalankan test, pastikan lulus**

Run: `php artisan test --filter=SalesLinePropPayloadTest`
Expected: PASS — seluruh test di kelas itu hijau, termasuk yang lama

- [ ] **Step 11: Jalankan seluruh test SalesLine sebagai jaring pengaman**

Run: `php artisan test --filter=SalesLine`
Expected: PASS — menambah method ke kontrak akan menggagalkan implementasi mana pun yang terlewat

- [ ] **Step 12: Commit**

```bash
git add app/Contracts/SalesLineInvoiceRule.php \
        app/Services/SalesLine/BaseSalesLineRule.php \
        app/Services/SalesLine/TransportRule.php \
        app/Services/SalesLine/HotelRule.php \
        app/Services/SalesLine/SalesLineRuleRegistry.php \
        tests/Feature/SalesLine/ChargeLineDateRangeRuleTest.php \
        tests/Feature/SalesLine/SalesLinePropPayloadTest.php
git commit -m "feat: aturan jenis penjualan yang memakai rentang tanggal baris tagihan"
```

---

### Task 3: PDF invoice mencetak rentang tanggal

**Perhatian:** test `CustomerPdfUnitLabelTest::test_baris_bernominal_mencetak_tanggal` yang sudah ada menyatakan `2026-07-22` tercetak **mentah**. Test itu akan gagal setelah perubahan ini dan memang harus diperbarui — bukan tanda ada yang rusak.

**Files:**
- Modify: `resources/views/invoice.blade.php:259-276` (loop baris bernominal)
- Test: `tests/Feature/Invoice/CustomerPdfUnitLabelTest.php` (ubah 1 test, tambah 3 test)

**Interfaces:**
- Consumes: `App\Support\ChargeLineDate::format(?string $start, ?string $end = null): string` dari Task 1
- Produces: tidak ada untuk task berikutnya

- [ ] **Step 1: Perbarui test lama & tulis test baru yang gagal**

Di `tests/Feature/Invoice/CustomerPdfUnitLabelTest.php`, **ganti** method `test_baris_bernominal_mencetak_tanggal()` yang ada dengan versi berikut, lalu tambahkan tiga test sesudahnya:

```php
    public function test_baris_bernominal_mencetak_tanggal(): void
    {
        $invoice = $this->makeInvoice($this->makeTour('rental', ['pax' => 10]), 2_050_000, [
            'description_lines' => self::BARIS_RENTAL,
        ]);

        $html = $this->renderInvoice($invoice, unitPrice: 2_050_000, pax: 10, lines: self::BARIS_RENTAL);

        // Tanggal ISO kini ditulis d/m/Y untuk customer, bukan mentah.
        $this->assertStringContainsString('22/07/2026', $html);
        $this->assertStringContainsString('25/07/2026', $html);
        $this->assertStringNotContainsString('2026-07-22', $html);
    }

    /** Baris rental dengan periode sewa penuh — kasus yang fitur ini layani. */
    private const BARIS_RENTAL_BERENTANG = [
        ['label' => 'Innova Reborn', 'date' => '2026-08-15', 'date_end' => '2026-08-17', 'detail' => 'Dengan Sopir', 'amount' => 1_700_000],
        ['label' => 'Avanza', 'date' => '2026-08-16', 'date_end' => '2026-08-17', 'detail' => 'sopir', 'amount' => 1_200_000],
    ];

    public function test_baris_bernominal_mencetak_rentang_tanggal(): void
    {
        $invoice = $this->makeInvoice($this->makeTour('rental', ['pax' => 4]), 2_900_000, [
            'description_lines' => self::BARIS_RENTAL_BERENTANG,
        ]);

        $html = $this->renderInvoice($invoice, unitPrice: 2_900_000, pax: 4, lines: self::BARIS_RENTAL_BERENTANG);

        $this->assertStringContainsString('15/08/2026 – 17/08/2026', $html);
        $this->assertStringContainsString('16/08/2026 – 17/08/2026', $html);
    }

    public function test_tanpa_tanggal_selesai_tidak_ada_tanda_pisah_menggantung(): void
    {
        $baris = [
            ['label' => 'Avanza', 'date' => '2026-08-15', 'detail' => 'sopir', 'amount' => 900_000],
        ];

        $invoice = $this->makeInvoice($this->makeTour('rental', ['pax' => 4]), 900_000, [
            'description_lines' => $baris,
        ]);

        $html = $this->renderInvoice($invoice, unitPrice: 900_000, pax: 4, lines: $baris);

        $this->assertStringContainsString('15/08/2026', $html);
        $this->assertStringNotContainsString('15/08/2026 –', $html);
    }

    public function test_tanggal_teks_bebas_pada_invoice_lama_tidak_berubah(): void
    {
        // CostRequestController::appendAdditionalCharge() menulis format 'M d, Y'.
        // Memformat ulang nilai seperti ini akan mengubah tampilan invoice yang
        // sudah terbit — justru yang paling harus dihindari.
        $baris = [
            ['label' => 'Additional', 'date' => 'Aug 15, 2026', 'detail' => 'Biaya tambahan disetujui', 'amount' => 500_000],
        ];

        $invoice = $this->makeInvoice($this->makeTour('tour', ['pax' => 4]), 1_000_000, [
            'description_lines' => $baris,
        ]);

        $html = $this->renderInvoice($invoice, unitPrice: 1_000_000, pax: 4, lines: $baris);

        $this->assertStringContainsString('Aug 15, 2026', $html);
    }
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=CustomerPdfUnitLabelTest`
Expected: FAIL — `test_baris_bernominal_mencetak_tanggal` (masih mencetak `2026-07-22`), `test_baris_bernominal_mencetak_rentang_tanggal` (belum ada rentang), dan `test_tanpa_tanggal_selesai_tidak_ada_tanda_pisah_menggantung` gagal. `test_tanggal_teks_bebas_pada_invoice_lama_tidak_berubah` sudah lulus sejak awal — itu memang test pengunci perilaku, bukan pendorong perubahan.

- [ ] **Step 3: Pakai helper di blade**

Di `resources/views/invoice.blade.php`, pada loop baris bernominal (sekitar baris 259-276), ganti isi sel deskripsi. **Sebelum:**

```blade
                            <tr>
                                <td class="k">{{ trim($ln['label'] ?? '') ?: 'Additional' }}</td>
                                <td class="s">:</td>
                                <td>
                                    @if(!empty($ln['date'])){{ $ln['date'] }}@if(!empty($ln['detail'])) &middot; @endif @endif{{ $ln['detail'] ?? '' }}
                                </td>
                            </tr>
```

**Sesudah:**

```blade
                            @php
                                // Rentang untuk rental/hotel, satu tanggal untuk sisanya,
                                // teks bebas dibiarkan utuh — lihat App\Support\ChargeLineDate.
                                $tgl = \App\Support\ChargeLineDate::format($ln['date'] ?? null, $ln['date_end'] ?? null);
                            @endphp
                            <tr>
                                <td class="k">{{ trim($ln['label'] ?? '') ?: 'Additional' }}</td>
                                <td class="s">:</td>
                                <td>
                                    @if($tgl !== ''){{ $tgl }}@if(!empty($ln['detail'])) &middot; @endif @endif{{ $ln['detail'] ?? '' }}
                                </td>
                            </tr>
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter=CustomerPdfUnitLabelTest`
Expected: PASS — seluruh test di kelas itu hijau

- [ ] **Step 5: Jalankan seluruh test Invoice sebagai jaring pengaman**

Run: `php artisan test --filter=Invoice`
Expected: PASS — memastikan tidak ada test PDF lain yang bergantung pada tanggal mentah

- [ ] **Step 6: Commit**

```bash
git add resources/views/invoice.blade.php tests/Feature/Invoice/CustomerPdfUnitLabelTest.php
git commit -m "feat: PDF invoice mencetak rentang tanggal baris bernominal"
```

---

### Task 4: Validasi & penyimpanan `date_end`

Tanpa aturan validasi, `$request->validate()` membuang key yang tidak terdaftar — `date_end` akan hilang diam-diam sebelum sampai ke database.

**Files:**
- Modify: `app/Http/Controllers/InvoiceController.php:78-90` (method `updateProforma`)
- Test: `tests/Feature/Invoice/ProformaAdditionalChargeTest.php` (tambah 2 test)

**Interfaces:**
- Consumes: tidak ada
- Produces: key `date_end` bertahan di `invoices.description_lines` — diandalkan Task 5 dan Task 6

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan ke `tests/Feature/Invoice/ProformaAdditionalChargeTest.php`, di akhir kelas:

```php
    public function test_tanggal_selesai_baris_tagihan_tersimpan_dan_terbaca_ulang(): void
    {
        $tour    = $this->makeTour('rental', ['pax' => 4]);
        $invoice = $this->makeInvoice($tour, 0);

        $this->actingAs($this->salesUser())
            ->patch(route('invoices.proforma', $invoice), [
                'currency'          => 'IDR',
                'unit_price'        => 0,
                'description_lines' => [
                    [
                        'label'    => 'Innova Reborn',
                        'date'     => '2026-08-15',
                        'date_end' => '2026-08-17',
                        'detail'   => 'Dengan Sopir',
                        'amount'   => 1_700_000,
                    ],
                ],
            ])
            ->assertRedirect();

        $baris = $invoice->fresh()->description_lines[0];

        $this->assertSame('2026-08-15', $baris['date']);
        $this->assertSame('2026-08-17', $baris['date_end'], 'date_end tidak boleh dibuang validasi');
    }

    public function test_baris_tanpa_tanggal_selesai_tetap_tersimpan(): void
    {
        // Jaminan bahwa date_end benar-benar opsional: jenis selain rental/hotel
        // tidak pernah mengirimnya sama sekali.
        $tour    = $this->makeTour('tour', ['pax' => 4]);
        $invoice = $this->makeInvoice($tour, 250_000);

        $this->actingAs($this->salesUser())
            ->patch(route('invoices.proforma', $invoice), [
                'currency'          => 'IDR',
                'unit_price'        => 250_000,
                'description_lines' => [
                    ['label' => 'Dokumen', 'date' => '2026-08-15', 'detail' => 'Visa', 'amount' => 200_000],
                ],
            ])
            ->assertRedirect();

        $baris = $invoice->fresh()->description_lines[0];

        $this->assertSame('2026-08-15', $baris['date']);
        $this->assertArrayNotHasKey('date_end', $baris);
        $this->assertEquals(1_200_000, $invoice->fresh()->total, '250.000 × 4 + 200.000');
    }
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run: `php artisan test --filter=ProformaAdditionalChargeTest`
Expected: FAIL pada `test_tanggal_selesai_baris_tagihan_tersimpan_dan_terbaca_ulang` — "Undefined array key \"date_end\"" atau nilainya hilang. `test_baris_tanpa_tanggal_selesai_tetap_tersimpan` sudah lulus sejak awal (test pengunci).

- [ ] **Step 3: Tambah aturan validasi**

Di `app/Http/Controllers/InvoiceController.php`, method `updateProforma()`, sisipkan satu baris tepat setelah aturan `description_lines.*.date`:

```php
            'description_lines.*.date'     => 'nullable|string|max:255',
            'description_lines.*.date_end' => 'nullable|string|max:255',
```

Aturannya `string`, bukan `date`, mengikuti `date` yang sudah ada — kolom yang sama juga menampung teks bebas pada baris deskripsi non-nominal.

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run: `php artisan test --filter=ProformaAdditionalChargeTest`
Expected: PASS — seluruh test di kelas itu hijau

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/InvoiceController.php tests/Feature/Invoice/ProformaAdditionalChargeTest.php
git commit -m "feat: simpan tanggal selesai baris tagihan pada proforma invoice"
```

---

### Task 5: Form proforma — urutan baru & kotak tanggal kedua

**Files:**
- Modify: `resources/js/Components/Tours/InvoicesPanel.vue` (state baris, `addAdditionalLine`, `saveProforma`, template baris bernominal)

**Interfaces:**
- Consumes: prop `salesLine.chargeLinesUseDateRange` (bool) dari Task 2; key `date_end` yang lolos validasi dari Task 4
- Produces: `useDateRange` computed — dipakai lagi di Task 6

- [ ] **Step 1: Tambah computed penanda mode rentang**

Di `resources/js/Components/Tours/InvoicesPanel.vue`, tepat setelah `const isLineItems = computed(...)` (sekitar baris 127), tambahkan:

```js
// Rental & hotel menagih layanan yang berjalan sepanjang rentang tanggal.
// Aturannya datang dari backend (SalesLineRuleRegistry), BUKAN percabangan
// tipe di sini — lihat spec §4.
const useDateRange = computed(() => props.salesLine.chargeLinesUseDateRange)
```

- [ ] **Step 2: Bawa `date_end` di hidrasi form**

Pada blok `watch` atas `props.tour.invoices`, di pemetaan `additional_lines` (sekitar baris 75-77), tambahkan `date_end`:

```js
                additional_lines: Array.isArray(inv.description_lines)
                    ? inv.description_lines.filter(l => l.amount !== undefined && l.amount !== null).map(l => ({ label: l.label ?? '', date: l.date ?? '', date_end: l.date_end ?? '', detail: l.detail ?? '', amount: Number(l.amount) || 0 }))
                    : [],
```

`description_lines` (baris tanpa nominal) TIDAK diubah — baris deskripsi biasa tetap satu tanggal teks bebas.

- [ ] **Step 3: Bawa `date_end` di baris baru dan saat menyimpan**

Ganti `addAdditionalLine()` (sekitar baris 322):

```js
function addAdditionalLine(invId) {
    proformaForms[invId].additional_lines.push({ label: '', date: '', date_end: '', detail: '', amount: '' })
}
```

Dan di `saveProforma()` (sekitar baris 296), tambahkan `date_end` pada pemetaan payload:

```js
            ...f.additional_lines.map(l => ({ label: l.label, date: l.date ?? '', date_end: l.date_end ?? '', detail: l.detail, amount: Number(l.amount) || 0 })),
```

- [ ] **Step 4: Susun ulang template baris bernominal**

Ganti isi `<div v-else class="divide-y">` pada blok baris bernominal (sekitar baris 662-676) dengan:

```vue
                        <div v-else class="divide-y">
                            <!-- Input bertipe date tidak bisa punya placeholder, jadi dua
                                 kotak tanggal berdampingan butuh penanda kolom sendiri. -->
                            <div v-if="useDateRange"
                                class="flex flex-wrap items-center gap-2 px-3 py-1.5 bg-muted/20 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                                <span class="w-36">Mulai</span>
                                <span class="w-36">Selesai</span>
                                <span class="w-32">Nama/Unit</span>
                                <span class="flex-1 min-w-[10rem]">Keterangan</span>
                                <span class="w-36 text-right">Nominal</span>
                                <span class="w-4"></span>
                            </div>
                            <div v-for="(ln, idx) in proformaForms[inv.id].additional_lines" :key="idx"
                                class="flex flex-wrap items-start gap-2 px-3 py-2">
                                <template v-if="useDateRange">
                                    <input type="date" v-model="ln.date" @blur="saveProforma(inv.id)"
                                        class="w-36 border rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-primary" />
                                    <input type="date" v-model="ln.date_end" @blur="saveProforma(inv.id)"
                                        class="w-36 border rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-primary" />
                                    <input type="text" v-model="ln.label" @blur="saveProforma(inv.id)" placeholder="Nama/Unit (mis. Avanza)"
                                        class="w-32 border rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-primary" />
                                </template>
                                <template v-else>
                                    <input type="text" v-model="ln.label" @blur="saveProforma(inv.id)" placeholder="Label (mis. Dokumen)"
                                        class="w-32 border rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-primary" />
                                    <input type="date" v-model="ln.date" @blur="saveProforma(inv.id)"
                                        class="w-36 border rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-primary" />
                                </template>
                                <input type="text" v-model="ln.detail" @blur="saveProforma(inv.id)" placeholder="Keterangan"
                                    class="flex-1 min-w-[10rem] border rounded px-2 py-1 text-sm focus:outline-none focus:ring-1 focus:ring-primary" />
                                <input type="number" v-model="ln.amount" @blur="saveProforma(inv.id)" min="0" placeholder="Nominal"
                                    class="w-36 border rounded px-2 py-1 text-right text-sm font-mono focus:outline-none focus:ring-1 focus:ring-primary" />
                                <button type="button" @click="removeAdditionalLine(inv.id, idx)"
                                    class="text-muted-foreground hover:text-destructive transition-colors" title="Hapus baris">✕</button>
                            </div>
                        </div>
```

- [ ] **Step 5: Pastikan build sukses**

Run: `npm run build`
Expected: selesai tanpa error (tidak ada test runner JS di repo ini)

- [ ] **Step 6: Verifikasi manual di browser**

Jalankan `composer dev`, lalu buka satu tour **rental** berstatus confirmed yang punya invoice belum disetujui. Periksa:
- baris judul kolom terbaca `MULAI · SELESAI · NAMA/UNIT · KETERANGAN · NOMINAL`
- urutan kotak input mengikuti judul itu
- mengisi kedua tanggal lalu berpindah fokus (blur) menyimpan tanpa error, dan nilainya tetap ada setelah halaman dimuat ulang

Ulangi pada tour bertipe **tour** (bukan rental/hotel): barisnya harus tetap seperti sebelumnya — satu kotak tanggal, label di depan, tanpa baris judul kolom.

- [ ] **Step 7: Commit**

```bash
git add resources/js/Components/Tours/InvoicesPanel.vue
git commit -m "feat: form rincian tagihan rental & hotel pakai tanggal mulai dan selesai"
```

---

### Task 6: Ringkasan invoice yang sudah disetujui menampilkan rentang

Setelah invoice disetujui, panel beralih ke ringkasan baca-saja yang saat ini tidak menampilkan tanggal sama sekali — sehingga yang dilihat di layar tidak cocok dengan PDF yang diterima customer.

**Files:**
- Modify: `resources/js/Components/Tours/InvoicesPanel.vue` (helper tanggal + blok ringkasan baris bernominal)

**Interfaces:**
- Consumes: key `date_end` dari Task 4; aturan penulisan dari Task 1 (dicerminkan, bukan diimpor — PHP tidak bisa dipanggil dari browser)
- Produces: tidak ada

- [ ] **Step 1: Tambah cerminan aturan penulisan tanggal**

Di `resources/js/Components/Tours/InvoicesPanel.vue`, tepat setelah computed `useDateRange` dari Task 5, tambahkan:

```js
// Cerminan App\Support\ChargeLineDate. Aturannya harus sama persis dengan
// yang tercetak di PDF, supaya ringkasan di layar tidak berbeda dari dokumen
// yang diterima customer. Hanya nilai berpola YYYY-MM-DD yang diformat;
// teks bebas (mis. "Aug 15, 2026" dari pengajuan biaya) dibiarkan utuh.
const POLA_ISO = /^\d{4}-\d{2}-\d{2}$/
function fmtLineDate(v) {
    const s = String(v ?? '').trim()
    if (!POLA_ISO.test(s)) return s
    const [y, m, d] = s.split('-')
    return `${d}/${m}/${y}`
}
function chargeLineDate(ln) {
    const awal  = fmtLineDate(ln.date)
    const akhir = fmtLineDate(ln.date_end)
    if (!awal) return akhir
    if (!akhir || akhir === awal) return awal
    return `${awal} – ${akhir}`
}
```

- [ ] **Step 2: Tampilkan tanggal di blok ringkasan**

Ganti isi baris pada blok "Biaya tambahan yang ditagihkan" (sekitar baris 748-755) dengan:

```vue
                        <div v-for="(ln, idx) in (inv.description_lines ?? []).filter(l => l.amount)" :key="'add-' + idx"
                            class="flex items-center justify-between gap-2 px-3 py-1.5">
                            <span>
                                <span v-if="chargeLineDate(ln)" class="font-mono text-xs text-muted-foreground">{{ chargeLineDate(ln) }} · </span>
                                <span class="font-medium">{{ ln.label || 'Additional' }}</span>
                                <span class="text-muted-foreground"> · {{ ln.detail }}</span>
                            </span>
                            <span class="font-mono font-semibold">{{ fmtCur(ln.amount, inv.currency) }}</span>
                        </div>
```

- [ ] **Step 3: Pastikan build sukses**

Run: `npm run build`
Expected: selesai tanpa error

- [ ] **Step 4: Verifikasi manual di browser**

Buka satu tour rental yang invoicenya **sudah disetujui** dan punya baris bertanggal. Ringkasannya harus menampilkan `15/08/2026 – 17/08/2026 · Innova Reborn · Dengan Sopir`, dan angka tanggal yang sama muncul di PDF invoicenya.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Components/Tours/InvoicesPanel.vue
git commit -m "feat: ringkasan invoice disetujui menampilkan rentang tanggal baris tagihan"
```

---

### Task 7: Verifikasi menyeluruh

**Files:** tidak ada perubahan kode

- [ ] **Step 1: Jalankan seluruh test suite**

Run: `php artisan test`
Expected: PASS — seluruh suite hijau

- [ ] **Step 2: Pastikan build produksi sukses**

Run: `npm run build`
Expected: selesai tanpa error

- [ ] **Step 3: Pastikan berkas tak berhubungan tidak ikut ter-commit**

Run: `git status --short`
Expected: hanya `M docs/design-system/13-my-jobs-manifest.md` yang tersisa belum ter-commit

Run: `git log --oneline dev..HEAD`
Expected: 6 commit fitur, tanpa commit yang menyentuh `docs/design-system/`
