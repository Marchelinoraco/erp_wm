# 09 — Matriks Kondisi

[← 08 Perbedaan per Tipe](08-perbedaan-per-tipe.md) · [Peta](README.md) · Berikutnya: [10 — Temuan](10-temuan.md)

---

Rujukan cepat seluruh kondisi yang disebut di dokumen ini. Kolom **Kekuatan** menunjukkan di mana kondisi benar-benar ditegakkan:

- **Server** — ditolak backend, tidak bisa dilewati
- **UI** — hanya `v-if` atau `:disabled`; permintaan langsung ke API tetap lolos
- **Turunan** — bukan penjaga, melainkan nilai yang dihitung

## 9.1 Prasyarat & pembuatan

| # | Kondisi | Kekuatan | Akibat bila gagal |
|---|---|---|---|
| K-01 | `tour.status === 'confirmed'` | UI | panel tidak dirender |
| K-02 | Tour belum punya invoice | Server | "satu tour hanya boleh satu invoice" |
| K-03 | Belum ada nomor berprefiks sama | Turunan | mulai `0001` |
| K-04 | Sudah ada nomor | Turunan | tertinggi + 1 |
| K-05 | Dua permintaan bersamaan | Server | `lockForUpdate()` mencegah kembar |
| K-06 | Ada invoice ter-soft-delete | Turunan | nomor tidak dipakai ulang |

## 9.2 Tahap 1 · Proforma

| # | Kondisi | Kekuatan | Akibat |
|---|---|---|---|
| K-10 | Belum disetujui | Server | "tidak bisa diubah lagi" |
| K-11 | Tour punya `pax` | Turunan | pakai `tour.pax` |
| K-12 | Tour tanpa `pax` | Turunan | pakai `invoice.pax` |
| K-13 | Keduanya kosong | Turunan | `1` |
| K-14 | Hasil < 1 | Turunan | dipaksa 1 |
| K-15 | Mata uang IDR | Server | `exchange_rate = 1`, `total_idr` terisi |
| K-16 | Mata uang non-IDR | Server | `total_idr` tetap kosong |
| K-17 | `bank_account_ids` terisi | Server | hanya rekening itu di PDF |
| K-18 | `bank_account_ids` kosong | Server | disimpan `null` = semua rekening aktif |

## 9.3 Tahap 2 · Patokan

| # | Kondisi | Kekuatan | Akibat bila gagal |
|---|---|---|---|
| K-20 | Belum disetujui | Server | "tidak bisa diubah lagi" |
| K-21 | `total > 0` | Server | "Isi harga proforma terlebih dahulu" |
| K-22 | `proformaTotal > 0` | UI | tombol disabled, tanpa pesan |
| K-23 | `stage === 'baseline'` | Turunan | tombol "Kunci Patokan" |
| K-24 | `stage === 'detail'` | Turunan | tombol "Samakan Patokan" + "Setujui" |
| K-25 | Selisih patokan < 0,01 | UI | tombol Setujui aktif |
| K-26 | Selisih patokan ≥ 0,01 | UI | tombol Setujui disabled |

## 9.4 Rincian Profit

| # | Kondisi | Kekuatan | Akibat |
|---|---|---|---|
| K-30 | Belum disetujui | Server | seluruh operasi item ditolak |
| K-31 | Produk hotel/transport/guide | Server | tanggal menjadi wajib |
| K-32 | Tipe produk lain | Server | tanggal opsional |
| K-33 | Produk wajib-tanggal dipilih | UI | `start_date` diprefill dari tour |
| K-34 | Produk opsional dipilih | UI | tanggal dibiarkan kosong |
| K-35 | `end_date` < `start_date` | UI | disamakan otomatis |
| K-36 | Produk `hotel` | UI | `nights` dihitung dari rentang, min 1 |
| K-37 | Baris tempelan kosong | UI | dilewati |
| K-38 | Baris header/ringkasan | UI | dilewati |
| K-39 | Kurang dari 2 sel | UI | dilewati |
| K-40 | Sel ke-2 bukan angka, ≥ 6 sel | UI | kolom Tipe dianggap ada |
| K-41 | Label tipe tak dikenal | UI | `product_type = null` |
| K-42 | Autosave sedang berjalan | UI | flush baru ditolak |
| K-43 | Tidak ada baris kotor | UI | jalankan `pendingAction` |
| K-44 | Request autosave gagal | UI | baris dikembalikan, data tidak hilang |
| K-45 | Ada ketikan selama request | UI | flush ulang 300 ms |
| K-46 | Selesai & bersih | UI | jalankan `pendingAction` |
| K-47 | Data server untuk baris yang diedit | UI | diabaikan |
| K-48 | Tutup tab dengan perubahan menggantung | UI | peringatan browser |
| K-49 | Navigasi Inertia GET | UI | dialog konfirmasi |
| K-50 | Item dihapus | UI | dibuang dari `dirtyIds` dulu |
| K-51 | `id` bukan milik invoice ini | Server | dilewati diam-diam |
| K-52 | Tipe `tour`, non-IDR, kurs kosong | Turunan | profit `null` |
| K-53 | Tipe non-`tour` | Turunan | profit selalu terhitung |

