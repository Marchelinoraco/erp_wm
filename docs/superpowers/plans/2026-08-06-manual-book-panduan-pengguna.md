# Manual Book Pengguna & Laporan Kerja per Fitur — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Membuat manual book pengguna di `docs/panduan-pengguna/` untuk fitur Rincian Profit Tetap Terbuka, lalu memperbaiki agent `laporan-kerja` dan menulis ulang laporan 6 Agustus supaya dikelompokkan per fitur dan menautkan manual itu.

**Architecture:** Pekerjaan dokumentasi murni — tidak ada kode aplikasi yang disentuh, tidak ada migrasi, tidak ada tes otomatis baru. Tiga deliverable berurutan: manual (Task 1) → aturan agent (Task 2) → laporan yang memakai keduanya (Task 3). Verifikasi dilakukan lewat `grep` terhadap kode sumber (memastikan setiap kutipan cocok dengan kode saat ini) dan pengecekan tautan, bukan lewat test runner.

**Tech Stack:** Markdown. Repo Laravel + Inertia/Vue (hanya dibaca, tidak diubah).

**Spek:** [`docs/superpowers/specs/2026-08-06-manual-book-panduan-pengguna-design.md`](../specs/2026-08-06-manual-book-panduan-pengguna-design.md)

## Global Constraints

- **Branch kerja:** `docs/panduan-pengguna-manual-book` (sudah dibuat dari `dev`). Jangan merge ke `dev`/`main` sendiri.
- **Path absolut wajib.** Direktori kerja shell di environment ini bisa berpindah sendiri ke repo lain. Gunakan `git -C /Users/marchelinoraco/Documents/2026/erp_wm` atau `cd /Users/marchelinoraco/Documents/2026/erp_wm && …` di **setiap** perintah.
- **Bahasa Indonesia** di seluruh dokumen. Istilah Inggris hanya boleh muncul kalau itu memang label yang tertulis di layar (mis. tombol `+ Tambah Produk`, kolom `Cost/unit`).
- **Kutipan pesan error harus persis** seperti di kode — tidak boleh diparafrase.
- **Nama berkas kebab-case**, mengikuti konvensi di [`docs/README.md`](../../README.md).
- **Jangan sentuh** `docs/design-system/13-my-jobs-manifest.md` — ada perubahan trailing-whitespace milik pengguna yang belum di-commit di situ. Biarkan apa adanya, jangan di-`git add`.
- **Tanpa tangkapan layar.** Manual berbasis teks (spek §8).
- **Jangan ubah kode aplikasi apa pun** (spek §8).

---

## Fakta terverifikasi (dipakai di Task 1 dan Task 3)

Semua sudah dicek langsung dari kode pada 6 Agustus 2026. Task 1 Step 1 mengecek ulang; kalau ada yang tidak cocok, **kode yang menang**, perbarui tabel ini.

| Fakta | Nilai | Sumber |
|---|---|---|
| Peran yang boleh mengedit | `admin`, `sales`, `accountant` | `routes/web.php:199` |
| Pesan tolak hapus item ber-Bill | `Item ini sudah dibuatkan Bill — hapus Bill-nya dulu bila memang keliru.` | `app/Http/Controllers/InvoiceItemController.php:159` |
| Riwayat tour dicatat | hanya bila `invoice->is_approved` benar | `InvoiceItemController.php:177-181` |
| Isi riwayat | ringkasan `nilai lama → nilai baru` per field yang berubah | `InvoiceItemController.php:196-214` |
| Guard Bill | hanya di `destroy()`; `update()`/`bulkUpdate()` tidak punya guard Bill (`Bill::` muncul 1× di file) | `InvoiceItemController.php:157` |
| Judul panel | `Rincian Profit (internal · IDR)` | `RincianProfitEditor.vue:209` |
| Kolom tabel | Deskripsi, Tanggal, Qty, Mlm, Cost/unit, Sell/unit, Total Jual | `RincianProfitEditor.vue:221-227` |
| Penyimpanan | otomatis, jeda 1,5 detik setelah berhenti mengetik | `RincianProfitEditor.vue:54` |
| Indikator status | `● Ada perubahan…` → `⏳ Menyimpan…` → `✓ Tersimpan` | `RincianProfitEditor.vue:283` |
| Pesan error tampil | baris merah di atas tabel | `RincianProfitEditor.vue:215` |
| Dialog konfirmasi hapus | judul `Hapus item ini?`, tombol `Hapus` | `RincianProfitEditor.vue:101` |
| Tombol `+ Tambah Item` | **disembunyikan** untuk sales (`:allow-manual-add="false"`), tampil untuk akuntan | `InvoicesPanel.vue:779`, `Finance/Tour.vue:419` |
| Jalur sales | halaman Tour → panel Invoice; **hanya muncul bila status tour `confirmed`** | `Tours/Edit.vue:111` |
| Jalur akuntan | Keuangan → halaman Tour (`/finance/{tour}`) | `routes/web.php:300` |
| Tagihan customer | `unit_price × pax`, tidak pernah dipengaruhi Rincian Profit | spek 5 Agu D7 |

