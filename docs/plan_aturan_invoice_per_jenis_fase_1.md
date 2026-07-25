# Fase 1 — Kontrak Aturan Invoice per Jenis: Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Memperkenalkan kontrak `SalesLineInvoiceRule` + registry + tujuh kelas aturan, lalu mengalirkan perhitungan `Invoice::syncProformaTotal()` melewatinya — dengan hasil angka **byte-identik** dengan rumus lama, sehingga tidak ada perubahan perilaku yang terlihat pengguna.

**Architecture:** Kontrak + implementasi per varian, mengikuti pola `app/Contracts/BrevoGateway.php` + `app/Services/Brevo/` yang sudah ada. Registry dibangun sekali dan di-*bind* sebagai singleton di container. `syncProformaTotal()` me-*resolve* aturan dari `tour.type`, tetapi pada Fase 1 tetap memakai **pengali pax untuk semua jenis** agar hasilnya identik; Fase 2 yang akan mengganti sumber pengali ke `billing_quantities`.

**Tech Stack:** Laravel 13, PHP 8.3+, PHPUnit, SQLite in-memory.

**Spec:** [`docs/design_pemisahan_invoice_per_jenis_penjualan.md`](design_pemisahan_invoice_per_jenis_penjualan.md) — Fase 1 pada §4, arsitektur §3.1–§3.1.1, batasan §3.4.1/§7.4.

**Cakupan repo:** Seluruh perubahan pada `erp_wm`, branch `feat/aturan-invoice-per-jenis` (sudah dibuat dari `dev`).
   
---

## Kenapa hasilnya harus identik — dan bagaimana itu dibuktikan

Fase 1 **tidak boleh** mengubah satu angka pun yang dihitung `syncProformaTotal()`. Rumus lama:

```php
$total = (float) $this->unit_price * max($pax, 1);
```

Setelah Fase 1, perhitungan lewat `$rule->calculateTotal($unitPrice, [Multiplier pax])`, dan `calculateTotal` = `unitPrice × hasil kali seluruh nilai pengali`. Karena satu-satunya pengali yang dikirim adalah `max($pax,1)`, hasilnya `unitPrice × max($pax,1)` — persis sama.

**Buktinya bukan keyakinan, melainkan test karakterisasi Fase 0 yang sudah ada** di `tests/Feature/Invoice/`. Ke-34 test itu mengunci output `syncProformaTotal()` untuk ketujuh jenis, termasuk jaminan §7.4. Bila Fase 1 tidak sengaja menggeser hasil, test-test itu **memerah**. Itulah gunanya jaring pengaman yang dibangun Fase 0.

Konsekuensi penting: aturan per-jenis yang berbeda (hotel = kamar × malam, guide = hari) **didefinisikan dan diuji unit** pada Fase 1, tetapi **belum menggerakkan** `syncProformaTotal()`. Method `defaultMultipliers()` baru dialirkan ke produksi pada Fase 2 (lewat backfill dan `store`). Pada Fase 1 ia hanya teruji, belum terpakai di jalur produksi.

## Larangan yang mengikat dari desain (§3.4.1 / §7.4)

`syncProformaTotal()` **tidak** memeriksa `is_approved` — penjaganya ada di controller. Fase 1 **tidak mengubah** sifat itu dan **tidak menambah** penjaga model (itu di luar cakupan). Yang penting: Fase 1 tidak boleh membuat `syncProformaTotal()` dipanggil di tempat baru mana pun. Ia hanya mengubah *isi* method, bukan siapa yang memanggilnya.

---

## Global Constraints