## 9.5 Tahap 3 · Persetujuan

| # | Kondisi | Kekuatan | Akibat bila gagal |
|---|---|---|---|
| K-60 | Belum disetujui | Server | "tidak bisa diubah lagi" |
| K-61 | `baseline_total > 0` | Server | "Kunci patokan terlebih dahulu" |
| K-62 | Kurs terisi bila non-IDR | Server | galat validasi |
| K-63 | Kurs > 0 | Server | galat validasi |
| K-64 | `baselineMatched` true | UI | tombol disabled |
| K-65 | `proformaTotal > 0` | UI | tombol disabled |
| K-66 | Belum ada nomor keuangan | Turunan | mulai `0001` |
| K-67 | Sudah ada | Turunan | tertinggi + 1 |
| K-68 | Dua persetujuan bersamaan | Server | `lockForUpdate()` |
| K-69 | Ada yang ter-soft-delete | Turunan | nomor tidak dipakai ulang |
| K-70 | Item punya produk bersupplier | Server | Bill draft dibuat |
| K-71 | Item tanpa produk | Server | dilewati |
| K-72 | Produk tanpa supplier | Server | dilewati |
| K-73 | Bill sudah ada | Server | tidak digandakan |
| K-74 | Mata uang IDR | Server | riwayat tanpa ekuivalen IDR |
| K-75 | Mata uang non-IDR | Server | riwayat dengan `(≈ IDR ...)` |

## 9.6 Penguncian

| # | Kondisi | Kekuatan | Akibat |
|---|---|---|---|
| K-80 | Sudah disetujui | Server | tidak bisa dihapus |
| K-81 | Belum disetujui | Server | soft delete |
| K-82 | Setelah dihapus | Turunan | boleh buat invoice baru |
| K-83 | Nomor invoice lama | Turunan | tetap terpakai selamanya |

## 9.7 Pembayaran

| # | Kondisi | Kekuatan | Akibat |
|---|---|---|---|
| K-90 | Invoice sudah disetujui | **UI saja** | blok pembayaran muncul |
| K-91 | Invoice non-IDR | Server | kurs wajib |
| K-92 | Invoice IDR | Server | kurs opsional |
| K-93 | `amount` ≤ 0 | Server | ditolak, minimal 0,01 |
| K-94 | Ada pembayaran sebelumnya | UI | kurs awal = kurs terakhir |
| K-95 | Belum ada pembayaran | UI | kurs awal = kurs invoice |
| K-96 | Keduanya kosong | UI | kosong |
| K-97 | Invoice IDR | UI | kurs selalu kosong |
| K-98 | `paid >= total` | Server | status `paid` |
| K-99 | `paid < total` | Server | status `partial` |
| K-100 | `paid <= 0` setelah hapus | Server | status `sent` |
| K-101 | `paid >= total` setelah hapus | Server | status `paid` |
| K-102 | selain itu setelah hapus | Server | status `partial` |
| K-103 | Kelebihan bayar | UI | sisa tagihan ditampilkan `0` |

## 9.8 Tipe penjualan

| # | Kondisi | Kekuatan | Akibat |
|---|---|---|---|
| K-110 | `tour` + outbound | Turunan | kode `12` |
| K-111 | `tour` + arah lain | Turunan | kode `11` |
| K-112 | Tipe terdaftar | Turunan | kode sesuai tabel |
| K-113 | Tipe tak dikenal | Turunan | jatuh ke `11` |
| K-114 | `tour` sudah disetujui | Turunan | pakai `total_idr` |
| K-115 | `tour`, draft, IDR | Turunan | pakai total proforma |
| K-116 | `tour`, draft, non-IDR, kurs ada | Turunan | total × kurs |
| K-117 | `tour`, draft, non-IDR, kurs kosong | Turunan | `null` |
| K-118 | Tipe non-`tour` | Turunan | selalu terhitung |
| K-119 | `tour` + invoice disetujui | Turunan | Ringkasan Biaya dari invoice |
| K-120 | Selain itu | Turunan | Ringkasan Biaya dari `tour_items` |
| K-121 | `pax = 1` | Turunan | `unit_price` = nilai tagihan |
| K-122 | `pax > 1` pada tipe non-pax | Turunan | total berlipat |

## 9.9 Ringkasan kekuatan penegakan

| Kekuatan | Jumlah | Catatan |
|---|:---:|---|
| Server | 34 | tidak bisa dilewati |
| UI saja | 30 | permintaan langsung ke API tetap lolos |
| Turunan | 29 | nilai hitungan, bukan penjaga |
| **Total** | **93** | K-01 s/d K-122 |

Kondisi bertanda **UI saja** yang paling berdampak pada aturan bisnis: **K-01** (status confirmed) dan **K-90** (pembayaran hanya setelah disetujui). Keduanya dibahas di [10-temuan.md](10-temuan.md).
