# Welcome Manado ERP — Master Reference & Project Status

> Patokan tunggal proyek. Update: 2026-06-07.
> Stack: **Laravel 13 + Inertia.js v2 + Vue 3 + MySQL 8 + shadcn/vue + Tailwind + Breeze + barryvdh/laravel-dompdf + Ziggy**
> File ini = sumber kebenaran (arsitektur + status). Kode implementasi penuh ada di file pendamping:
> `ROLE_ACCESS_MATRIX_SETUP.md` (peran) · `FINANCE_MODULE_SETUP.md` (keuangan).

---

## 1. Status per milestone (ringkas)

| Milestone | Status | Catatan / Ref |
|---|---|---|
| M1 — Master Data | ✅ Selesai | Supplier, Product, Customer (CRUD + search/filter) |
| M2 — Tour Builder | ✅ Selesai | item inline + dialog tambah produk + panel costing realtime |
| M3 — Quotation + Pipeline | ✅ Selesai | PDF branded (dompdf) + Dashboard pipeline |
| M4 — Operations | ✅ Selesai (data) | `assignments` + `Manifest.vue`. **Cara antar diubah** → lihat M5 |
| **M5 — Peran & Akses** | 🔜 Dirancang | `ROLE_ACCESS_MATRIX_SETUP.md` |
| **M6 — Keuangan (profit riil)** | 🔜 Dirancang | `FINANCE_MODULE_SETUP.md` |

**Perubahan keputusan penting (M4 → M5):** manifest **signed-link** diganti menjadi **login berbasis peran** — guide/driver/tour_leader login lalu melihat *My Jobs* mereka. `Manifest.vue` di-repurpose jadi `MyJobs/Show.vue`. Signed-link kini hanya **opsional** untuk orang eksternal tanpa akun.

### Pra-produksi (belum dikerjakan)
- [ ] Deploy ke server publik + set `APP_URL` (akses tim & link butuh ini)
- [ ] Backup DB otomatis (`mysqldump` terjadwal)
- [ ] Lengkapi master data: produk restaurant, attraction, guide (baru hotel + transport)
- [ ] Form Request validation di semua controller
- [ ] Verifikasi snapshot: ubah harga produk → total tour lama TIDAK berubah

---

## 2. Konsep inti (JANGAN sampai lupa)

