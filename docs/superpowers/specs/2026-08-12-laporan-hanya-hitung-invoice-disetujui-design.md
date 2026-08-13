# Laporan keuangan hanya menghitung invoice yang sudah disetujui

Tanggal: 2026-08-12
Laporan terdampak: Laba Rugi, Neraca, Saldo Akun, Dashboard, Fiskal

## 1. Masalah

Enam pemanggilan di empat controller menjumlahkan `invoices` tanpa menyaring
`approved_at`. Proforma yang masih draft — belum diterima Keuangan — ikut
terhitung sebagai penjualan, piutang, laba ditahan, dan peredaran bruto pajak.

Terukur pada data production, tahun 2026:

| | Invoice | Nilai |
| --- | ---: | ---: |
| Seluruh invoice 2026 | 29 | Rp 639.870.246,02 |
| **Belum disetujui** | **17** | **Rp 369.794.436,02** |
| Sudah disetujui | 12 | Rp 270.075.810,00 |

**57,8% dari angka "Total Penjualan" di Laba Rugi berasal dari invoice yang
belum disetujui.**

Ini bukan ambiguitas desain. `FinanceController` sudah menyaring dengan benar
(`Invoice::approved()->sum(...)` pada baris 21, 44, dan 46), dan `Invoice`
sudah menyediakan `scopeApproved()`. Enam tempat inilah yang menyimpang dari
konvensi yang berlaku di codebase ini, bukan sebaliknya.

## 2. Enam tempat yang diperbaiki

| Berkas | Baris | Laporan | Yang salah |
| --- | ---: | --- | --- |
| `FinanceReportController` | 512 | Laba Rugi | Total Penjualan & penjualan per lini bisnis |
| `FinanceReportController` | 362 | Neraca | Piutang (AR) |
| `FinanceReportController` | 433 | Neraca | Laba ditahan (ekuitas) |
| `FinanceReportController` | 329 | Saldo Akun | Piutang (AR) |
| `DashboardController` | 70 | Dashboard | Piutang beredar |
| `FiscalController` | 37 | Fiskal | Peredaran bruto — **dasar hitung pajak** |

Perbaikannya menambahkan `->approved()` pada masing-masing kueri. Tidak ada
logika baru yang ditulis; scope-nya sudah ada.

## 3. Kenapa Neraca wajib dikoreksi berpasangan

Piutang (aset) dan laba ditahan (ekuitas) sama-sama membesar oleh nilai draft
yang sama persis. Keduanya berada di sisi berlawanan pada persamaan neraca,
sehingga **Neraca tetap seimbang meski kedua sisinya salah**. Kesalahan yang
saling menutupi seperti ini tidak akan pernah terdeteksi dari selisih neraca.

Konsekuensinya dua hal:

1. Baris 362 dan 433 **wajib diperbaiki dalam satu perubahan**. Memperbaiki
   salah satunya saja akan membuat Neraca benar-benar tidak seimbang.
2. Tes wajib membuktikan Neraca tetap seimbang **sesudah** perbaikan, dengan
   data yang memuat invoice disetujui maupun belum. Itu penjaga terpenting di
   pekerjaan ini.

## 4. Gerbang `finance:snapshot`

Repo ini punya `php artisan finance:snapshot` yang dibuat persis untuk keadaan
seperti ini: merekam angka seluruh laporan keuangan sebelum dan sesudah suatu
perubahan.

Catatan proyek mencatat gerbang ini pernah dilewati pada pekerjaan Rincian
Profit, dan hal itu ditandai sebagai titik curiga pertama bila angka laporan
terasa aneh. Kali ini gerbangnya dijalankan.

Perintah itu menulis enam laporan keuangan ke JSON justru agar bisa
dibandingkan sebelum/sesudah. Prosedurnya:

1. `php artisan finance:snapshot` sebelum perubahan apa pun; salin berkas JSON
   hasilnya ke nama lain agar tidak tertimpa