- Semua path relatif terhadap `/Users/marchelinoraco/Documents/2026/erp_wm`.
- Perintah test: `php artisan test` dari root repo.
- **Output `syncProformaTotal()` wajib byte-identik dengan sebelum Fase 1.** Dibuktikan dengan seluruh test `tests/Feature/Invoice/` tetap hijau. `calculateTotal()` **tidak boleh membulatkan** — kembalikan `float` mentah persis seperti rumus lama; pembulatan tetap diserahkan ke kolom `decimal(15,2)` saat disimpan.
- Registry memetakan **nilai `tour.type` di database**. Transport disimpan sebagai `rental` di DB — kelasnya `TransportRule`, tetapi terdaftar di bawah kunci `'rental'`.
- Jenis tak dikenal jatuh ke aturan `tour` (perilaku pax), agar tidak ada nilai `type` yang bisa menggagalkan perhitungan.
- Komentar kode dan label yang dilihat pengguna dalam **Bahasa Indonesia**, mengikuti kode yang ada.
- Namespace: `App\Contracts\...` dan `App\Services\SalesLine\...` (PSR-4 `App\` → `app/`). Test namespace `Tests\...` → `tests/`.
- Setiap commit memakai prefiks Conventional Commits dan diakhiri baris:
  `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`
- **Di luar cakupan Fase 1:** kolom `sales_line`/`billing_quantities` (Fase 2), pengali dapat diedit & label per jenis di UI (Fase 3), label PDF (Fase 4), pemecahan `inquiryTypes.js` (Fase 5). Jangan menyentuhnya.

## Struktur Berkas

| Berkas | Tanggung jawab |
|---|---|
| `app/Services/SalesLine/Multiplier.php` | Value object pengali: `key`, `label`, `value` |
| `app/Contracts/SalesLineInvoiceRule.php` | Interface: label, pengali bawaan, hitung total |
| `app/Services/SalesLine/BaseSalesLineRule.php` | Abstrak: `calculateTotal()` + helper pax/hari/malam |
| `app/Services/SalesLine/TourRule.php` | Pengali: pax |
| `app/Services/SalesLine/MiceRule.php` | Pengali: pax (harga paket × peserta) |
| `app/Services/SalesLine/DocumentRule.php` | Pengali: pax (dokumen) |
| `app/Services/SalesLine/TicketingRule.php` | Pengali: pax (tiket) |
| `app/Services/SalesLine/GuideRule.php` | Pengali: hari |
| `app/Services/SalesLine/TransportRule.php` | Pengali: hari (terdaftar sebagai `rental`) |
| `app/Services/SalesLine/HotelRule.php` | Pengali: kamar × malam |
| `app/Services/SalesLine/SalesLineRuleRegistry.php` | Peta `tour.type` → instance aturan |
| `app/Providers/AppServiceProvider.php` | Bind registry sebagai singleton (modifikasi) |
| `app/Models/Invoice.php` | `syncProformaTotal()` lewat registry (modifikasi) |
| `tests/Unit/SalesLine/MultiplierTest.php` | Value object |
| `tests/Unit/SalesLine/CalculateTotalTest.php` | Aritmetika `calculateTotal()` |
| `tests/Unit/SalesLine/SalesLineRuleRegistryTest.php` | Resolusi kunci → aturan |
| `tests/Feature/SalesLine/RuleLabelsAndMultipliersTest.php` | Label + `defaultMultipliers()` ketujuh aturan |

---

### Task 1: Value object `Multiplier`, kontrak, dan `calculateTotal()`

**Files:**
- Create: `app/Services/SalesLine/Multiplier.php`
- Create: `app/Contracts/SalesLineInvoiceRule.php`
- Create: `app/Services/SalesLine/BaseSalesLineRule.php`
- Test: `tests/Unit/SalesLine/MultiplierTest.php`, `tests/Unit/SalesLine/CalculateTotalTest.php`

**Interfaces:**
- Consumes: model `Invoice` yang sudah ada.
- Produces:
  - `App\Services\SalesLine\Multiplier` — konstruktor `(string $key, string $label, int $value)`, properti publik `readonly` `$key`, `$label`, `$value`.
  - `App\Contracts\SalesLineInvoiceRule` — `unitPriceLabel(): string`, `defaultMultipliers(Invoice $invoice): array`, `calculateTotal(float $unitPrice, array $multipliers): float`.
  - `App\Services\SalesLine\BaseSalesLineRule` — abstrak; mengimplementasi `calculateTotal()`; menyediakan `protected paxOf(Invoice): int`, `daysOf(Invoice): int`, `nightsOf(Invoice): int`. Task 2 meng-*extend*-nya.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Unit/SalesLine/MultiplierTest.php`:

```php
<?php

namespace Tests\Unit\SalesLine;

use App\Services\SalesLine\Multiplier;
use PHPUnit\Framework\TestCase;

class MultiplierTest extends TestCase
{
    public function test_menyimpan_kunci_label_dan_nilai(): void
    {
        $m = new Multiplier('pax', 'Peserta', 10);

        $this->assertSame('pax', $m->key);
        $this->assertSame('Peserta', $m->label);
        $this->assertSame(10, $m->value);
    }
}
```

Buat `tests/Unit/SalesLine/CalculateTotalTest.php`:

```php
<?php

namespace Tests\Unit\SalesLine;

use App\Contracts\SalesLineInvoiceRule;
use App\Models\Invoice;
use App\Services\SalesLine\BaseSalesLineRule;
use App\Services\SalesLine\Multiplier;
use PHPUnit\Framework\TestCase;

class CalculateTotalTest extends TestCase
{
    /** Aturan telanjang untuk menguji hanya aritmetika BaseSalesLineRule. */
    private function rule(): SalesLineInvoiceRule
    {
        return new class extends BaseSalesLineRule {
            public function unitPriceLabel(): string
            {
                return 'Harga';
            }

            public function defaultMultipliers(Invoice $invoice): array
            {
                return [];
            }
        };
    }

    public function test_satu_pengali_mengali_harga(): void
    {
        $total = $this->rule()->calculateTotal(500_000, [new Multiplier('pax', 'Peserta', 10)]);

        $this->assertSame(5_000_000.0, $total);
    }

    public function test_dua_pengali_dikalikan_berurutan(): void
    {
        // Hotel: harga/kamar/malam × 3 kamar × 4 malam
        $total = $this->rule()->calculateTotal(500_000, [
            new Multiplier('rooms', 'Kamar', 3),
            new Multiplier('nights', 'Malam', 4),
        ]);

        $this->assertSame(6_000_000.0, $total);
    }

    public function test_tanpa_pengali_total_sama_dengan_harga(): void
    {
        $this->assertSame(750_000.0, $this->rule()->calculateTotal(750_000, []));
    }

    public function test_mengembalikan_float_tanpa_pembulatan(): void
    {
        // Angka ganjil membuktikan tidak ada round() yang menyelinap masuk.
        $total = $this->rule()->calculateTotal(333_333, [new Multiplier('pax', 'Peserta', 3)]);

        $this->assertSame(999_999.0, $total);
    }
}
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter='MultiplierTest|CalculateTotalTest'`
Expected: FAIL dengan `Class "App\Services\SalesLine\Multiplier" not found`

- [ ] **Step 3: Buat `Multiplier`**

`app/Services/SalesLine/Multiplier.php`:

```php
<?php

namespace App\Services\SalesLine;

/**
 * Satu pengali tagihan beserta namanya, mis. pax=10 atau malam=4.
 *
 * `key` dipakai untuk menyimpan di billing_quantities (Fase 2), `label` untuk
 * ditampilkan di UI (Fase 3), `value` untuk perhitungan.
 */
final class Multiplier
{
    public function __construct(
        public readonly string $key,
        public readonly string $label,
        public readonly int $value,
    ) {
    }
}
```

- [ ] **Step 4: Buat kontrak `SalesLineInvoiceRule`**

`app/Contracts/SalesLineInvoiceRule.php`:

```php
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
}
```

- [ ] **Step 5: Buat `BaseSalesLineRule`**

`app/Services/SalesLine/BaseSalesLineRule.php`:

```php
<?php

namespace App\Services\SalesLine;

use App\Contracts\SalesLineInvoiceRule;
use App\Models\Invoice;

/**
 * Menyediakan aritmetika total yang sama untuk semua jenis, plus helper
 * penurunan pengali dari data tour. Subclass cukup mengisi unitPriceLabel()
 * dan defaultMultipliers().
 */
abstract class BaseSalesLineRule implements SalesLineInvoiceRule
{
    public function calculateTotal(float $unitPrice, array $multipliers): float
    {
        $product = 1;

        foreach ($multipliers as $multiplier) {
            $product *= $multiplier->value;
        }

        // Tanpa round(): kolom decimal(15,2) yang membulatkan saat disimpan,
        // persis seperti rumus lama unit_price × pax.
        return $unitPrice * $product;
    }

    /** Ukuran rombongan, minimal 1. Sumber sama dengan rumus lama. */
    protected function paxOf(Invoice $invoice): int
    {
        return max((int) ($invoice->tour?->pax ?? $invoice->pax ?? 1), 1);
    }

    /** Jumlah hari inklusif dari rentang tanggal tour, minimal 1. */
    protected function daysOf(Invoice $invoice): int
    {
        $start = $invoice->tour?->start_date;
        $end   = $invoice->tour?->end_date;

        if (! $start || ! $end) {
            return 1;
        }

        return max((int) $start->diffInDays($end) + 1, 1);
    }

    /** Jumlah malam dari rentang tanggal tour (selisih hari), minimal 1. */
    protected function nightsOf(Invoice $invoice): int
    {
        $start = $invoice->tour?->start_date;
        $end   = $invoice->tour?->end_date;

        if (! $start || ! $end) {
            return 1;
        }

        return max((int) $start->diffInDays($end), 1);
    }
}
```

- [ ] **Step 6: Jalankan test untuk memastikan lulus**

Run: `php artisan test --filter='MultiplierTest|CalculateTotalTest'`
Expected: PASS, 5 test

- [ ] **Step 7: Commit**

```bash
git add app/Services/SalesLine/Multiplier.php app/Contracts/SalesLineInvoiceRule.php app/Services/SalesLine/BaseSalesLineRule.php tests/Unit/SalesLine/MultiplierTest.php tests/Unit/SalesLine/CalculateTotalTest.php
git commit -m "feat: kontrak SalesLineInvoiceRule + Multiplier + BaseSalesLineRule

calculateTotal = unit_price × hasil kali pengali, tanpa pembulatan — kolom
decimal yang membulatkan saat disimpan, persis rumus lama. Helper pax/hari/
malam menurunkan pengali dari data tour.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 2: Tujuh kelas aturan

**Files:**
- Create: `app/Services/SalesLine/TourRule.php`, `MiceRule.php`, `DocumentRule.php`, `TicketingRule.php`, `GuideRule.php`, `TransportRule.php`, `HotelRule.php`
- Test: `tests/Feature/SalesLine/RuleLabelsAndMultipliersTest.php`

**Interfaces:**
- Consumes: `BaseSalesLineRule`, `Multiplier` (Task 1); trait `Tests\Support\CreatesSalesFixtures` (dari Fase 0) untuk membuat invoice+tour di test.
- Produces: tujuh kelas final `App\Services\SalesLine\{Tour,Mice,Document,Ticketing,Guide,Transport,Hotel}Rule`, masing-masing meng-*extend* `BaseSalesLineRule`. Task 3 (registry) meng-instansiasi ketujuhnya.

Label dan pengali mengikuti §3.1.1 dokumen desain. `defaultMultipliers()` diuji lewat Feature test karena membaca tanggal & pax dari tour di database — memakai trait fixture Fase 0.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/SalesLine/RuleLabelsAndMultipliersTest.php`:

```php
<?php

namespace Tests\Feature\SalesLine;

use App\Services\SalesLine\DocumentRule;
use App\Services\SalesLine\GuideRule;
use App\Services\SalesLine\HotelRule;
use App\Services\SalesLine\MiceRule;
use App\Services\SalesLine\TicketingRule;
use App\Services\SalesLine\TourRule;
use App\Services\SalesLine\TransportRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

class RuleLabelsAndMultipliersTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    public function test_label_harga_per_jenis(): void
    {
        $this->assertSame('Harga / pax', (new TourRule())->unitPriceLabel());
        $this->assertSame('Harga paket / peserta', (new MiceRule())->unitPriceLabel());
        $this->assertSame('Harga / dokumen', (new DocumentRule())->unitPriceLabel());
        $this->assertSame('Harga / tiket', (new TicketingRule())->unitPriceLabel());
        $this->assertSame('Harga / hari', (new GuideRule())->unitPriceLabel());
        $this->assertSame('Harga / hari', (new TransportRule())->unitPriceLabel());
        $this->assertSame('Harga / kamar / malam', (new HotelRule())->unitPriceLabel());
    }

    public function test_jenis_berbasis_pax_mengembalikan_satu_pengali_pax(): void
    {
        // Tour default: pax 10, tanggal 2026-08-01..05.
        $invoice = $this->makeInvoice($this->makeTour('tour', ['pax' => 10]), 500_000);

        foreach ([new TourRule(), new MiceRule(), new DocumentRule(), new TicketingRule()] as $rule) {
            $m = $rule->defaultMultipliers($invoice);

            $this->assertCount(1, $m, get_class($rule));
            $this->assertSame(10, $m[0]->value, get_class($rule).' harus memakai pax');
        }
    }

    public function test_guide_dan_transport_memakai_hari_inklusif(): void
    {
        // 2026-08-01 sampai 2026-08-05 = 5 hari inklusif.
        $invoice = $this->makeInvoice($this->makeTour('guide'), 500_000);

        foreach ([new GuideRule(), new TransportRule()] as $rule) {
            $m = $rule->defaultMultipliers($invoice);

            $this->assertCount(1, $m, get_class($rule));
            $this->assertSame('hari', $m[0]->key);
            $this->assertSame(5, $m[0]->value, get_class($rule).' harus 5 hari inklusif');
        }
    }

    public function test_hotel_memakai_kamar_kali_malam(): void
    {
        // 2026-08-01 sampai 2026-08-05 = 4 malam; kamar mulai dari 1.
        $invoice = $this->makeInvoice($this->makeTour('hotel'), 500_000);

        $m = (new HotelRule())->defaultMultipliers($invoice);

        $this->assertCount(2, $m);
        $this->assertSame('rooms', $m[0]->key);
        $this->assertSame(1, $m[0]->value);
        $this->assertSame('nights', $m[1]->key);
        $this->assertSame(4, $m[1]->value);
    }

    public function test_tanpa_tanggal_hari_dan_malam_jatuh_ke_satu(): void
    {
        $invoice = $this->makeInvoice(
            $this->makeTour('guide', ['start_date' => null, 'end_date' => null]),
            500_000
        );

        $this->assertSame(1, (new GuideRule())->defaultMultipliers($invoice)[0]->value);
        $this->assertSame(1, (new HotelRule())->defaultMultipliers($invoice)[1]->value);
    }
}
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=RuleLabelsAndMultipliersTest`
Expected: FAIL dengan `Class "App\Services\SalesLine\TourRule" not found`

- [ ] **Step 3: Buat empat aturan berbasis pax**

`app/Services/SalesLine/TourRule.php`:

```php
<?php

namespace App\Services\SalesLine;

use App\Models\Invoice;

/** Tour: dijual per orang. */
final class TourRule extends BaseSalesLineRule
{
    public function unitPriceLabel(): string
    {
        return 'Harga / pax';
    }

    public function defaultMultipliers(Invoice $invoice): array
    {
        return [new Multiplier('pax', 'Peserta', $this->paxOf($invoice))];
    }
}
```

`app/Services/SalesLine/MiceRule.php`:

```php
<?php

namespace App\Services\SalesLine;

use App\Models\Invoice;

/** MICE: harga paket dikali jumlah peserta. */
final class MiceRule extends BaseSalesLineRule
{
    public function unitPriceLabel(): string
    {
        return 'Harga paket / peserta';
    }

    public function defaultMultipliers(Invoice $invoice): array
    {
        return [new Multiplier('pax', 'Peserta', $this->paxOf($invoice))];
    }
}
```

`app/Services/SalesLine/DocumentRule.php`:

```php
<?php

namespace App\Services\SalesLine;

use App\Models\Invoice;

/** Document (visa/paspor): per dokumen; nilai awal mengikuti jumlah peserta. */
final class DocumentRule extends BaseSalesLineRule
{
    public function unitPriceLabel(): string
    {
        return 'Harga / dokumen';
    }

    public function defaultMultipliers(Invoice $invoice): array
    {
        return [new Multiplier('dokumen', 'Dokumen', $this->paxOf($invoice))];
    }
}
```

`app/Services/SalesLine/TicketingRule.php`:

```php
<?php

namespace App\Services\SalesLine;

use App\Models\Invoice;

/** Ticketing: per tiket; nilai awal mengikuti jumlah peserta. */
final class TicketingRule extends BaseSalesLineRule
{
    public function unitPriceLabel(): string
    {
        return 'Harga / tiket';
    }

    public function defaultMultipliers(Invoice $invoice): array
    {
        return [new Multiplier('tiket', 'Tiket', $this->paxOf($invoice))];
    }
}
```

- [ ] **Step 4: Buat dua aturan berbasis hari**

`app/Services/SalesLine/GuideRule.php`:

```php
<?php

namespace App\Services\SalesLine;

use App\Models\Invoice;

/** Jasa Guide: per hari, dari rentang tanggal tour. */
final class GuideRule extends BaseSalesLineRule
{
    public function unitPriceLabel(): string
    {
        return 'Harga / hari';
    }

    public function defaultMultipliers(Invoice $invoice): array
    {
        return [new Multiplier('hari', 'Hari', $this->daysOf($invoice))];
    }
}
```

`app/Services/SalesLine/TransportRule.php`:

```php
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
}
```

- [ ] **Step 5: Buat aturan hotel (dua pengali)**

`app/Services/SalesLine/HotelRule.php`:

```php
<?php

namespace App\Services\SalesLine;

use App\Models\Invoice;

/** Hotel: harga per kamar per malam. Satu-satunya jenis dengan dua pengali. */
final class HotelRule extends BaseSalesLineRule
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
}
```

- [ ] **Step 6: Jalankan test untuk memastikan lulus**

Run: `php artisan test --filter=RuleLabelsAndMultipliersTest`
Expected: PASS, 5 test

- [ ] **Step 7: Commit**

```bash
git add app/Services/SalesLine/TourRule.php app/Services/SalesLine/MiceRule.php app/Services/SalesLine/DocumentRule.php app/Services/SalesLine/TicketingRule.php app/Services/SalesLine/GuideRule.php app/Services/SalesLine/TransportRule.php app/Services/SalesLine/HotelRule.php tests/Feature/SalesLine/RuleLabelsAndMultipliersTest.php
git commit -m "feat: tujuh aturan jenis penjualan dengan label dan pengali bawaan

Tour/MICE/Document/Ticketing memakai pax; Guide/Transport memakai hari
inklusif; Hotel memakai kamar × malam. defaultMultipliers diuji lewat data
tour nyata, belum dialirkan ke produksi (Fase 2).

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 3: Registry `SalesLineRuleRegistry`

**Files:**
- Create: `app/Services/SalesLine/SalesLineRuleRegistry.php`
- Test: `tests/Unit/SalesLine/SalesLineRuleRegistryTest.php`

**Interfaces:**
- Consumes: ketujuh kelas aturan (Task 2).
- Produces: `App\Services\SalesLine\SalesLineRuleRegistry` — `for(string $salesLine): SalesLineInvoiceRule`, `keys(): array`. Task 4 me-resolve-nya lewat container.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Unit/SalesLine/SalesLineRuleRegistryTest.php`:

```php
<?php

namespace Tests\Unit\SalesLine;

use App\Services\SalesLine\DocumentRule;
use App\Services\SalesLine\GuideRule;
use App\Services\SalesLine\HotelRule;
use App\Services\SalesLine\MiceRule;
use App\Services\SalesLine\SalesLineRuleRegistry;
use App\Services\SalesLine\TicketingRule;
use App\Services\SalesLine\TourRule;
use App\Services\SalesLine\TransportRule;
use PHPUnit\Framework\TestCase;

class SalesLineRuleRegistryTest extends TestCase
{
    public function test_memetakan_setiap_jenis_ke_aturannya(): void
    {
        $r = new SalesLineRuleRegistry();

        $this->assertInstanceOf(TourRule::class, $r->for('tour'));
        $this->assertInstanceOf(HotelRule::class, $r->for('hotel'));
        $this->assertInstanceOf(GuideRule::class, $r->for('guide'));
        $this->assertInstanceOf(MiceRule::class, $r->for('mice'));
        $this->assertInstanceOf(DocumentRule::class, $r->for('document'));
        $this->assertInstanceOf(TicketingRule::class, $r->for('ticketing'));
    }

    public function test_transport_terdaftar_di_bawah_kunci_rental(): void
    {
        // tours.type menyimpan 'rental', bukan 'transport'.
        $this->assertInstanceOf(TransportRule::class, (new SalesLineRuleRegistry())->for('rental'));
    }

    public function test_jenis_tak_dikenal_jatuh_ke_tour(): void
    {
        $this->assertInstanceOf(TourRule::class, (new SalesLineRuleRegistry())->for('entah-apa'));
    }

    public function test_keys_berisi_ketujuh_jenis(): void
    {
        $this->assertEqualsCanonicalizing(
            ['tour', 'hotel', 'guide', 'rental', 'mice', 'document', 'ticketing'],
            (new SalesLineRuleRegistry())->keys()
        );
    }
}
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=SalesLineRuleRegistryTest`
Expected: FAIL dengan `Class "App\Services\SalesLine\SalesLineRuleRegistry" not found`

- [ ] **Step 3: Buat registry**

`app/Services/SalesLine/SalesLineRuleRegistry.php`:

```php
<?php

namespace App\Services\SalesLine;

use App\Contracts\SalesLineInvoiceRule;

/**
 * Memetakan nilai tours.type ke aturan hitungnya. Satu-satunya tempat jenis
 * penjualan dipilih — tidak ada percabangan `if type ===` di controller/model.
 */
final class SalesLineRuleRegistry
{
    /** @var array<string, SalesLineInvoiceRule> */
    private array $rules;

    public function __construct()
    {
        $this->rules = [
            'tour'      => new TourRule(),
            'hotel'     => new HotelRule(),
            'guide'     => new GuideRule(),
            // tours.type menyimpan 'rental' untuk penjualan Transport.
            'rental'    => new TransportRule(),
            'mice'      => new MiceRule(),
            'document'  => new DocumentRule(),
            'ticketing' => new TicketingRule(),
        ];
    }

    /** Jenis tak dikenal jatuh ke aturan tour (perilaku pax) agar tak ada type yang menggagalkan hitung. */
    public function for(string $salesLine): SalesLineInvoiceRule
    {
        return $this->rules[$salesLine] ?? $this->rules['tour'];
    }

    /** @return string[] */
    public function keys(): array
    {
        return array_keys($this->rules);
    }
}
```

- [ ] **Step 4: Jalankan test untuk memastikan lulus**

Run: `php artisan test --filter=SalesLineRuleRegistryTest`
Expected: PASS, 4 test

- [ ] **Step 5: Commit**

```bash
git add app/Services/SalesLine/SalesLineRuleRegistry.php tests/Unit/SalesLine/SalesLineRuleRegistryTest.php
git commit -m "feat: SalesLineRuleRegistry memetakan tours.type ke aturan

Transport terdaftar di bawah kunci 'rental' sesuai nilai tours.type. Jenis
tak dikenal jatuh ke aturan tour agar tidak ada type yang menggagalkan hitung.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

### Task 4: Alirkan `syncProformaTotal()` lewat registry — hasil identik

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `app/Models/Invoice.php` (method `syncProformaTotal()`)
- Test: `tests/Feature/SalesLine/SyncProformaThroughRegistryTest.php`

**Interfaces:**
- Consumes: `SalesLineRuleRegistry` (Task 3), `Multiplier` (Task 1).
- Produces: `syncProformaTotal()` yang menghitung total lewat registry. Tidak ada tipe baru untuk task lain.

Bukti utama tidak-berubahnya perilaku adalah **seluruh test `tests/Feature/Invoice/` (34 test Fase 0) tetap hijau**. Task ini menambah satu Feature test yang memastikan jalur registry benar-benar dilewati, lalu menjalankan suite Fase 0 sebagai gerbang.

- [ ] **Step 1: Tulis test yang gagal**

Buat `tests/Feature/SalesLine/SyncProformaThroughRegistryTest.php`:

```php
<?php

namespace Tests\Feature\SalesLine;

use App\Contracts\SalesLineInvoiceRule;
use App\Models\Invoice;
use App\Services\SalesLine\Multiplier;
use App\Services\SalesLine\SalesLineRuleRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CreatesSalesFixtures;
use Tests\TestCase;

class SyncProformaThroughRegistryTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSalesFixtures;

    public function test_hasil_identik_dengan_rumus_lama_unit_price_kali_pax(): void
    {
        // Rumus lama untuk ketujuh jenis: total = unit_price × pax. Fase 1 tidak
        // boleh menggesernya, apa pun jenisnya.
        foreach (self::SALES_TYPES as $type) {
            $invoice = $this->makeInvoice($this->makeTour($type, ['pax' => 4]), 1_250_000);

            $this->assertEquals(5_000_000, $invoice->total, "Jenis {$type}");
        }
    }

    public function test_syncProformaTotal_benar_benar_memakai_registry(): void
    {
        // Ganti registry di container dengan objek palsu yang selalu memberi
        // aturan pengali ×1000, membuktikan syncProformaTotal memanggil registry,
        // bukan menghitung sendiri. Objek palsu TIDAK meng-extend registry (kelas
        // itu final); container mengembalikan apa pun yang di-bind, dan
        // syncProformaTotal hanya memanggil ->for()->calculateTotal().
        $this->app->instance(SalesLineRuleRegistry::class, new class {
            public function for(string $salesLine): SalesLineInvoiceRule
            {
                return new class implements SalesLineInvoiceRule {
                    public function unitPriceLabel(): string
                    {
                        return 'x';
                    }

                    public function defaultMultipliers(Invoice $invoice): array
                    {
                        return [];
                    }

                    public function calculateTotal(float $unitPrice, array $multipliers): float
                    {
                        return $unitPrice * 1000;
                    }
                };
            }
        });

        $invoice = $this->makeInvoice($this->makeTour('tour', ['pax' => 4]), 1_000);

        // Bila registry dipakai: 1.000 × 1000 = 1.000.000. Bila tidak: 1.000 × 4.
        $this->assertEquals(1_000_000, $invoice->total);
    }
}
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run: `php artisan test --filter=SyncProformaThroughRegistryTest`
Expected: FAIL pada `test_syncProformaTotal_benar_benar_memakai_registry` — total masih `4.000` (registry belum dipakai). Test pertama mungkin sudah lulus (perilaku lama kebetulan sama).