1. **Snapshot harga** — saat produk ditambah ke tour, `cost`/`sell` DISALIN ke `tour_items.unit_cost`/`unit_sell`. Bukan referensi harga live. (Kesalahan #1 yang harus dihindari.)
2. **Profit = query, bukan modul** — `total_cost`, `total_sell`, `profit`, `margin` adalah accessor di `Tour`, dihitung dari sum `tour_items`.
3. **`line_cost` & `line_sell` = generated columns** MySQL (`qty * nights * unit_*`). Jangan isi manual → butuh **MySQL 8+**.
4. **Pipeline status** — `tours.status`: `inquiry → quotation_draft → quotation_sent → follow_up → negotiation → confirmed → cancelled`.
5. **Peran (M5)** — `users.role`: `admin | sales | accountant | guide | driver | tour_leader`. 4 tingkat akses; `guide/driver/tour_leader` = tingkat **field** (hanya jadwal sendiri, TANPA cost/profit).
6. **Expected vs Actual (M6)** — angka tour = perkiraan (snapshot). Modul keuangan menambah biaya **aktual** (`bills`) → **profit riil** = `total_sell − SUM(bills.amount)`.
7. **Keuangan SEDERHANA** — AR/AP/kas saja, BUKAN general ledger. Pembukuan resmi diekspor ke Accurate.

---

## 3. Cara menjalankan (lokal)

```bash
composer install
npm install
cp .env.example .env          # set DB_DATABASE=welcome_manado, dst (jika belum)
php artisan key:generate      # jika belum
php artisan migrate
npm run dev                    # mode Vite; atau: npm run build
php artisan serve
```
Wajib **MySQL 8.0+** (generated columns).

---

## 4. Data model (semua tabel)

**Migrations:**
- `2026_06_06_000000_create_welcome_manado_core_tables.php` — inti
- `2026_06_07_000000_add_roles_and_link_assignments.php` — M5
- `2026_06_07_010000_create_finance_tables.php` — M6

| Tabel | Inti | Keterangan |
|---|---|---|
| `suppliers` | ✅ | hotel/transport/guide/resto/attraction |
| `products` | ✅ | **cost** & **sell** (sumber snapshot) |
| `customers` | ✅ | type (agent/corporate/direct) + `country` (profit-by-market) |
| `tours` | ✅ | header + `status` pipeline + `code` WM-YYYY-XXXX |
| `tour_items` | ✅ | baris produk; **snapshot** `unit_cost`/`unit_sell`; `line_*` generated |
| `assignments` | ✅ | guide/driver/tour_leader; **`user_id`** (M5) tautan ke akun |
| `users` | ✅ | **`role`** (M5) |
| `invoices` + `invoice_payments` | M6 | AR — tagihan & uang masuk customer |
| `bills` + `bill_payments` | M6 | AP — **biaya aktual** & uang keluar supplier |

---

## 5. Model & kode kanonik

### `Tour` — jantung sistem (perkiraan + aktual)
```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Tour extends Model
{
    protected $guarded = [];
    protected $casts = ['start_date' => 'date', 'end_date' => 'date'];

    public function customer()    { return $this->belongsTo(Customer::class); }
    public function items()       { return $this->hasMany(TourItem::class); }
    public function assignments() { return $this->hasMany(Assignment::class); }
    public function invoices()    { return $this->hasMany(Invoice::class); } // M6
    public function bills()       { return $this->hasMany(Bill::class); }    // M6

    // --- PERKIRAAN (dari snapshot tour_items) ---
    public function getTotalCostAttribute(): float { return (float) $this->items->sum('line_cost'); }
    public function getTotalSellAttribute(): float { return (float) $this->items->sum('line_sell'); }
    public function getProfitAttribute(): float    { return $this->total_sell - $this->total_cost; }
    public function getMarginAttribute(): float    { return $this->total_sell > 0 ? round($this->profit / $this->total_sell * 100, 1) : 0; }

    // --- AKTUAL (M6, butuh tabel keuangan) ---
    public function getActualCostAttribute(): float   { return (float) $this->bills->sum('amount'); }
    public function getActualProfitAttribute(): float { return $this->total_sell - $this->actual_cost; }
    public function getCostVarianceAttribute(): float { return $this->actual_cost - $this->total_cost; } // + = boros
    public function getReceivedAttribute(): float     { return (float) $this->invoices->flatMap->payments->sum('amount'); }
    public function getReceivableAttribute(): float   { return (float) $this->invoices->sum('total') - $this->received; }

    protected static function booted(): void
    {
        static::creating(function (Tour $t) {
            if (! $t->code) {
                $year = now()->year;
                $seq  = static::whereYear('created_at', $year)->count() + 1;
                $t->code = sprintf('WM-%d-%04d', $year, $seq);
            }
        });
    }
}
```

### `TourItem` — helper snapshot (WAJIB dipakai saat tambah produk)
```php
public static function fromProduct(Product $p, array $extra = []): self
{
    return new self(array_merge([
        'product_id'   => $p->id,
        'product_type' => $p->type,
        'description'  => $p->name,
        'unit_cost'    => $p->cost,   // SNAPSHOT
        'unit_sell'    => $p->sell,   // SNAPSHOT
        'currency'     => $p->currency,
        'qty'          => 1,
        'nights'       => 1,
    ], $extra));
}
// protected $guarded = ['id', 'line_cost', 'line_sell'];  // line_* di-generate DB
```

### `User` — peran & pendaratan (M5)
```php
public function assignments() { return $this->hasMany(Assignment::class); }

public function isAdmin(): bool      { return $this->role === 'admin'; }
public function isSales(): bool      { return $this->role === 'sales'; }
public function isAccountant(): bool { return $this->role === 'accountant'; }
public function isField(): bool      { return in_array($this->role, ['guide','driver','tour_leader']); }

public function homePath(): string {
    return match (true) {
        $this->isAdmin(), $this->isSales() => route('dashboard', absolute: false),
        $this->isAccountant()              => route('finance.index', absolute: false),
        default                            => route('my-jobs', absolute: false),
    };
}
```

> `Supplier`, `Product`, `Customer`, `Assignment` = model ringan (relasi + `$guarded = []`). `Invoice`, `InvoicePayment`, `Bill`, `BillPayment` → kode penuh di `FINANCE_MODULE_SETUP.md`.

---

## 6. Akses & peran (M5)

`field` = `guide` / `driver` / `tour_leader` (akses identik).

| Area | admin | sales | accountant | field |
|---|:--:|:--:|:--:|:--:|
| Suppliers | ✅ | – | – | – |
| Products | ✅ | ✅ | – | – |
| Customers / Inquiry / Tour / Quotation | ✅ | ✅ | – | – |
| Costing / profit per tour | ✅ | ✅¹ | – | – |
| Pipeline dashboard | ✅ | ✅ | – | – |
| Keuangan (M6) | ✅ | – | ✅ | – |
| My Jobs (jadwal sendiri) | – | – | – | ✅ |
| Kelola akun / Tim | ✅ | – | – | – |

¹ Default: sales melihat cost/margin. Bisa dimatikan (lihat `ROLE_ACCESS_MATRIX_SETUP.md`).

Mekanisme: middleware ber-parameter `EnsureUserHasRole` (alias `role`), dipakai `->middleware('role:admin,sales')`. Redirect login & tolak-akses memakai `User::homePath()`. Kode penuh: `ROLE_ACCESS_MATRIX_SETUP.md`.

---

## 7. Keuangan & profit riil (M6)

| Metrik | Rumus |
|---|---|
| Biaya aktual / tour | `SUM(bills.amount)` |
| **Profit riil** / tour | `total_sell − biaya aktual` |
| Cost variance | `biaya aktual − total_cost` ( + = boros ) |
| Piutang (AR) | `SUM(invoices.total) − SUM(invoice_payments.amount)` |
| Hutang (AP) | `SUM(bills.amount) − SUM(bill_payments.amount)` |

**AR ↔ AP itu cermin** — bangun invoice (AR) dulu, salin pola untuk bill (AP). Kode penuh + dashboard: `FINANCE_MODULE_SETUP.md`.

---

## 8. Fitur per layar

| Layar | Status |
|---|---|
| Suppliers / Products / Customers (Index + Form) | ✅ |
| Tours/Index (filter status + profit) | ✅ |
| Tours/Edit — Tour Builder + panel costing | ✅ |
| Quotation PDF (preview + download) | ✅ |
| Dashboard pipeline | ✅ |
| Manifest.vue (publik signed) | ✅ → di-repurpose ke MyJobs/Show |
| **MyJobs/Index + Show** (field) | 🔜 M5 |
| **Finance/Index** (dashboard) + **Finance/Tour** (Budget vs Actual) | 🔜 M6 |
| Dialog assignment → dropdown user | 🔜 M5 |
| Navigasi per peran di AuthenticatedLayout | 🔜 M5 |

---

## 9. Query profit (gratis, tanpa modul)

```php
// Profit PERKIRAAN per negara
Tour::where('status','confirmed')
  ->join('customers','customers.id','=','tours.customer_id')
  ->join('tour_items','tour_items.tour_id','=','tours.id')
  ->groupBy('customers.country')
  ->selectRaw('customers.country, SUM(tour_items.line_sell) - SUM(tour_items.line_cost) AS profit')
  ->get();

// Profit RIIL per tour (M6): total_sell − SUM(bills.amount)
```

---

## 10. Yang JANGAN dibangun
- **General Ledger / double-entry / jurnal / chart of accounts** → ekspor ke Accurate.
- **Multi-currency penuh** → IDR dulu.
- **Mesin pajak / e-Faktur**, **rekonsiliasi bank** → di luar scope.
- **Driver/Guide mobile app** → cukup login + My Jobs.
- **AI Module** → fase jauh.
- **Seasonal pricing** → nanti via `product_rates` (valid_from/valid_to).

---

## 11. Roadmap berikutnya (setelah M5 & M6)
Reservation tasks → Purchasing (sumber `bills`) → integrasi Accurate → `product_rates` seasonal → B2B Agent Portal → AI. Semua menumpuk di atas fondasi yang sama.

## 12. Cara lanjut (prompt sesi berikut)
```
Implementasikan M5 (Peran) dari ROLE_ACCESS_MATRIX_SETUP.md — middleware, routes, MyJobController.
Implementasikan M6 (Keuangan) dari FINANCE_MODULE_SETUP.md — migration, model, lalu Invoice (AR).
buat Finance/Tour.vue dengan panel Budget vs Actual.
buat MyJobs/Show.vue dari Manifest.vue yang sudah ada.
```