2. Kerjakan perubahan
3. `php artisan finance:snapshot` lagi
4. `diff` kedua berkas JSON itu

**Yang harus terlihat:** selisih hanya pada angka yang memang dituju, dan
**nol di tempat lain**. Bila ada laporan lain yang ikut bergeser, berarti ada
pemanggilan ketujuh yang belum ditemukan — dan itu harus diselidiki sebelum
pekerjaan dilanjutkan, bukan diabaikan.

## 5. Yang TIDAK berubah

- **Rekap Keuangan, Arus Kas, Jurnal, Buku Besar.** Keempatnya membaca
  `fin_transactions` — uang yang benar-benar bergerak — dan tidak pernah
  menyentuh `invoices`.
- **`FinanceController`.** Sudah menyaring dengan benar; tidak disentuh.
- **Tidak ada migrasi.** Tidak ada satu baris data pun ditulis ulang. Yang
  berubah hanya cara membaca.
- **Definisi "disetujui" tidak diubah.** `scopeApproved()` tetap
  `whereNotNull('approved_at')` apa adanya.
- **Invoice yang di-soft-delete** tetap terkecualikan seperti sebelumnya —
  itu perilaku bawaan `SoftDeletes`, bukan sesuatu yang pekerjaan ini atur.

## 6. Kolom yang dipakai Fiskal — dicatat, tidak diperbaiki

`FiscalController` memakai `sum('total')`, sedangkan lima tempat lain memakai
`sum('total_idr')`. Untuk invoice berdenominasi IDR keduanya identik; untuk
invoice mata uang asing tidak.

Pekerjaan ini **tidak** mengubahnya. Itu pertanyaan terpisah tentang mata uang,
bukan tentang persetujuan invoice, dan mencampurnya akan membuat selisih
snapshot §4 mustahil ditafsirkan. Dicatat di sini supaya tidak hilang.

## 7. Dampak yang akan terlihat pengguna

Angka di lima laporan akan **turun** begitu perubahan ini rilis. Untuk 2026:

- Laba Rugi Total Penjualan: Rp 639.870.246,02 → Rp 270.075.810,00
- Piutang di Neraca, Saldo Akun, dan Dashboard: turun sebesar nilai draft
- Laba ditahan di Neraca: turun sebesar nilai yang sama
- Peredaran bruto di Fiskal: turun

Ini koreksi, bukan kemunduran — angka lama memang tidak pernah benar. Tetapi
penurunannya besar dan mendadak, jadi pemilik proyek perlu tahu sebelum rilis
agar tidak terbaca sebagai kehilangan penjualan.

## 8. Tes

1. Laba Rugi mengabaikan invoice yang belum disetujui, dan tetap menghitung
   yang sudah.
2. Laba Rugi per lini bisnis ikut menyaring — bukan hanya angka totalnya.
3. **Neraca tetap seimbang** dengan data campuran (disetujui + belum), setelah
   kedua sisinya dikoreksi.
4. Piutang di Neraca, Saldo Akun, dan Dashboard menghasilkan angka yang sama
   satu sama lain untuk data yang sama.
5. Fiskal mengabaikan invoice yang belum disetujui.
6. Invoice yang sudah disetujui lalu di-soft-delete tetap tidak terhitung di
   keenam tempat.
7. Invoice yang belum disetujui lalu disetujui kemudian mulai terhitung —
   membuktikan penyaringnya membaca keadaan terkini, bukan hasil cache.

## 9. Di luar cakupan

- Mengubah kolom yang dipakai Fiskal dari `total` ke `total_idr` (§6).
- Mengubah definisi `scopeApproved()`.
- Menambahkan tampilan terpisah untuk nilai proforma yang belum disetujui.
  Kalau kelak sales ingin melihat "potensi penjualan", itu laporan tersendiri
  dengan judul yang jujur — bukan angka yang menyamar sebagai penjualan.
- Menyentuh laporan berbasis `fin_transactions`.