---

### Task 1: Manual book Rincian Profit + indeks

**Files:**
- Create: `docs/panduan-pengguna/rincian-profit.md`
- Create: `docs/panduan-pengguna/README.md`
- Modify: `docs/README.md` (tabel "Struktur" + bagian pembeda folder)

**Interfaces:**
- Consumes: tabel "Fakta terverifikasi" di atas.
- Produces: path `docs/panduan-pengguna/rincian-profit.md` — ditautkan oleh Task 2 (sebagai contoh dalam aturan agent) dan Task 3 (tautan nyata di laporan). Judul H1 berkas: `Panduan: Rincian Profit`.

- [ ] **Step 1: Cek ulang seluruh fakta terhadap kode**

Jangan percaya tabel di atas begitu saja — kode bisa sudah berubah.

```bash
cd /Users/marchelinoraco/Documents/2026/erp_wm
sed -n '199p' routes/web.php
sed -n '157,161p;177,181p' app/Http/Controllers/InvoiceItemController.php
grep -c "Bill::" app/Http/Controllers/InvoiceItemController.php
sed -n '209p;221,227p;283p' resources/js/Components/Tours/RincianProfitEditor.vue
sed -n '54p;101p;215p' resources/js/Components/Tours/RincianProfitEditor.vue
grep -n "allow-manual-add" resources/js/Components/Tours/InvoicesPanel.vue
sed -n '111p' resources/js/Pages/Tours/Edit.vue
```

Expected: `role:admin,sales,accountant`; pesan Bill persis seperti di tabel; `grep -c "Bill::"` = `1`; indikator `● Ada perubahan…`/`⏳ Menyimpan…`/`✓ Tersimpan`; `setTimeout(flushSaves, 1500)`; `:allow-manual-add="false"` di InvoicesPanel; `tour.status === 'confirmed'` di Edit.vue.
Kalau ada yang berbeda: perbaiki tabel fakta di rencana ini dulu, baru menulis manual.

- [ ] **Step 2: Tulis `docs/panduan-pengguna/rincian-profit.md`**

Judul: `# Panduan: Rincian Profit`. Enam bagian sesuai template spek §4, dengan isi wajib berikut.

**Bagian "Untuk siapa & di mana"** — tabel:

| Peran | Jalur | Tambah item lewat |
|---|---|---|
| Sales | Tour → panel Invoice (**hanya kalau status tour sudah `confirmed`**) | tombol `+ Tambah Produk` (katalog) |
| Akuntan / Admin | Keuangan → pilih tour | tombol `+ Tambah Item` (isi bebas) |

Sebutkan panel bernama `Rincian Profit (internal · IDR)` dan bahwa angka di dalamnya **internal** — tidak pernah tampil di dokumen yang diterima pelanggan.

**Bagian "Apa gunanya"** — 2–3 kalimat: Rincian Profit mencatat biaya modal per item supaya sistem bisa menghitung margin tour. Sejak 6 Agustus 2026 ia bisa diedit kapan saja, termasuk setelah invoice disetujui.

**Bagian "Skenario"** — tiga skenario, tiap satu berformat *situasi → langkah bernomor → hasil*:

1. *Rincian Profit terlanjur kosong saat invoice disetujui.* Akibatnya profit tersimpan = tagihan penuh dikurangi nol (margin salah). Langkah: buka tour → isi item → tunggu `✓ Tersimpan`. Hasil: margin ikut terkoreksi, dan tiap item yang ditambahkan tercatat di riwayat tour.
2. *Biaya aktual supplier berbeda dari estimasi.* Langkah: ubah `Cost/unit` pada baris terkait → tunggu indikator `✓ Tersimpan`. Tegaskan: mengubah item yang sudah punya Bill **boleh** — Bill dan Rincian Profit dua angka terpisah, jadi kalau Bill-nya juga perlu diperbaiki, itu dikerjakan terpisah di bagian "AP — Bill ke Supplier".
3. *Ada item keliru yang ingin dihapus.* Langkah: klik `✕` di ujung baris → dialog `Hapus item ini?` → `Hapus`. Bila item sudah punya Bill, muncul baris merah `Item ini sudah dibuatkan Bill — hapus Bill-nya dulu bila memang keliru.` dan item **tidak** terhapus; urutan benarnya: hapus Bill dulu di bagian AP, baru hapus itemnya.

