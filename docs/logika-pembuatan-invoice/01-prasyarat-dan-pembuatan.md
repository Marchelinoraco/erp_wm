# 01 — Prasyarat & Pembuatan Invoice

[← Kembali ke peta](README.md) · Berikutnya: [02 — Tahap 1 Proforma](02-tahap-1-proforma.md)

---

## 1.1 Kondisi agar panel Invoice muncul

| # | Kondisi | Ditegakkan di | Kekuatan |
|---|---|---|---|
| K-01 | `tour.status === 'confirmed'` | [`Edit.vue:105`](../../resources/js/Pages/Tours/Edit.vue#L105) — `v-if` | **Hanya UI** |

```vue
<InvoicesPanel v-if="tour.status === 'confirmed'" :tour="tour" ... />
```

Selama status tour masih `inquiry`, `quotation_draft`, `quotation_sent`, `follow_up`, `negotiation`, atau `cancelled`, panel Invoice tidak dirender sama sekali. Sales tidak punya jalan menekan tombolnya.

Server **tidak** memeriksa kondisi ini — lihat [10-temuan.md §10.1](10-temuan.md).

## 1.2 Kondisi agar invoice boleh dibuat

| # | Kondisi | Ditegakkan di | Kekuatan | Pesan galat |
|---|---|---|---|---|
| K-02 | Tour belum punya invoice | `InvoiceController::store()` | **Server, keras** | "Tour ini sudah punya invoice — satu tour hanya boleh satu invoice." |

```php
if ($tour->invoices()->exists()) {
    throw ValidationException::withMessages([
        'invoice' => 'Tour ini sudah punya invoice — satu tour hanya boleh satu invoice.',
    ]);
}
```

Aturan ini mutlak dan tidak punya pengecualian. Bila ada perubahan setelah invoice disetujui, pesan galat di `InvoiceItemController` menyarankan "buat invoice tambahan" — namun jalur itu **tidak tersedia** karena `store()` menolaknya. Lihat [10-temuan.md §10.5](10-temuan.md).

Pemeriksaan memakai `exists()` pada relasi, sedangkan `Invoice` memakai `SoftDeletes`. Artinya invoice yang sudah dihapus **tidak** menghalangi pembuatan invoice baru — relasi default mengabaikan baris ter-soft-delete.

## 1.3 Payload permintaan

Panel mengirim **body kosong**:

```js
router.post(route('invoices.store', props.tour.id), {}, reload)
```

Semua field bersifat opsional dan seluruhnya jatuh ke nilai default. Aturan validasinya:

| Field | Aturan | Bila kosong |
|---|---|---|
| `pax` | `nullable\|integer\|min:1` | ambil `tour.pax` |
| `date` | `nullable\|date` | hari ini |
| `due_date` | `nullable\|date` | `tour.start_date`, atau hari ini + 7 hari |
| `notes` | `nullable\|string` | `null` |

## 1.4 Nilai awal invoice

| Kolom | Nilai | Sumber |
|---|---|---|
| `guest_name` | disalin dari tour | `tour.guest_name` |
| `pax` | `data.pax` atau `tour.pax` | |
| `date` | `data.date` atau hari ini | |
| `due_date` | `data.due_date`, atau `tour.start_date`, atau H+7 | |
| `notes` | `data.notes` atau `null` | |
| `status` | `draft` | tetap |
| `number` | dibangkitkan otomatis | hook `creating` di `Invoice::booted()` |
| `baseline_total` | `0` (default kolom) | → tahap awal = `baseline` |
| `approved_at` | `null` | → belum terkunci |

Perhatikan `guest_name` disalin sebagai **snapshot**, bukan direlasikan. Mengubah nama tamu di tour setelah invoice dibuat tidak mengubah invoice.

## 1.5 Penomoran

Nomor dibangkitkan di hook `creating`, jadi selalu terisi meskipun pemanggil tidak menyediakannya:

```php
static::creating(function (Invoice $inv) {
    if (! $inv->number) {
        $inv->number = static::nextNumber($inv->tour);
    }
});
```

### Format

```
INV - 2026 - 11 - 0001
 │     │      │     └── urut, 4 digit, reset per tahun per tipe
 │     │      └──────── kode tipe penjualan (lihat 08-perbedaan-per-tipe.md)
 │     └─────────────── tahun saat invoice DIBUAT
 └───────────────────── prefiks tetap
```

### Kondisi penomoran

| # | Kondisi | Perilaku |
|---|---|---|
| K-03 | Belum ada invoice dengan prefiks sama | mulai dari `0001` |
| K-04 | Sudah ada | ambil tertinggi + 1 |
| K-05 | Dua permintaan bersamaan | `lockForUpdate()` di dalam transaksi mencegah nomor kembar |
| K-06 | Ada invoice ter-soft-delete | tetap ikut dihitung (`withTrashed()`), jadi nomor tidak pernah dipakai ulang |

Urutan mengikuti **kapan invoice dibuat**, bukan urutan tour-nya. Tour lama yang baru dibuatkan invoice hari ini akan mendapat nomor terbaru.

Nomor kedua — `finance_number` — belum ada pada tahap ini. Ia baru terbit saat invoice disetujui; lihat [05-tahap-3-persetujuan.md](05-tahap-3-persetujuan.md).

## 1.6 Setelah dibuat

Invoice masuk **Tahap 1 · Proforma** karena `baseline_total` masih 0. Semua nominalnya masih kosong: `unit_price` belum diisi, sehingga `total` juga 0 dan tombol Kunci Patokan dalam keadaan disabled.

Langkah berikutnya ada di [02-tahap-1-proforma.md](02-tahap-1-proforma.md).
