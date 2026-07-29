# Desain: Batasi Kartu Keuangan di Pipeline Dashboard ke Admin+Accountant

> Status (29 Jul 2026): disetujui, siap dieksekusi.

## 1. Masalah

Pipeline Dashboard (`/dashboard`, `role:admin,sales`) menampilkan angka finansial company-wide ke role `sales`:

- Kartu ringkas atas: **Profit Riil**, **Diterima Bulan Ini**
- Box **"Ringkasan Keuangan"**: Nilai Confirmed, Biaya Aktual, Profit Riil, Diterima Bln Ini, Piutang (AR), Hutang (AP)

Modul Keuangan (`/finance/*`) sendiri sudah dibatasi `role:admin,accountant` (lihat `docs/design-system/10-keuangan-ar-ap.md`). Dashboard adalah satu-satunya tempat yang membocorkan ringkasan finansial company-wide ke role lain.

## 2. Keputusan yang sudah dikunci

| # | Keputusan |
|---|---|
| D1 | Hanya `admin` dan `accountant` yang boleh melihat data finansial di Dashboard |
| D2 | Cakupan **hanya** Pipeline Dashboard — panel "Ringkasan Biaya" per-tour milik sales dan PDF "Rincian Profit" per-invoice **tidak disentuh**, tetap seperti sekarang |
| D3 | Sales tetap bisa akses halaman `/dashboard` — Pipeline Status, Tour Confirmed Mendatang, Tour Terbaru, Total Tours, Confirmed tetap tampil seperti biasa |
| D4 | Gating dilakukan di **backend** (data finansial tidak dihitung/dikirim untuk role yang tidak berhak), bukan cuma `v-if` di Vue — supaya angka tidak bisa dilihat lewat Network tab/View Source |
| D5 | Tidak ada perubahan pada route, middleware, controller, atau halaman lain di luar `DashboardController.php` dan `Dashboard.vue` |

## 3. Arsitektur

### Backend — `DashboardController::index()`

```php
$canViewFinance = $user->isAdmin() || $user->isAccountant();

// confirmedSell, actualCost, realProfit, arOutstanding, apOutstanding,
// cashInMonth — HANYA dihitung kalau $canViewFinance true. Query-nya
// dibungkus kondisi, bukan dihitung lalu disembunyikan.
```

Field yang dikirim ke Inertia:
- `canViewFinance: bool` — flag baru
- Keenam field finansial (`confirmedSell`, `actualCost`, `realProfit`, `arOutstanding`, `apOutstanding`, `cashInMonth`) — **tidak disertakan sama sekali** dalam payload kalau `canViewFinance` false (bukan `null`, benar-benar absen dari props)

Field lain (`pipeline`, `totalTours`, `totalConfirmed`, `recentTours`, `upcomingConfirmed`) tidak berubah, dikirim untuk semua role seperti sekarang.

### Frontend — `Dashboard.vue`

Kartu "Profit Riil", "Diterima Bulan Ini" (di baris kartu atas), dan seluruh box "Ringkasan Keuangan" dibungkus `v-if="canViewFinance"`. Tanpa akses, layout menyesuaikan (grid kartu atas otomatis jadi 2 kolom alih-alih 4, karena Vue/Tailwind grid me-reflow otomatis begitu elemen dihapus dari DOM — tidak perlu perubahan CSS eksplisit).

## 4. Testing

Test baru `tests/Feature/DashboardFinanceVisibilityTest.php`:
1. Request sebagai **sales** → assert `canViewFinance === false` DAN keenam key finansial **tidak ada** di props Inertia (`->missing(...)` untuk tiap key).
2. Request sebagai **admin** → assert `canViewFinance === true` dan keenam field muncul dengan nilai benar.

**Catatan:** middleware route `/dashboard` saat ini `role:admin,sales` — `accountant` sama sekali tidak punya akses ke halaman ini (dan itu tidak diubah di sini, lihat D5). Jadi klausa "accountant" di `$canViewFinance` bersifat future-proofing sesuai D1, tapi efek praktis perbaikan ini hari ini adalah: **admin tetap lihat data finansial, sales tidak** — tidak perlu test terpisah untuk accountant karena rolenya tidak bisa mencapai controller ini sama sekali.

## 5. Di luar lingkup

- Mengubah middleware route `/dashboard` (siapa yang boleh membuka halamannya sama sekali)
- Panel "Ringkasan Biaya" per-tour (`CostingPanel`/`InvoicesPanel.vue`) — sales tetap lihat profit/margin tour miliknya sendiri
- PDF "Rincian Profit" (`invoices.profit-pdf`) — sales tetap bisa unduh
- Modul Keuangan (`/finance/*`) — sudah `role:admin,accountant`, tidak disentuh