Sertakan catatan cara simpan (berlaku untuk semua skenario): tidak ada tombol Simpan — perubahan tersimpan otomatis ~1,5 detik setelah berhenti mengetik; tunggu `✓ Tersimpan` sebelum menutup halaman.

**Bagian "Aturan & batasan"** — tabel:

| Tindakan | Boleh? | Yang terjadi di layar |
|---|---|---|
| Menambah item setelah invoice disetujui | Ya | Tersimpan, satu baris masuk riwayat tour |
| Mengubah angka item setelah disetujui | Ya | Tersimpan, riwayat mencatat `nilai lama → nilai baru` |
| Mengubah item yang sudah punya Bill | Ya | Bill tidak ikut berubah — perbaiki Bill terpisah bila perlu |
| Menghapus item yang sudah punya Bill | **Tidak** | Baris merah: `Item ini sudah dibuatkan Bill — hapus Bill-nya dulu bila memang keliru.` |
| Menghapus item tanpa Bill | Ya | Dialog konfirmasi `Hapus item ini?` dulu |
| Mengedit saat tour belum `confirmed` (sisi sales) | Panel belum muncul | Selesaikan dulu konfirmasi tour |

**Bagian "Jejak tercatat"** — riwayat tour hanya mencatat perubahan **setelah invoice disetujui**; edit sebelum disetujui tidak dicatat karena itu area kerja normal sales. Tiap baris memuat nama pengguna dan ringkasan `nilai lama → nilai baru`.

**Bagian "Salah kaprah"** — minimal dua:
- *"Mengedit Rincian Profit mengubah tagihan customer."* → **Tidak.** Tagihan dihitung dari `unit_price × pax` dan tidak pernah tersentuh Rincian Profit.
- *"Kalau invoice sudah disetujui berarti sudah terkunci."* → Yang terkunci adalah kurs invoice dan tagihannya, bukan Rincian Profit.

Tautkan di bagian penutup: [`design-system/01-penjualan-tour.md`](../design-system/01-penjualan-tour.md) dan [`logika-pembuatan-invoice/04-rincian-profit.md`](../logika-pembuatan-invoice/04-rincian-profit.md) untuk pembaca yang ingin detail teknis.

- [ ] **Step 3: Tulis `docs/panduan-pengguna/README.md`**

Indeks folder. Isi: judul `# Panduan Pengguna`, satu paragraf bahwa folder ini menjawab *"bagaimana saya memakai fitur ini?"* untuk staf (sales, akuntan, admin) — berbeda dari `design-system/` yang menjelaskan *bagaimana fitur bekerja* untuk developer. Lalu tabel daftar panduan:

| Panduan | Untuk peran | Isi singkat |
|---|---|---|
| [rincian-profit.md](rincian-profit.md) | Sales, Akuntan, Admin | Mengisi dan mengoreksi biaya modal tour, termasuk setelah invoice disetujui |

Tutup dengan konvensi: satu berkas per fitur, kebab-case, ditulis dari pembacaan kode, dan kutipan pesan error harus persis seperti di layar.

- [ ] **Step 4: Tambahkan folder baru ke `docs/README.md`**

Sisipkan satu baris di tabel "Struktur", tepat setelah baris `design-system/`:

```markdown
| **[panduan-pengguna/](panduan-pengguna/README.md)** | *Bagaimana memakai tiap fitur* — panduan operasional langkah demi langkah untuk staf (sales, akuntan, admin), berbasis skenario |
```

Lalu di bagian "Bedanya design-system vs desain", tambahkan satu poin pembeda ketiga:

```markdown
- **panduan-pengguna/** menjelaskan **cara memakai** fitur untuk staf non-developer — `design-system/` menjawab *bagaimana fitur bekerja*, `panduan-pengguna/` menjawab *bagaimana saya memakainya*.
```

- [ ] **Step 5: Verifikasi tautan dan kutipan**

