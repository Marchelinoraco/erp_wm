# Changelog

Catatan perubahan ERP Welcome Manado.

Bagian **Sedang Dikerjakan** menjawab "sudah sampai mana" untuk pekerjaan yang berjalan lintas beberapa rilis. Bagian di bawahnya adalah riwayat yang sudah selesai, terbaru di atas.

## Cara mengisi

Setiap perubahan yang terasa oleh pengguna dicatat di sini — bukan setiap commit. Perbaikan typo, penataan kode, dan pekerjaan internal tanpa efek yang terlihat tidak perlu masuk.

Kategori yang dipakai:

| Kategori | Untuk |
|---|---|
| **Ditambahkan** | kemampuan baru yang sebelumnya tidak ada |
| **Diubah** | perilaku yang sudah ada, kini bekerja berbeda |
| **Diperbaiki** | sesuatu yang rusak, kini benar |
| **Dihapus** | kemampuan yang ditiadakan |
| **Keamanan** | perbaikan yang menyangkut akses atau data sensitif |

Tulis dari sudut pandang orang yang memakai sistem, bukan dari sudut pandang kode. "Sales bisa mengatur jatuh tempo invoice" lebih berguna daripada "tambah kolom due_date".

Untuk pekerjaan bertahap yang belum selesai, tambahkan entri di **Sedang Dikerjakan** dengan status per fase, lalu pindahkan ke bagian rilis begitu seluruhnya tuntas.

---

## Sedang Dikerjakan

### Pemisahan Aturan Invoice per Jenis Penjualan

Setiap jenis penjualan (Tour, Hotel, Jasa Guide, Transport, MICE, Document, Ticketing) mendapat aturan hitung tagihannya sendiri, sehingga mengubah cara hitung satu jenis tidak mengganggu jenis lain. Sekaligus memperbaiki kolom "Harga / pax" yang dipaksakan pada jenis yang sebenarnya ditagih per hari atau per unit.

- Desain: [`docs/design_pemisahan_invoice_per_jenis_penjualan.md`](docs/design_pemisahan_invoice_per_jenis_penjualan.md)
- Rencana Fase 0: [`docs/plan_karakterisasi_invoice_fase_0.md`](docs/plan_karakterisasi_invoice_fase_0.md)

| Fase | Isi | Status | Terasa oleh pengguna? |
|:---:|---|---|:---:|
| — | Dokumentasi alur invoice yang berjalan | ✅ Selesai (24 Jul) | — |
| — | Desain + protokol keamanan data | ✅ Selesai (24 Jul) | — |
| 0 | Characterization test — kunci perilaku sekarang | ⬜ Belum mulai | Tidak |
| 1 | Kontrak + registry + 7 aturan, hasil hitung identik | ⬜ Belum mulai | Tidak |
| 2 | Migrasi kolom + backfill data lama | ⬜ Belum mulai | Tidak |
| 3 | Pengali bisa diedit di invoice + label per jenis | ⬜ Belum mulai | **Ya** |
| 4 | Label jenis penjualan di PDF invoice | ⬜ Belum mulai | **Ya** |
| 5 | Definisi jenis penjualan dipecah per berkas di frontend | ⬜ Belum mulai | Tidak |

**Syarat sebelum Fase 2 menyentuh production** (dari §7.8 dokumen desain): backup manual terverifikasi, migrasi diuji di salinan data production dengan nol selisih total invoice, dan test jaminan "invoice disetujui tidak pernah dihitung ulang" lolos.

---

## 24 Juli 2026

### Ditambahkan
- Dokumentasi lengkap alur pembuatan invoice, dipecah per tahap dengan 93 kondisi bernomor rujukan dan penanda apakah tiap kondisi ditegakkan di server atau hanya di UI ([`docs/logika-pembuatan-invoice/`](docs/logika-pembuatan-invoice/README.md))

### Catatan
Ditemukan beberapa perilaku yang perlu diketahui tim, tercatat di [`docs/logika-pembuatan-invoice/10-temuan.md`](docs/logika-pembuatan-invoice/10-temuan.md) — di antaranya syarat status Confirmed yang hanya dijaga di tampilan, route pembayaran yang tidak memeriksa status invoice, dan kolom "Harga / pax" yang dipaksakan pada jenis penjualan yang ditagih per job. Belum ada yang diperbaiki; masing-masing menunggu keputusan.

---

## 21 Juli 2026

### Ditambahkan
- Email benar-benar terkirim ke customer lewat Brevo SMTP, berjalan di latar belakang sehingga tidak menahan halaman
- Digest follow-up harian otomatis ke Sales
- Halaman Kontak Brevo beserta status "sudah terkirim" per email

---

## 18 Juli 2026

### Ditambahkan
- Kepemilikan tour per akun Sales — tiap Sales hanya melihat tour miliknya, admin melihat semua
- Reminder follow-up otomatis H+1, berantai mengikuti perubahan status tour

### Diubah
- Data finansial memakai soft delete, sehingga penghapusan tidak lagi permanen
- Role Operation dan Travel Agent kini diterima pada form Kelola Akun

---

## 17 Juli 2026

### Diubah
- Halaman Kelola Akun dirombak tampilannya

### Diperbaiki
- Pesan kesalahan kini muncul pada form catat pembayaran tour
- Role Operation dan Travel Agent tidak lagi hilang dari daftar

---

## 16 Juli 2026