- [ ] **Step 3: Bind registry sebagai singleton**

Pada `app/Providers/AppServiceProvider.php`, di dalam `register()`, tambahkan setelah bind `BrevoGateway`:

```php
        $this->app->singleton(\App\Services\SalesLine\SalesLineRuleRegistry::class);
```

- [ ] **Step 4: Alirkan `syncProformaTotal()` lewat registry**

Pada `app/Models/Invoice.php`, tambahkan import bersama import lain di atas kelas:

```php
use App\Services\SalesLine\Multiplier;
use App\Services\SalesLine\SalesLineRuleRegistry;
```

Ganti seluruh isi method `syncProformaTotal()` menjadi:

```php
    public function syncProformaTotal(): void
    {
        $pax  = max((int) ($this->tour?->pax ?? $this->pax ?? 1), 1);
        $rule = app(SalesLineRuleRegistry::class)->for($this->tour?->type ?? 'tour');

        // Fase 1: pengali tetap pax untuk SEMUA jenis, supaya total identik
        // dengan rumus lama (unit_price × pax). Fase 2 mengganti sumber pengali
        // ke kolom billing_quantities agar guide dihitung per hari, hotel per
        // kamar × malam, dst.
        $total = $rule->calculateTotal((float) $this->unit_price, [
            new Multiplier('pax', 'Peserta', $pax),
        ]);

        // Simpan pax yang dipakai menghitung total — PDF menampilkan pax invoice,
        // jadi keduanya harus selalu berasal dari angka yang sama.
        $updates = ['total' => $total, 'pax' => $pax];
        if (($this->currency ?: 'IDR') === 'IDR') {
            $updates['total_idr'] = $total;
        }

        $this->update($updates);
    }
```