```bash
cd /Users/marchelinoraco/Documents/2026/erp_wm/docs/panduan-pengguna
for p in ../design-system/01-penjualan-tour.md ../logika-pembuatan-invoice/04-rincian-profit.md rincian-profit.md; do [ -e "$p" ] && echo "OK   $p" || echo "MATI $p"; done
cd /Users/marchelinoraco/Documents/2026/erp_wm
grep -q "$(sed -n '159p' app/Http/Controllers/InvoiceItemController.php | sed "s/.*'invoice' => '//;s/',$//")" docs/panduan-pengguna/rincian-profit.md && echo "OK pesan Bill cocok persis" || echo "GAGAL pesan Bill tidak cocok"
grep -n "panduan-pengguna" docs/README.md
```

Expected: semua tautan `OK`, `OK pesan Bill cocok persis`, dan `docs/README.md` memuat `panduan-pengguna`.
Kalau `GAGAL`: perbaiki kutipan di manual agar sama persis dengan kode, lalu ulangi.

- [ ] **Step 6: Commit**

```bash
cd /Users/marchelinoraco/Documents/2026/erp_wm
git add docs/panduan-pengguna/ docs/README.md
git status --short   # pastikan 13-my-jobs-manifest.md TIDAK ikut ter-stage
git commit -m "docs: manual book pengguna untuk Rincian Profit

Folder baru docs/panduan-pengguna/ menjawab \"bagaimana saya memakai fitur
ini?\" untuk staf, terpisah dari design-system/ yang untuk developer.
Panduan pertama: Rincian Profit, disusun berbasis skenario dengan pesan
error dikutip persis seperti yang muncul di layar.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 2: Perbaikan aturan agent `laporan-kerja`

**Files:**
- Modify: `.claude/agents/laporan-kerja.md`

**Interfaces:**
- Consumes: path manual dari Task 1 (`docs/panduan-pengguna/`) sebagai contoh tautan dalam aturan.
- Produces: aturan tertulis yang dipakai Task 3 saat menulis ulang laporan.

- [ ] **Step 1: Baca file agent saat ini**

Baca `.claude/agents/laporan-kerja.md` seluruhnya. Perhatikan: aturan "wajib paragraf penjelasan + dampak bisnis" sudah ada di baris 12, dan langkah 4 sudah menyuruh menggabungkan commit terkait. Yang ditambahkan di sini adalah **penegasan pengelompokan** dan **larangan konkret** — jangan menghapus aturan yang sudah ada.

- [ ] **Step 2: Perketat langkah 4 (pengelompokan per fitur)**

Ganti langkah 4 yang sekarang berbunyi:

```markdown
4. **Gabungkan commit yang saling terkait** menjadi satu seksi fitur. Satu seksi = satu topik pekerjaan (mis. semua commit soal invoice hari ini jadi satu seksi "Invoice").
```

menjadi:

```markdown
4. **Gabungkan commit yang saling terkait** menjadi satu seksi **fitur**. Satu seksi = satu fitur, BUKAN satu kategori.

   - Commit `docs:`, `feat:`, dan `fix:` yang menyangkut fitur yang sama WAJIB jadi **satu** seksi, dengan seluruh tautan buktinya dikumpulkan di seksi itu.
   - Judul seksi memakai **nama fitur** (mis. "Rincian Profit Tetap Terbuka"). DILARANG memakai nama kategori generik sebagai judul seksi: "Perbaikan", "Pengembangan Sistem", "Sistem & Otomasi", "Akses & Pengguna", dan sejenisnya.
   - Commit `docs:` TIDAK berdiri sendiri sebagai seksi — ia menempel ke seksi fitur yang didokumentasikannya.
   - Kalau satu fitur dikerjakan lintas beberapa hari, tulis **satu seksi utuh di tanggal commit terakhirnya**, dengan keterangan rentang di awal paragraf (mis. "Dikerjakan 5–6 Agustus 2026."). Jangan mengulang fitur yang sama di tiap tanggal.

   Tanda seksi terlalu tipis: kalau sebuah seksi cuma berisi 1–2 baris salinan judul commit, berarti pengelompokannya salah — gabungkan ke seksi fiturnya.
