# Welcome Manado ERP — Project Status

> Update terakhir: 2026-06-07
> **Patokan implementasi: `WELCOME_MANADO_MVP_SPEC.md`** (sumber kebenaran arsitektur + keputusan).
> File pendamping: `ROLE_ACCESS_MATRIX_SETUP.md` (M5) · `FINANCE_MODULE_SETUP.md` (M6)
> Stack: Laravel 13 + Inertia.js v2 + Vue 3 + MySQL 8 + shadcn/vue + Tailwind + Breeze + dompdf + Ziggy

---

## Status Milestone

| # | Milestone | Status | Catatan |
|---|---|---|---|
| M1 | Master Data | ✅ Selesai | Supplier, Product, Customer — CRUD + search/filter |
| M2 | Tour Builder | ✅ Selesai | Item inline + dialog tambah produk + panel costing realtime |
| M3 | Quotation + Pipeline | ✅ Selesai | PDF branded (dompdf) + Dashboard pipeline |
| M4 | Operations | ✅ Selesai (data) | `assignments` dibuat. **Manifest signed-link → diganti M5** (lihat bawah) |
| **M5** | **Peran & Akses** | ✅ Selesai | Middleware `role`, MyJobs, nav per peran, assignment linked user |
| **M6** | **Keuangan (profit riil)** | ✅ Selesai | AR/AP, Budget vs Actual, Finance/Index + Finance/Tour |
| **M7** | **Katalog Tour + Sumber Inquiry** | ✅ Selesai | `tour_packages` seeded 72 paket (18 Manado + 54 Intl), Create form: source picker + package search |

### Keputusan penting M5 (selesai 2026-06-07)
- `Manifest.vue` di-repurpose → `MyJobs/Show.vue` (authenticated, tanpa cost/profit)
- Signed-link manifest tetap ada sebagai opsional untuk eksternal tanpa akun
- Login redirect pakai `User::homePath()` — field user langsung ke `/my-jobs`
- Assignment dialog di Tours/Edit sekarang punya dropdown "Link ke Akun" (auto-fill nama & role)

---

## Pra-produksi (belum dikerjakan)

- [ ] Deploy ke server publik + set `APP_URL`
- [ ] Backup DB otomatis (`mysqldump` terjadwal)
- [ ] Lengkapi master data: produk restaurant, attraction, guide (baru ada hotel + transport)
- [ ] Form Request validation di semua controller
- [ ] Verifikasi snapshot: ubah harga produk → total tour lama TIDAK berubah

---

## Stack & Tools

| Layer | Library/Tool | Versi |
|---|---|---|
| Framework | Laravel | ^13.8 |
| Frontend bridge | inertia-laravel | ^2.0 |
| Frontend framework | Vue 3 + Vite | dari Breeze |
| Auth scaffolding | Laravel Breeze | ^2.4 |
| UI Components | shadcn/vue | components.json ✅ |
| CSS | Tailwind CSS | ✅ |
| PDF | barryvdh/laravel-dompdf | ^3.1 |
| DB | MySQL 8+ | `welcome_manado` |
| Route helper | tightenco/ziggy | ^2.0 |

---

## Database Migrations

| File Migration | Status | Isi |
|---|---|---|
| `2026_06_06_000000_create_welcome_manado_core_tables.php` | ✅ Migrated | Tabel inti M1–M4 |
| `2026_06_07_000000_add_roles_and_link_assignments.php` | ✅ Migrated | `users.role`, `assignments.user_id` |
| `2026_06_07_010000_create_finance_tables.php` | ✅ Migrated | `invoices`, `invoice_payments`, `bills`, `bill_payments` |
| `2026_06_07_020000_add_package_and_source_to_tours.php` | ✅ Migrated | `tour_packages` + `tours.package_id` + `tours.inquiry_source` |

### Tabel yang sudah ada

| Tabel | Status | Keterangan |
|---|---|---|
| `users` | ✅ | Bawaan Breeze; perlu tambah `role` di M5 |
| `suppliers` | ✅ | hotel/transport/guide/resto/attraction |
| `products` | ✅ | `cost` & `sell` — sumber snapshot |
| `customers` | ✅ | type (agent/corporate/direct) + `country` |
| `tours` | ✅ | header + `status` pipeline + `code` WM-YYYY-XXXX |
| `tour_items` | ✅ | snapshot `unit_cost`/`unit_sell`; `line_*` generated columns |
| `assignments` | ✅ | guide/driver/tour_leader; perlu tambah `user_id` di M5 |

---

## Fitur Layar — Status

| Layar | Status |
|---|---|
| Auth (login, register, forgot/reset password, profile) | ✅ |
| Suppliers / Products / Customers (Index + Form) | ✅ |
| Tours/Index (filter status + profit per baris) | ✅ |
| Tours/Create + Tours/Edit (Tour Builder + costing panel) | ✅ |
| Quotation PDF (preview + download, branded) | ✅ |
| Dashboard pipeline (stats cards, funnel, upcoming) | ✅ |
| **MyJobs/Index + Show** (halaman field: jadwal sendiri) | ✅ M5 |
| Dialog assignment → pilih dari dropdown `users` | ✅ M5 |
| Navigasi `AuthenticatedLayout` per peran | ✅ M5 |
| **Finance/Index** (dashboard keuangan) | ✅ M6 |
| **Finance/Tour** (Budget vs Actual per tour) | ✅ M6 |
| **Tours/Create** — source picker (Website/External) + package search | ✅ M7 |

