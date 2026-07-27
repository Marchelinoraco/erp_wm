# Produk

> **Status:** ✅ Berjalan · **Peran:** admin, sales · **Sejak:** Jun 2026
> **Terkait:** —

## 1. Ringkasan

Data master untuk seluruh item yang bisa dijual di dalam tour (hotel, transport, guide, restaurant, attraction, venue, equipment, dll) dalam satu tabel `products`, dengan harga dasar (`cost`/`sell`) yang dipakai lewat snapshot saat ditambahkan ke tour — bukan referensi live. Mendukung harga per periode (`product_prices`, murni referensi manual, tidak otomatis dipilih berdasarkan tanggal tour) dan varian produk (`group_label`+`grade`). Dipakai oleh admin & sales.

## 2. Cara kerja (as-built)

> _Dilengkapi pada batch domain — lihat rencana._

## 3. Keterkaitan

> _Dilengkapi pada batch domain — lihat rencana._

## 4. Batasan & jebakan ⚠️

> _Dilengkapi pada batch domain — lihat rencana._

## 5. Status & yang belum

Berjalan penuh di production sejak fondasi MVP. Harga periode masih perlu dicek & di-override manual oleh sales (tidak ada pencocokan tanggal otomatis); seasonal pricing terjadwal penuh belum dibangun (lihat `ikhtisar-proyek.md` §9).

## 6. Dokumen terkait

> _Dilengkapi pada batch domain — lihat rencana._