```

- [ ] **Step 3: Tambahkan larangan menyalin judul commit**

Di bagian "Aturan tabel & poin", tambahkan dua butir di akhir:

```markdown
- **DILARANG menyalin judul commit** sebagai isi laporan. Judul commit adalah catatan teknis untuk developer; laporan harus menjelaskan dampaknya bagi pekerjaan orang. Kalau sebuah butir laporan masih terbaca seperti "Fix: filter router guard to GET navigations only", itu belum ditulis — itu baru disalin.
- **Tidak boleh ada istilah Inggris yang tidak diterjemahkan** (mis. *router guard*, *ResizeObserver*, *bulk update*). Ganti dengan dampaknya dalam bahasa Indonesia, atau hilangkan sama sekali bila cuma detail teknis internal.
```

- [ ] **Step 4: Tambahkan bagian "Cara pakai" ke format laporan**

Di blok "Format laporan", sisipkan sebelum baris `**Bukti pengerjaan:**`:

```markdown
<Bila fitur ini mengubah cara kerja pengguna, tambahkan ringkasan langkah singkat:>

**Cara pakai:** <2–4 langkah ringkas>

<Bila ada manual lengkapnya di docs/panduan-pengguna/, tautkan:>

📖 Panduan lengkap: [<nama fitur>](docs/panduan-pengguna/<berkas>.md)
```

- [ ] **Step 5: Verifikasi aturan tidak saling bertabrakan**

```bash
cd /Users/marchelinoraco/Documents/2026/erp_wm
grep -n "kategori\|Kategori" .claude/agents/laporan-kerja.md
grep -n "panduan-pengguna" .claude/agents/laporan-kerja.md
```

Expected: tidak ada sisa aturan yang masih menyuruh mengelompokkan per kategori (yang tersisa hanya larangannya), dan `panduan-pengguna` muncul di blok format.
Baca ulang langkah 4 dan bagian "Aturan tabel & poin" berdampingan — pastikan tidak ada instruksi yang kontradiktif.

- [ ] **Step 6: Commit**

```bash
cd /Users/marchelinoraco/Documents/2026/erp_wm
git add .claude/agents/laporan-kerja.md
git commit -m "docs: agent laporan-kerja kelompokkan per fitur, bukan per kategori

Akar masalah laporan yang tipis: pengelompokan per kategori memecah satu
fitur ke banyak seksi, tiap seksi terlalu tipis untuk ditulis sebagai
cerita sehingga jatuhnya menyalin judul commit. Ditambah larangan eksplisit
menyalin judul commit, aturan fitur lintas tanggal, dan bagian Cara pakai
yang menautkan manual book.

Co-Authored-By: Claude Opus 5 <noreply@anthropic.com>"
```

---

### Task 3: Tulis ulang laporan 6 Agustus

**Files:**
- Modify: `LAPORAN-KERJA.md` (root proyek)

**Interfaces:**
- Consumes: manual dari Task 1 (untuk tautan "Panduan lengkap") dan aturan dari Task 2.
- Produces: tidak ada — ini deliverable akhir.

⚠️ **`LAPORAN-KERJA.md` di-ignore git.** Task ini **tidak** punya langkah commit, dan itu memang benar. Jangan mencoba memaksanya masuk dengan `git add -f`.

- [ ] **Step 1: Kumpulkan seluruh commit fitur beserta tanggalnya**

```bash
cd /Users/marchelinoraco/Documents/2026/erp_wm
git log --no-merges --pretty="%h %ad %s" --date=format:"%Y-%m-%d" origin/main..dev
```

Expected: 12 commit, tanggal 5–6 Agustus 2026, seluruhnya menyangkut fitur Rincian Profit Tetap Terbuka.
Catat hash mana yang tanggal 5 dan mana yang 6 — dipakai untuk keterangan rentang.

- [ ] **Step 2: Ganti seluruh isi laporan dengan satu seksi fitur**

Struktur baru menggantikan 7 seksi kategori (6 Agu) + 1 seksi (5 Agu):

```markdown
# Laporan Kerja — Sistem ERP Welcome Manado

> Diperbarui otomatis dari riwayat pengembangan setiap ada perubahan. Terakhir: 6 Agustus 2026

---

## Kamis, 6 Agustus 2026

**Fokus:** Menyelesaikan fitur Rincian Profit Tetap Terbuka.

---

### 1. Rincian Profit Tetap Terbuka

Dikerjakan 5–6 Agustus 2026.