---

## Struktur File (M1–M4)

```
app/
  Http/Controllers/
    Auth/                        ✅ lengkap (Breeze)
    AssignmentController.php     ✅ store, update, destroy
    CustomerController.php       ✅ CRUD + search
    DashboardController.php      ✅ pipeline stats
    ManifestController.php       ✅ public signed page (opsional, eksternal)
    MyJobsController.php         ✅ index + show (field user)
    ProductController.php        ✅ CRUD + filter type
    ProfileController.php        ✅
    QuotationController.php      ✅ download + preview PDF
    SupplierController.php       ✅ CRUD
    TourController.php           ✅ CRUD + manifestUrl prop
    TourItemController.php       ✅ store, update, destroy
  Models/
    Assignment.php  ✅  user_id FK ke users (M5 selesai)
    Customer.php    ✅
    Product.php     ✅
    Supplier.php    ✅
    Tour.php        ✅  accessor: total_cost/sell/profit/margin + auto kode + relasi package()
    TourPackage.php ✅  72 paket seeded (18 Manado + 54 Internasional)
    TourItem.php    ✅  fromProduct() snapshot + generated columns
    User.php        ✅  role + isAdmin/isField/homePath() (M5 selesai)

resources/
  js/Pages/
    Auth/               ✅ lengkap
    Customers/          ✅ Index.vue + Form.vue
    Dashboard.vue       ✅ pipeline + stats cards
    Manifest.vue        ✅ publik signed (opsional, eksternal)
    MyJobs/             ✅ Index.vue + Show.vue (field user)
    Products/           ✅ Index.vue + Form.vue
    Profile/            ✅ lengkap
    Suppliers/          ✅ Index.vue + Form.vue
    Tours/              ✅ Index.vue + Create.vue + Edit.vue
  views/
    quotation.blade.php ✅ branded PDF template

routes/
  web.php   ✅ semua route domain + manifest (signed, akan diubah M5)
  auth.php  ✅ lengkap

database/
  migrations/
    2026_06_06_000000_create_welcome_manado_core_tables.php ✅
  seeders/
    WelcomeMaadoImportSeeder.php ✅ 53 hotel + 6 transport
```

---

## Konsep Inti (WAJIB — jangan sampai lupa)

1. **Snapshot harga** — saat produk ditambah ke tour, `cost`/`sell` DISALIN ke `tour_items.unit_cost`/`unit_sell`. Bukan referensi harga live. (Kesalahan #1 yang harus dihindari.)
2. **Profit = query, bukan modul** — `total_cost`, `total_sell`, `profit`, `margin` adalah accessor di `Tour`, dihitung dari sum `tour_items`.
3. **`line_cost` & `line_sell` = generated columns** MySQL (`qty * nights * unit_*`). Jangan isi manual → wajib **MySQL 8+**.
4. **Pipeline status** — `tours.status`: `inquiry → quotation_draft → quotation_sent → follow_up → negotiation → confirmed → cancelled`.
5. **Peran (M5)** — `users.role`: `admin | sales | accountant | guide | driver | tour_leader`. Field = guide/driver/tour_leader (hanya jadwal sendiri, TANPA cost/profit).
6. **Expected vs Actual (M6)** — angka tour = perkiraan (snapshot). Modul keuangan menambah biaya aktual (`bills`) → **profit riil** = `total_sell − SUM(bills.amount)`.
7. **Keuangan SEDERHANA** — AR/AP/kas saja. Bukan general ledger. Pembukuan resmi ekspor ke Accurate.
8. **Katalog paket (M7)** — `tour_packages` 72 paket dari website (18 Manado + 54 Intl). `tours.package_id` FK nullable. `tours.inquiry_source` enum(website, external). Sales pilih paket → judul auto-fill, atau isi manual.

---

## Yang JANGAN dibangun (dari spec)

- General Ledger / double-entry / jurnal / chart of accounts
- Multi-currency penuh (IDR dulu)
- Mesin pajak / e-Faktur / rekonsiliasi bank
- Driver/Guide mobile app (cukup login + My Jobs)
- AI Module
- Seasonal pricing (nanti via `product_rates`)

---

## Cara Lanjut

```
# M5 — Peran & Akses
Implementasikan M5 dari ROLE_ACCESS_MATRIX_SETUP.md — middleware EnsureUserHasRole, routes per peran, MyJobsController, MyJobs/Index.vue + Show.vue.

# M6 — Keuangan
Implementasikan M6 dari FINANCE_MODULE_SETUP.md — migration finance tables, model Invoice/Bill, lalu Finance/Index.vue dan Finance/Tour.vue (Budget vs Actual).
```
