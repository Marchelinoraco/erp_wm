# Desain: Manual Book Pengguna & Laporan Kerja per Fitur

> Status (6 Agu 2026): disetujui, siap direncanakan.

---

## 1. Latar belakang

Laporan kerja (`LAPORAN-KERJA.md`) untuk 6 Agustus 2026 memuat 7 seksi, tapi isinya hanya menyalin judul commit mentah — termasuk istilah Inggris yang tidak diterjemahkan seperti *"filter router guard to GET navigations only in RincianProfitEditor"*. Atasan tidak bisa memahami apa yang dibangun, apalagi staf yang harus memakai fiturnya.

Dua kekurangan terpisah yang menyebabkannya:

1. **Tidak ada dokumentasi cara pakai sama sekali.** `docs/` punya `design-system/` (cara kerja fitur, untuk developer), `desain/`, `rencana/`, dan `referensi/` — tak satu pun menjawab pertanyaan staf: *"bagaimana saya memakai fitur ini?"* Fitur seperti Rincian Profit Tetap Terbuka punya aturan yang tidak terduga (item ber-Bill tak bisa dihapus, tiap edit pasca-approve tercatat di riwayat) yang tidak terbaca dari layar.

2. **Agent `laporan-kerja` mengelompokkan per kategori, bukan per fitur.** Aturannya di [`.claude/agents/laporan-kerja.md`](../../../.claude/agents/laporan-kerja.md) sudah mewajibkan paragraf penjelasan dan dampak bisnis (baris 12), dan langkah 4 sudah menyuruh menggabungkan commit terkait jadi satu seksi. Yang terjadi sebaliknya: satu fitur utuh dipecah ke 7 kategori, tiap seksi kebagian 1–2 commit — terlalu tipis untuk ditulis sebagai cerita, jadi jatuhnya menyalin judul commit. Jadi akar masalahnya pengelompokan, bukan kurangnya aturan menulis.

## 2. Keputusan yang sudah dikunci

| # | Keputusan |
|---|---|
| D1 | Manual book jadi **dokumen tetap di `docs/panduan-pengguna/`**, bukan ditulis inline di `LAPORAN-KERJA.md`. Alasan: `LAPORAN-KERJA.md` di-ignore git dan digenerate ulang oleh agent — isi manual akan hilang tertimpa. |
| D2 | Pembaca manual adalah **pengguna sistem (sales, akuntan, admin)**, bukan atasan. Isinya panduan operasional langkah demi langkah, termasuk aturan/larangan dan pesan error yang muncul di layar. Bisa dipakai melatih staf baru. |
| D3 | Struktur manual **berbasis skenario/tugas**, bukan per peran atau per layar. Bab disusun dari niat pengguna ("biaya supplier ternyata berbeda"), karena aturan penting paling masuk akal dijelaskan di tempat aturan itu menggigit. |
| D4 | Cakupan sekarang: **satu manual untuk Rincian Profit Tetap Terbuka**. Ke depan tiap fitur baru dapat manualnya sendiri. Backfill 13 modul `design-system/` yang ada **tidak** termasuk lingkup ini. |
| D5 | `.claude/agents/laporan-kerja.md` diperbarui supaya satu seksi laporan = satu **fitur**, dan laporan menautkan manual book terkait. |
| D6 | Fitur yang dikerjakan lintas tanggal ditulis **satu seksi utuh di tanggal commit terakhirnya**, dengan keterangan rentang tanggal ("dikerjakan 5–6 Agustus"). Tidak diulang per tanggal. |
| D7 | Manual ditulis dari **pembacaan kode**, bukan dari dokumen desain saja — mengikuti konvensi `docs/` yang sudah ada. Pesan error dikutip persis seperti yang muncul di layar. |

## 3. Struktur berkas

```
docs/panduan-pengguna/
├── README.md          — indeks manual book
└── rincian-profit.md  — manual fitur Rincian Profit Tetap Terbuka
```

`docs/README.md` mendapat satu baris baru di tabel struktur.

Penempatan ini sengaja sejajar dengan `design-system/` tapi terpisah, dan perbedaannya ditulis eksplisit di kedua indeks:

| Folder | Menjawab | Untuk |
|---|---|---|
| `design-system/` | *Bagaimana fitur bekerja* (alur, model data, route) | Developer |
| `panduan-pengguna/` | *Bagaimana saya memakainya* (langkah, aturan, pesan error) | Staf pengguna |

## 4. Template isi tiap manual

| Bagian | Isi |
|---|---|
| Untuk siapa & di mana | Peran yang boleh + jalur menu persisnya |
| Apa gunanya | 2–3 kalimat, bahasa awam |
| Skenario | Bagian inti: *situasi → langkah bernomor → hasil yang terlihat* |
| Aturan & batasan | Tabel: tindakan → boleh/tidak → pesan yang muncul di layar |
| Jejak tercatat | Apa yang masuk riwayat tour dan apa yang tidak |
| Salah kaprah | Kekhawatiran keliru yang bikin staf takut memakai fitur |