### Ditambahkan
- Kurs dapat berbeda per pembayaran — DP dan pelunasan boleh memakai kurs masing-masing
- Biaya tambahan tampil sebagai baris "Additional" pada invoice yang sama

### Diubah
- Nomor invoice mengikuti tipe penjualan dan urutan waktu invoice dibuat, bukan kode tour
- Daftar AR di Keuangan urut menurut nomor invoice
- Rekening baru otomatis menjadi Akun Kas; penghapusan rekening dibatasi admin dan akuntan

### Diperbaiki
- PDF invoice tidak lagi terpotong ke halaman kedua yang nyaris kosong
- Bug pada proses persetujuan invoice bermata uang asing

---

## 11–13 Juli 2026

### Ditambahkan
- Sales dapat mengajukan biaya tambahan saat tour berjalan; akuntan memverifikasi lalu tercatat sebagai Bill
- PDF Rincian Profit internal, dan item bersupplier otomatis tercatat sebagai Bill draft
- Akuntan memilih akun kas (BCA/BNI/dst) saat mencatat pembayaran Bill dan Invoice
- Customer tipe Buyer (travel agent) — Sales mengisi Nama Tamu, tim lapangan melihat nama tamu bukan nama buyer
- Autosave massal pada Rincian Profit untuk input item dalam jumlah banyak
- Tanggal mulai dan selesai untuk semua tipe produk, wajib pada hotel/transport/guide
- Deskripsi itinerary mendukung bold, italic, dan list; ikut tampil di PDF quotation, MyJobs, dan manifest

### Diubah
- Nomor keuangan gapless (`INV-<tahun>-NNNN`) ditetapkan saat invoice disetujui
- Layout Edit Tour dan daftar Tour dilebarkan agar muat tanpa scroll horizontal

---

## 3–7 Juli 2026

### Ditambahkan
- Itinerary versi Indonesia untuk tim lapangan
- Manifest publik diselaraskan dengan MyJobs, dengan layout responsif
- Salin/Tempel itinerary antar tour, dan Salin/Tempel rincian profit
- Item invoice hotel/transport/guide bertanggal, muncul di jadwal MyJobs
- Template quotation sepenuhnya berbahasa Inggris untuk customer

### Diubah
- Profit tipe Tour dihitung sebagai tagihan (harga/pax × pax) dikurangi cost, bukan penjumlahan margin per item
- Profit tour ikut tercatat di Ringkasan Biaya dan Keuangan
- Halaman daftar Tour responsif di ponsel, dengan filter pencarian/tanggal/sales

### Diperbaiki
- Tanggal Mulai/Selesai tour tidak lagi tampak kosong saat tour dibuka ulang

---

## 1 Juli 2026

### Ditambahkan
- Invoice proforma mendukung banyak mata uang
- Watermark DP/PAID pada PDF invoice, dan Sales dapat mencatat pembayaran langsung
- Tipe penjualan Hotel
- Laporan kerja otomatis

### Diubah
- Kode tour dibentuk berdasarkan tipe penjualan
- Label pada PDF invoice sepenuhnya berbahasa Inggris

### Diperbaiki
- Nomor invoice duplikat
- Kode tour duplikat ketika ada record yang telah dihapus
- Daftar pembayaran tidak muncul pada invoice di halaman tour

---

## 26–29 Juni 2026

### Ditambahkan
- Modul Keuangan lengkap: Laba/Rugi, Neraca, Aset Tetap, Hutang, dan Koreksi Fiskal
- Template MICE yang dapat dipakai ulang
- Alur invoice dua tahap: patokan → rincian → setujui → masuk Keuangan
- Ekspor Word (.doc) untuk quotation
- Sales dapat mengelola Supplier dan mengatur jatuh tempo invoice sebelum disetujui

### Diubah
- PDF invoice dirancang ulang mengikuti format proforma yang diterima customer

### Diperbaiki
- PDF quotation menghormati field teks yang sengaja dikosongkan
- Ekspor Word yang menghasilkan berkas kosong

---

## 15–21 Juni 2026

### Ditambahkan
- Keuangan Tahap 1: buku kas dan grafik Arus Kas
- Keuangan Tahap 2: Jurnal, Buku Besar, Laba Akuntansi, dan Rekap
- Neraca per tahun serta Saldo per Akun (Kas, Bank BCA, dst)
- Unduh PDF untuk seluruh laporan keuangan
- Kalkulator harga per-pax pada quotation, dengan mode Per Kendaraan dan Per Jumlah Pax
- PDF invoice itemized lengkap dengan terbilang dan rekening pembayaran
- Rekening pembayaran dinamis yang dikelola akuntan
- Periode harga produk

### Diperbaiki
- Sesi yang kedaluwarsa pada permintaan Inertia ditangani dengan rapi

---

## 7–14 Juni 2026

Fondasi sistem.

### Ditambahkan
- Modul tour, customer, produk, supplier, dan quotation
- Quotation profesional dengan matriks harga dan itinerary, disertai pembuatan invoice draft otomatis saat tour berstatus Confirmed
- Jenis inquiry: Tour, Rental, Guide, Visa/Paspor, dan Ticketing
- Itinerary jam-ke-jam, riwayat customer, activity log, dan Channel Manager
- Role Operasional beserta alur Booking supplier — booking yang dikonfirmasi otomatis menjadi hutang usaha
- Download template produk dan ekspor referensi supplier
- Halaman Edit Tour disusun ulang menjadi panel-panel komponen
