# Panduan: Rincian Profit

Cara mengisi dan mengoreksi biaya modal sebuah tour — termasuk setelah invoice disetujui.

---

## Untuk siapa & di mana

| Peran | Jalur | Tambah item lewat |
|---|---|---|
| Sales | Tour → panel **Invoice** (hanya muncul kalau status tour sudah **confirmed**) | tombol **+ Tambah Produk** (pilih dari katalog) |
| Akuntan / Admin | **Keuangan** → pilih tour | tombol **+ Tambah Item** (ketik bebas) |

Panelnya berjudul **Rincian Profit (internal · IDR)**. Kata *internal* itu penting: angka di panel ini tidak pernah muncul di invoice, kuitansi, atau dokumen apa pun yang diterima pelanggan. Isinya selalu dalam Rupiah, walau tagihan pelanggannya memakai mata uang lain.

Sales dan akuntan melihat dan mengedit **data yang sama** — perubahan dari satu sisi langsung terlihat di sisi lain. Bedanya hanya cara menambah item: sales memilih dari katalog produk, akuntan mengetik sendiri.

## Apa gunanya

Rincian Profit mencatat **biaya modal** tiap komponen tour: berapa yang kita bayar ke hotel, ke transport, ke guide. Dari situ sistem menghitung margin tour — tagihan pelanggan dikurangi total biaya modal.

Kalau rinciannya tidak diisi, sistem menganggap biaya modalnya nol, sehingga margin tour tercatat seolah seluruh tagihan pelanggan adalah keuntungan. Sejak 6 Agustus 2026 rincian ini **bisa diperbaiki kapan saja**, termasuk setelah invoice disetujui.

## Cara perubahan tersimpan

Tidak ada tombol Simpan. Perubahan tersimpan sendiri sekitar **1,5 detik** setelah kamu berhenti mengetik. Perhatikan penanda kecil di bagian bawah panel:

| Penanda | Artinya |
|---|---|
| **● Ada perubahan…** | Ketikanmu belum terkirim, tunggu sebentar |
| **⏳ Menyimpan…** | Sedang dikirim ke server |
| **✓ Tersimpan** | Aman, sudah masuk |

Tunggu sampai muncul **✓ Tersimpan** sebelum menutup atau berpindah halaman. Kalau ada yang gagal, muncul **baris merah di atas tabel** berisi keterangannya — perubahanmu tidak hilang, ia akan dicoba kirim lagi.

---

## Skenario

### 1. Rincian Profit terlanjur kosong padahal invoice sudah disetujui

**Situasi.** Invoice sudah disetujui dan masuk Keuangan, tapi kolom biaya belum sempat diisi. Margin tour ini sekarang tercatat salah — seluruh tagihan pelanggan dihitung sebagai keuntungan, padahal biaya ke supplier belum dipotong.

**Langkah:**

1. Buka tour tersebut — sales lewat halaman Tour, akuntan lewat menu Keuangan.
2. Cari panel **Rincian Profit (internal · IDR)**.
3. Tambahkan item satu per satu: sales pakai **+ Tambah Produk**, akuntan pakai **+ Tambah Item**.
4. Isi **Cost/unit** dengan biaya modal per unit, lalu **Qty** dan **Mlm** (malam) sesuai kenyataan.
5. Tunggu penanda **✓ Tersimpan**.

**Hasil.** Margin tour langsung ikut terkoreksi. Karena invoice sudah disetujui, setiap item yang kamu tambahkan tercatat di riwayat tour lengkap dengan namamu.

### 2. Biaya aktual dari supplier berbeda dari estimasi

**Situasi.** Waktu invoice dibuat, biaya hotel diperkirakan Rp 800.000 per malam. Tagihan asli dari hotel ternyata Rp 950.000. Margin yang tercatat sekarang terlalu optimistis.

**Langkah:**

1. Buka panel Rincian Profit pada tour tersebut.
2. Klik kolom **Cost/unit** di baris yang perlu dikoreksi, ganti angkanya.
3. Tunggu penanda **✓ Tersimpan**.

**Hasil.** Margin tour menyesuaikan dengan biaya sebenarnya.

**Penting.** Item yang sudah dibuatkan tagihan ke supplier (Bill) **tetap boleh diubah** di sini. Rincian Profit dan Bill adalah dua catatan terpisah: yang satu untuk menghitung margin, yang satu untuk membayar supplier. Mengubah angka di sini **tidak** mengubah nominal Bill. Kalau Bill-nya juga perlu diperbaiki, kerjakan terpisah di bagian **AP — Bill ke Supplier** pada halaman Keuangan.

