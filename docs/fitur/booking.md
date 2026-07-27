# Booking

> **Status:** ✅ Berjalan · **Peran:** admin, sales, operation · **Sejak:** Jun 2026
> **Terkait:** —

## 1. Ringkasan

Jembatan operasional antara Penjualan (tour yang sudah `confirmed`) dan Keuangan (utang/AP ke supplier) — satu baris `tour_bookings` per tugas "eksekusi pemesanan ke satu supplier", dibuat otomatis saat tour berubah status jadi confirmed (dikelompokkan dari item tour per supplier). Tim operation mengeksekusi tiap baris (isi harga deal, nomor konfirmasi), yang otomatis membuat/menyinkronkan Bill terkait. Dipakai oleh admin, sales, dan operation — untuk operation ini satu-satunya halaman yang bisa diakses.

## 2. Cara kerja (as-built)

> _Dilengkapi pada batch domain — lihat rencana._

## 3. Keterkaitan

> _Dilengkapi pada batch domain — lihat rencana._

## 4. Batasan & jebakan ⚠️

> _Dilengkapi pada batch domain — lihat rencana._

## 5. Status & yang belum

Berjalan penuh di production sejak fondasi MVP. Pembatasan tampilan booking per akun sales (mengikuti kepemilikan tour) sudah ada di kode tapi belum jalan di production — lihat catatan §5 di [penjualan-tour.md](penjualan-tour.md) dan [rencana/tour-ownership.md](../rencana/tour-ownership.md).

## 6. Dokumen terkait

> _Dilengkapi pada batch domain — lihat rencana._
