# 10 — Temuan

[← 09 Matriks Kondisi](09-matriks-kondisi.md) · [Peta](README.md)

---

Bagian ini **bukan** dokumentasi perilaku, melainkan catatan hal-hal yang ditemukan saat menelusuri kode. Tidak ada satu pun yang sedang menyebabkan kerusakan. Keputusan apakah perlu diubah ada di tangan tim.

Diurutkan menurut dampak.

---

## 10.1 Syarat status `confirmed` hanya ada di UI

**Temuan.** `InvoiceController::store()` hanya memeriksa satu hal — apakah tour sudah punya invoice. Tidak ada pemeriksaan status tour sama sekali.

Yang mencegah invoice dibuat pada tour berstatus `inquiry` atau `negotiation` semata-mata adalah `v-if` di [`Edit.vue:105`](../../resources/js/Pages/Tours/Edit.vue#L105).

**Dampak.** Permintaan `POST /tours/{tour}/invoices` yang dikirim langsung akan berhasil pada tour berstatus apa pun, termasuk `cancelled`. Nomor invoice ikut terbit dan terpakai selamanya.

**Kemungkinan penyebab tak sengaja**, bukan hanya penyalahgunaan: kode yang memanggil route ini dari tempat lain di masa depan tidak akan mendapat peringatan apa pun.

**Bila ingin ditutup:** tambahkan pemeriksaan di awal `store()`, sejajar dengan pemeriksaan yang sudah ada.

```php
if ($tour->status !== 'confirmed') {
    throw ValidationException::withMessages([
        'invoice' => 'Invoice hanya dapat dibuat untuk tour berstatus Confirmed.',
    ]);
}
```

---

## 10.2 Route pembayaran tidak memeriksa status invoice

**Temuan.** `InvoicePaymentController::store()` dan `destroy()` tidak memiliki penjaga status sama sekali — tidak memeriksa `approved_at`, tidak memeriksa apa pun selain validasi field.

Yang menghalangi pencatatan pembayaran pada invoice draft hanyalah `v-if="isApproved(inv)"` di panel.

Perlu dicatat pula: route sales (`invoice-deposits.*`) dan route keuangan (`invoice-payments.*`) menunjuk ke **method yang sama**. Perbedaannya hanya middleware grup. Artinya sales tetap dapat menambah dan menghapus pembayaran setelah invoice masuk Keuangan.

**Dampak.**
- Invoice draft bisa memiliki pembayaran, dan `status`-nya berubah menjadi `partial`/`paid` sementara `approved_at` masih kosong — kombinasi yang tidak diperhitungkan alur mana pun
- Penghapusan pembayaran oleh sales pada invoice yang sudah masuk Keuangan tidak meninggalkan jejak di riwayat tour

**Catatan tambahan.** Tidak ada validasi yang mencegah kelebihan bayar. Pembayaran melebihi `total` diterima, status menjadi `paid`, dan sisa tagihan ditampilkan `0` karena `Math.max(..., 0)` — kelebihannya tidak terlihat di mana pun.

**Bila ingin ditutup:** tambahkan penjaga di `store()`, dan pertimbangkan apakah penghapusan pembayaran pasca-persetujuan memang boleh dilakukan sales.

---

## 10.3 Perubahan `pax` tour dapat memblokir persetujuan tanpa penjelasan

**Temuan.** `syncProformaTotal()` membaca `pax` dari **tour**, bukan dari invoice:

```php
$pax = (int) ($this->tour?->pax ?? $this->pax ?? 1);
```

Sementara `baselineMatched()` di UI membandingkan total proforma terkini dengan `baseline_total` yang terkunci.

**Rangkaian kejadiannya:**

1. Sales A mengunci patokan pada 10 pax × Rp 5 juta = Rp 50 juta
2. Sales B (atau admin) mengubah `pax` tour menjadi 12 di panel Header
3. Panel invoice menghitung ulang: 12 × 5 juta = Rp 60 juta
4. `baselineMatched()` menjadi false → **tombol Setujui mati**
5. Tidak ada pesan apa pun yang menjelaskan sebabnya

**Penilaian.** Perilaku memblokirnya benar dan tampaknya memang disengaja — patokan memang seharusnya menghalangi persetujuan atas angka yang bergeser diam-diam. Yang bermasalah adalah **ketiadaan penjelasan**. Bagi sales, tombol terlihat "tiba-tiba rusak".

**Bila ingin diperbaiki:** cukup di sisi tampilan, tanpa mengubah logika. Tampilkan keterangan di dekat tombol saat `!baselineMatched(inv)`, misalnya:

> Total tagihan (Rp 60.000.000) berbeda dari patokan (Rp 50.000.000). Tekan **Samakan Patokan** sebelum menyetujui.

---

## 10.4 "Harga / pax" dipaksakan pada tipe yang ditagih per job

**Temuan.** `syncProformaTotal()` tidak membaca tipe penjualan sama sekali. Satu rumus berlaku untuk ketujuh tipe:

```php
$pax   = (int) ($this->tour?->pax ?? $this->pax ?? 1);
$total = (float) $this->unit_price * max($pax, 1);
```

Label di panel juga tetap, apa pun tipenya:

```vue
<label>Harga / pax ({{ currency }})</label>
...
× <span>{{ tourPax || 1 }} pax</span>
```

**Terdampak: 4 dari 7 tipe penjualan** — `guide`, `rental`, `document`, `ticketing`. Keempatnya lazim ditagih per hari, per unit, per dokumen, atau per job; bukan per kepala.

**Contoh nyata.** Jasa guide 3 hari × Rp 500.000 = Rp 1.500.000 untuk rombongan 20 orang. Karena `tour.pax` = 20, sales terpaksa mengisi:

```
Rp 1.500.000 ÷ 20 = Rp 75.000   ← angka yang tidak ada di daftar harga mana pun
```

Angka Rp 75.000 itu lalu tersimpan permanen sebagai `unit_price` invoice.

**Memperburuk keadaan:** `pax` diambil dari **tour**, bukan invoice. `$this->tour?->pax` menang lebih dulu, sehingga mengubah `pax` pada invoice saja tidak berpengaruh — sales harus mengubah `pax` tour-nya.

**Yang membatasi kerusakan.** `unit_price` **tidak pernah dicetak** ke PDF customer — sudah diperiksa langsung ke `invoice.blade.php`. Yang tampil hanya "Total Pax", baris deskripsi, dan total akhir. Jadi angka janggal itu tidak bocor ke customer; ia hanya mengotori catatan internal.

**Akar masalah.** `tour.pax` memikul dua tanggung jawab yang berbeda sekaligus:

| Peran | Dipakai di | Untuk jasa guide |
|---|---|---|
| Ukuran rombongan (informasi) | "Total Pax: 20 pax" di PDF | **20 benar** |
| Pengali tagihan (perhitungan) | `total = unit_price × pax` | **20 salah** |

Pada paket tour per orang kedua peran ini kebetulan bernilai sama, sehingga masalahnya tidak pernah terasa. Pada jasa yang ditagih per job keduanya berbeda, dan sistem tidak menyediakan cara memisahkannya.

**Jalan keluar yang tersedia sekarang:**

| Kondisi | Yang bisa dilakukan | Konsekuensi |
|---|---|---|
| Ukuran rombongan tidak perlu tampil | set `pax` tour = 1 | `unit_price` langsung = nilai tagihan; PDF mencetak "Total Pax: 1 pax" |
| Ukuran rombongan harus tampil | tidak ada jalan bersih | terpaksa membagi; hasil pembagian tersimpan permanen |

**Dua arah perbaikan:**

- **Murah dan tanpa risiko** — ubah **label** mengikuti tipe penjualan: "Harga / hari" untuk guide, "Harga / unit" untuk rental, "Harga / dokumen" untuk document. Rumus tidak disentuh; hanya kata-katanya dibuat jujur terhadap apa yang sebenarnya dikalikan. Tidak menyelesaikan masalah pembagian, tetapi menghilangkan kebingungannya.
- **Perbaikan sebenarnya** — pisahkan pengali tagihan dari ukuran rombongan. Tambahkan kolom `billing_qty` pada invoice yang default-nya `tour.pax` tetapi dapat diubah, lalu `total = unit_price × billing_qty`. Menyelesaikan akar masalah untuk keempat tipe sekaligus, tetapi menyentuh `syncProformaTotal()`, perhitungan patokan, dan PDF — perlu direncanakan, bukan ditambal.

Berkaitan dengan [§10.3](#103-perubahan-pax-tour-dapat-memblokir-persetujuan-tanpa-penjelasan): keduanya berakar pada `tour.pax` yang menjadi sumber tunggal untuk hal-hal yang sebenarnya berbeda.

---

## 10.5 Pesan galat menyarankan jalan keluar yang tidak ada

**Temuan.** `InvoiceItemController::ensureEditable()` menampilkan:

> "Invoice sudah disetujui dan masuk Keuangan, tidak bisa diubah. **Buat invoice tambahan bila ada perubahan.**"

Namun `InvoiceController::store()` menolaknya:

> "Tour ini sudah punya invoice — satu tour hanya boleh satu invoice."

**Dampak.** Sales yang mengikuti saran pada pesan pertama akan menemui penolakan pada pesan kedua, tanpa jalan keluar lain. Setelah invoice disetujui dan ternyata ada kekeliruan nominal, tidak ada mekanisme koreksi apa pun di aplikasi — hanya intervensi database.

**Dua arah penyelesaian**, keduanya keputusan bisnis, bukan teknis:

- **Terima aturan satu invoice**, lalu ubah pesan galat agar tidak menjanjikan sesuatu yang tidak ada. Termurah.
- **Izinkan invoice tambahan** untuk tour yang invoice sebelumnya sudah disetujui. Berdampak luas: penomoran, penjumlahan tagihan, Ringkasan Biaya, dan pelaporan Keuangan semuanya berasumsi satu invoice per tour.

---

## 10.6 Catatan kecil

**Field `date` pada baris deskripsi bertipe teks bebas.** Divalidasi sebagai `string|max:255`, bukan `date`. Tidak diurai maupun diformat — apa yang diketik itu yang tercetak di PDF. Ini tampaknya disengaja agar bisa menulis "Hari 1–3" atau "TBA", tetapi berarti tidak ada jaminan konsistensi format antar invoice.

**`bulkUpdate()` melewati id asing secara diam-diam.** Baris `$items->get($row['id'])?->update(...)` melindungi dari perubahan lintas invoice — perlindungan yang benar. Namun id yang keliru tidak memunculkan galat apa pun, sehingga autosave yang mengirim id salah akan tampak berhasil padahal tidak menyimpan apa-apa.

**Menghapus invoice draft tidak mengembalikan nomornya.** `nextNumber()` memakai `withTrashed()`, jadi membuat ulang invoice menghasilkan nomor berikutnya, bukan nomor yang sama. Ini benar untuk audit, tetapi dapat menimbulkan pertanyaan "kenapa nomor kami loncat".