## 5. Isi manual Rincian Profit

Fakta berikut **sudah diverifikasi langsung dari kode**, bukan dari dokumen desain:

| Fakta | Sumber |
|---|---|
| Peran yang boleh: `admin`, `sales`, `accountant` | [`routes/web.php:199`](../../../routes/web.php#L199) |
| Hapus item ber-Bill ditolak, pesan persis: *"Item ini sudah dibuatkan Bill — hapus Bill-nya dulu bila memang keliru."* | [`InvoiceItemController.php:157-161`](../../../app/Http/Controllers/InvoiceItemController.php#L157-L161) |
| Riwayat tour dicatat **hanya** bila `is_approved` benar | [`InvoiceItemController.php:177-189`](../../../app/Http/Controllers/InvoiceItemController.php#L177-L189) |
| Riwayat memuat ringkasan *"nilai lama → nilai baru"* per field yang berubah | [`InvoiceItemController.php:196-214`](../../../app/Http/Controllers/InvoiceItemController.php#L196-L214) |
| Mengubah item ber-Bill tetap boleh (hanya hapus yang ditolak) | Guard `destroy()` saja, `update()`/`bulkUpdate()` tanpa guard Bill |

Tiga skenario, diambil dari pemicu asli fitur ([spek 5 Agu §1](2026-08-05-rincian-profit-tetap-terbuka-design.md)):

1. **Rincian Profit terlanjur kosong saat invoice disetujui** — profit tersimpan salah (tagihan penuh dikurangi nol); ini cara memperbaikinya.
2. **Biaya aktual supplier berbeda dari estimasi** — koreksi angkanya, termasuk kapan perlu koordinasi dengan akuntan soal Bill.
3. **Ada item keliru yang ingin dihapus** — termasuk apa yang terjadi bila item itu sudah punya Bill, dan urutan benar untuk membongkarnya.

Bagian "Salah kaprah" wajib memuat satu koreksi ini: **mengedit Rincian Profit tidak mengubah tagihan customer.** Tagihan dihitung dari `unit_price × pax` dan tidak pernah tersentuh Rincian Profit (keputusan D7 spek 5 Agustus). Ini kekhawatiran yang paling mungkin membuat staf ragu memakai fitur.

## 6. Perubahan pada agent `laporan-kerja`

Empat perubahan pada [`.claude/agents/laporan-kerja.md`](../../../.claude/agents/laporan-kerja.md):

1. **Pengelompokan per fitur.** Satu seksi = satu fitur. Commit `docs:`, `feat:`, dan `fix:` yang menyangkut fitur sama wajib jadi satu seksi dengan semua tautan buktinya dikumpulkan di situ. Nama kategori generik ("Perbaikan", "Sistem & Otomasi", "Pengembangan Sistem") **dilarang** jadi judul seksi.
2. **Larangan menyalin judul commit** sebagai isi laporan, termasuk larangan meninggalkan istilah Inggris yang tidak diterjemahkan.
3. **Bagian "Cara pakai" per seksi**: ringkasan langkah singkat + tautan ke manual lengkap di `docs/panduan-pengguna/` bila ada.
4. **Commit `docs:` tidak berdiri sendiri** sebagai fitur — menempel ke seksi fitur yang didokumentasikannya.

Plus aturan lintas tanggal (D6).

## 7. Penulisan ulang laporan 6 Agustus

7 seksi kategori → **1 seksi fitur** ("Rincian Profit Tetap Terbuka") berisi paragraf penjelasan, tabel dampak, ringkasan cara pakai, tautan manual, dan seluruh tautan bukti commit. Seksi 5 Agustus lebur ke dalamnya (commit desain `7955982` bagian dari fitur yang sama), dengan keterangan rentang "dikerjakan 5–6 Agustus" sesuai D6.

## 8. Di luar lingkup

- Backfill manual untuk 13 modul `design-system/` yang sudah ada (D4).
- Tangkapan layar / gambar. Manual ditulis berbasis teks dulu; gambar bisa menyusul bila dibutuhkan.
- Perubahan pada agent `laporan-kerja` global (yang mencakup client_wm/api_wm/admin_wm). Lingkup di sini hanya agent tingkat proyek erp_wm.
- Perubahan kode aplikasi apa pun. Ini pekerjaan dokumentasi murni.

## 9. Verifikasi

Pekerjaan ini tidak menyentuh kode aplikasi, jadi tidak ada tes otomatis yang relevan. Gerbang penerimaan:

1. Setiap pesan error dan nama peran yang dikutip di manual **cocok persis** dengan kode saat ini (dicek ulang lewat `grep` saat penulisan, bukan disalin dari spek).
2. Setiap tautan berkas relatif di dokumen baru bisa dibuka (tidak ada tautan mati).
3. `LAPORAN-KERJA.md` hasil tulis ulang tidak memuat satu pun judul commit mentah dan tidak memuat istilah Inggris yang tak diterjemahkan.
4. `docs/README.md` memuat baris `panduan-pengguna/`, dan perbedaannya dengan `design-system/` ditulis eksplisit.