<Paragraf, maksimal 4 kalimat, bahasa non-teknis. Wajib memuat: (a) masalah lamanya —
begitu invoice disetujui, rincian biaya modal terkunci permanen untuk siapa pun, satu-satunya
jalan perbaikan adalah mengutak-atik database langsung; (b) akibatnya — kalau sales belum
sempat mengisi, margin tour tersimpan salah selamanya, dan biaya supplier yang meleset dari
estimasi tidak bisa dikoreksi; (c) yang sekarang bisa dilakukan — sales dan akuntan bisa
memperbaiki kapan saja, dengan seluruh perubahan tercatat.>

| Sebelum | Sesudah |
|---|---|
| Rincian biaya terkunci permanen begitu invoice disetujui | Bisa diperbaiki kapan saja, di status invoice apa pun |
| Hanya sales yang bisa mengisi | Sales dan akuntan/admin sama-sama bisa |
| Perbaikan hanya lewat database langsung | Cukup dari halaman Tour atau Keuangan |
| Perubahan tidak meninggalkan jejak | Tiap perubahan tercatat di riwayat tour beserta nama pengguna |

**Cara pakai:** Buka tour → panel Rincian Profit (sales lewat Tour, akuntan lewat Keuangan) →
ubah atau tambah item → perubahan tersimpan otomatis. Item yang sudah dibuatkan tagihan ke
supplier tidak bisa dihapus sampai tagihannya dihapus lebih dulu.

📖 Panduan lengkap: [Rincian Profit](docs/panduan-pengguna/rincian-profit.md)

**Pengaman yang ikut dipasang:** tagihan ke pelanggan sama sekali tidak terpengaruh perubahan
ini, dan setiap perubahan setelah invoice disetujui otomatis tercatat di riwayat tour lengkap
dengan nilai sebelum dan sesudahnya.

**Bukti pengerjaan:** <12 tautan commit, format [perubahan 1](url), [perubahan 2](url), … urut kronologis>

---
```

Ganti setiap `<…>` dengan tulisan sebenarnya. Format tautan bukti: `https://github.com/Marchelinoraco/erp_wm/commit/<hash>`.

Seksi "Rabu, 5 Agustus 2026" **dihapus** — commit desainnya (`7955982`) lebur ke seksi di atas sesuai aturan lintas tanggal Task 2.

- [ ] **Step 3: Verifikasi tidak ada sisa judul commit mentah**

```bash
cd /Users/marchelinoraco/Documents/2026/erp_wm
grep -n -i "router guard\|ResizeObserver\|vAutogrow\|bulk\|InvoicesPanel\|RincianProfitEditor\|Feat:\|Fix:\|Docs:" LAPORAN-KERJA.md
```

Expected: **tidak ada keluaran sama sekali.** Setiap baris yang muncul adalah istilah teknis yang lolos — tulis ulang jadi bahasa dampak.

```bash
grep -c "^### " LAPORAN-KERJA.md
grep -n "Perbaikan\|Sistem & Otomasi\|Pengembangan Sistem\|Akses & Pengguna" LAPORAN-KERJA.md
```

Expected: jumlah seksi `1`, dan tidak ada keluaran untuk judul kategori generik.

- [ ] **Step 4: Verifikasi tautan bukti dan tautan manual**

```bash
cd /Users/marchelinoraco/Documents/2026/erp_wm
grep -o "commit/[0-9a-f]\{7\}" LAPORAN-KERJA.md | sed 's|commit/||' | while read h; do git cat-file -e "$h^{commit}" 2>/dev/null && echo "OK   $h" || echo "MATI $h"; done
grep -c "commit/" LAPORAN-KERJA.md
[ -e docs/panduan-pengguna/rincian-profit.md ] && echo "OK tautan manual" || echo "MATI tautan manual"
```

Expected: seluruh hash `OK`, jumlah tautan commit `12`, dan `OK tautan manual`.

- [ ] **Step 5: Laporkan hasil ke pengguna**

Tampilkan: jumlah seksi sebelum (8) dan sesudah (1), konfirmasi `LAPORAN-KERJA.md` tidak di-commit karena di-ignore git, dan ingatkan bahwa tiap tautan bukti bisa dibuka untuk verifikasi independen.

---

## Sesudah semua task

Branch `docs/panduan-pengguna-manual-book` siap dipromosikan. **Jangan** merge sendiri ke `dev` atau `main` — panggil agent `branch-fitur` dan tunggu persetujuan pengguna.

Catatan untuk yang mempromosikan: ada perubahan trailing-whitespace milik pengguna di `docs/design-system/13-my-jobs-manifest.md` yang sengaja dibiarkan belum di-commit. Itu bukan bagian dari pekerjaan ini.
