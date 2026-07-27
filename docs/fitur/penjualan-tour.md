# Penjualan Tour

> **Status:** ✅ Berjalan · **Peran:** admin, sales · **Sejak:** Jun 2026
> **Terkait:** [rencana/tour-ownership.md](../rencana/tour-ownership.md)

## 1. Ringkasan

Modul inti ERP — satu mesin (tabel `tours` + `TourController`) yang melayani tujuh jenis penjualan sekaligus: Tour, Rental, Jasa Guide, Visa/Paspor, Ticketing, MICE, dan Hotel, lewat satu halaman kerja `Tours/Edit.vue` berisi panel bertumpuk (item produk, itinerary, invoice, biaya tambahan, penugasan lapangan, riwayat). Tiap tour mengalir lewat pipeline status `inquiry → … → confirmed/cancelled`, dan harga produk di-snapshot ke `tour_items` saat ditambahkan sehingga perubahan harga produk di kemudian hari tidak mengubah tour lama. Dipakai sehari-hari oleh admin & sales sebagai titik masuk utama seluruh alur penjualan.

## 2. Cara kerja (as-built)

> _Dilengkapi pada batch domain — lihat rencana._

## 3. Keterkaitan

> _Dilengkapi pada batch domain — lihat rencana._

## 4. Batasan & jebakan ⚠️

> _Dilengkapi pada batch domain — lihat rencana._

## 5. Status & yang belum

Berjalan penuh di production sejak fondasi MVP (M1–M7 selesai seluruhnya). Kepemilikan tour per akun sales (agar tiap sales hanya melihat tour miliknya, admin tetap melihat semua) sudah selesai & terverifikasi di kode tapi migrasinya **belum dijalankan di production** — sampai saat itu, semua sales masih melihat & bisa mengedit semua tour. Lihat [rencana/tour-ownership.md](../rencana/tour-ownership.md).

## 6. Dokumen terkait

> _Dilengkapi pada batch domain — lihat rencana._