Perbarui docblock method agar menyebut registry (opsional tetapi disarankan): ganti kalimat pertama menjadi "Hitung ulang total proforma lewat aturan jenis penjualan (Fase 1: pengali pax, hasil identik rumus lama)."

- [ ] **Step 5: Jalankan test task ini untuk memastikan lulus**

Run: `php artisan test --filter=SyncProformaThroughRegistryTest`
Expected: PASS, 2 test

- [ ] **Step 6: GERBANG IDENTITAS — jalankan seluruh test karakterisasi Fase 0**

Run: `php artisan test tests/Feature/Invoice`
Expected: PASS semua (34 test). **Bila ada yang memerah, perilaku bergeser — hentikan dan periksa `calculateTotal`/wiring, jangan ubah test Fase 0.**

- [ ] **Step 7: Jalankan seluruh suite**

Run: `php artisan test`
Expected: PASS semua — 102 test lama + 16 test baru Fase 1 (Task 1: 5, Task 2: 5, Task 3: 4, Task 4: 2) = 118.

- [ ] **Step 8: Commit**

```bash
git add app/Providers/AppServiceProvider.php app/Models/Invoice.php tests/Feature/SalesLine/SyncProformaThroughRegistryTest.php
git commit -m "feat: syncProformaTotal menghitung lewat SalesLineRuleRegistry

Perhitungan dialirkan lewat aturan per jenis, tetapi Fase 1 tetap memakai
pengali pax untuk semua jenis sehingga total byte-identik dengan rumus lama.
Dibuktikan seluruh 34 test karakterisasi Fase 0 tetap hijau.

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Verifikasi Akhir Fase 1

```bash
php artisan test
php artisan test tests/Feature/Invoice     # gerbang identitas Fase 0
```

Yang harus terbukti oleh test:

| Properti | Dibuktikan oleh |
|---|---|
| `calculateTotal` = harga × Π pengali, tanpa pembulatan | `CalculateTotalTest` |
| Label harga benar per jenis | `RuleLabelsAndMultipliersTest::test_label_harga_per_jenis` |
| Pengali bawaan benar (pax / hari / kamar×malam) | `RuleLabelsAndMultipliersTest` |
| Registry memetakan `tours.type`, `rental`→Transport, fallback tour | `SalesLineRuleRegistryTest` |
| `syncProformaTotal` memakai registry | `SyncProformaThroughRegistryTest::test_syncProformaTotal_benar_benar_memakai_registry` |
| **Output byte-identik dengan rumus lama** | 34 test `tests/Feature/Invoice/` tetap hijau |

**Definisi selesai:** seluruh 118 test hijau, dan tidak ada satu pun test karakterisasi Fase 0 yang diubah. Verifikasi test Fase 0 tak tersentuh:

```bash
git diff dev...HEAD --stat -- tests/Feature/Invoice
```

Expected: kosong. Bila ada berkas `tests/Feature/Invoice/` yang berubah, gerbang identitas dikompromikan — periksa.

## Cakupan Spec

| Bagian spec | Task |
|---|---|
| §3.1 kontrak + BaseSalesLineRule (calculateTotal generik) | Task 1 |
| §3.1 struktur `Services/SalesLine/` | Task 1–3 |
| §3.1.1 label & pengali bawaan ketujuh jenis | Task 2 |
| §3.1 registry (satu-satunya percabangan jenis) | Task 3 |
| §4 Fase 1 — dipanggil dari `syncProformaTotal`, hasil identik | Task 4 |
| §3.4.1/§7.4 tidak menambah pemanggil `syncProformaTotal`, tidak mengubah sifat tak-terjaga | Task 4 (hanya mengubah isi method) |

**Bagian spec yang sengaja belum dikerjakan:** kolom `sales_line`/`billing_quantities` dan pengaliran `defaultMultipliers()` ke produksi (Fase 2); pengali dapat diedit + label per jenis di UI (Fase 3); label PDF (Fase 4); `sales-lines/*.js` (Fase 5). Masing-masing plan sendiri.