### 3. Ada item keliru yang ingin dihapus

**Situasi.** Satu baris salah masuk — misalnya produk yang batal dipakai, atau item yang terlanjur dobel.

**Langkah:**

1. Buka panel Rincian Profit.
2. Klik tanda **✕** di ujung kanan baris tersebut.
3. Muncul konfirmasi **"Hapus item ini?"** — klik **Hapus**.

**Hasil.** Item terhapus dan margin ikut menyesuaikan.

**Kalau item itu sudah punya Bill,** penghapusan ditolak dan muncul baris merah:

> Item ini sudah dibuatkan Bill — hapus Bill-nya dulu bila memang keliru.

Ini disengaja. Bill bisa jadi sudah dibayar ke supplier, dan menghapus itemnya akan membuat Bill itu kehilangan jejak asal-usulnya. Urutan yang benar:

1. Buka bagian **AP — Bill ke Supplier** di halaman Keuangan.
2. Hapus Bill yang menunjuk ke item tersebut.
3. Baru kembali ke Rincian Profit dan hapus itemnya.

Kalau Bill-nya sudah terlanjur dibayar, jangan dihapus — bicarakan dulu dengan akuntan, karena itu menyangkut catatan kas yang sudah keluar.

---

## Aturan & batasan

| Tindakan | Boleh? | Yang terjadi di layar |
|---|---|---|
| Menambah item setelah invoice disetujui | Ya | Tersimpan, satu baris masuk riwayat tour |
| Mengubah angka item setelah disetujui | Ya | Tersimpan, riwayat mencatat nilai lama → nilai baru |
| Mengubah item yang sudah punya Bill | Ya | Bill tidak ikut berubah — perbaiki Bill terpisah bila perlu |
| Menghapus item yang sudah punya Bill | **Tidak** | Baris merah: *Item ini sudah dibuatkan Bill — hapus Bill-nya dulu bila memang keliru.* |
| Menghapus item tanpa Bill | Ya | Muncul konfirmasi *Hapus item ini?* dulu |
| Mengedit saat tour belum **confirmed** (sisi sales) | Panelnya belum muncul | Selesaikan dulu konfirmasi tour |

## Jejak tercatat

Setiap penambahan, perubahan, dan penghapusan **setelah invoice disetujui** dicatat satu baris di **riwayat tour**, memuat nama pengguna yang melakukannya dan ringkasan **nilai lama → nilai baru** untuk tiap kolom yang berubah.

Perubahan **sebelum** invoice disetujui **tidak** dicatat — itu area kerja normal sales saat menyusun penawaran, dan mencatat tiap ketikan hanya akan membanjiri riwayat tour tanpa manfaat.

Jadi kalau kamu memperbaiki data tour lama, jejaknya ada dan bisa ditelusuri. Itu bukan alasan untuk ragu memperbaiki — justru sebaliknya, riwayat itu yang membuat koreksi jadi aman dilakukan.

## Salah kaprah

**"Mengedit Rincian Profit akan mengubah tagihan pelanggan."**
Tidak. Tagihan pelanggan dihitung dari harga jual dikali jumlah peserta, dan sama sekali tidak dipengaruhi Rincian Profit. Mengubah biaya modal di sini tidak akan mengubah angka yang sudah dikirim ke pelanggan, tidak membuat invoice terbit ulang, dan tidak mengubah sisa tagihan.

**"Invoice sudah disetujui, berarti semuanya terkunci."**
Yang terkunci adalah kurs invoice dan tagihan ke pelanggan — dan itu memang seharusnya terkunci. Rincian Profit tidak termasuk, karena ia catatan internal yang justru sering baru bisa dipastikan setelah tour berjalan dan tagihan supplier masuk.

**"Kalau saya salah isi, tidak ada jalan kembali."**
Ada. Selama item belum dibuatkan Bill, ia bisa diubah atau dihapus kapan saja. Yang tidak bisa dibatalkan sendiri hanyalah penghapusan item ber-Bill — dan itu pun cuma soal urutan: hapus Bill dulu.

---

## Ingin tahu detail teknisnya?

- [`design-system/01-penjualan-tour.md`](../design-system/01-penjualan-tour.md) — cara kerja modul penjualan tour secara menyeluruh
- [`logika-pembuatan-invoice/04-rincian-profit.md`](../logika-pembuatan-invoice/04-rincian-profit.md) — rumus profit dan aturan per kondisi
