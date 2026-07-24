# Logika Pembuatan Invoice → pindah ke folder

Dokumen ini sudah digantikan versi terpecah yang jauh lebih rinci:

**→ [`docs/logika-pembuatan-invoice/`](logika-pembuatan-invoice/README.md)**

Isinya sama, tetapi dipecah per tahap dengan setiap kondisi diberi nomor rujukan (K-01 s/d K-122) dan ditandai apakah ditegakkan di server, di UI saja, atau sekadar nilai turunan.

| # | Dokumen | Isi |
|---|---|---|
| — | [Peta & ringkasan](logika-pembuatan-invoice/README.md) | Diagram alur, tiga hal yang sering disalahpahami |
| 01 | [Prasyarat & Pembuatan](logika-pembuatan-invoice/01-prasyarat-dan-pembuatan.md) | Kapan invoice boleh dibuat, nilai awal, penomoran |
| 02 | [Tahap 1 — Proforma](logika-pembuatan-invoice/02-tahap-1-proforma.md) | Mata uang, harga/pax, deskripsi, rekening bank |
| 03 | [Tahap 2 — Patokan](logika-pembuatan-invoice/03-tahap-2-patokan.md) | Kunci patokan, `baselineMatched` |
| 04 | [Rincian Profit](logika-pembuatan-invoice/04-rincian-profit.md) | Item internal, tanggal wajib, tempel massal, autosave |
| 05 | [Tahap 3 — Persetujuan](logika-pembuatan-invoice/05-tahap-3-persetujuan.md) | Gerbang ke Keuangan, kurs, efek samping |
| 06 | [Penguncian](logika-pembuatan-invoice/06-penguncian-setelah-disetujui.md) | Matriks izin setelah disetujui |
| 07 | [Pembayaran](logika-pembuatan-invoice/07-pembayaran.md) | DP, pelunasan, transisi status |
| 08 | [Perbedaan per Tipe](logika-pembuatan-invoice/08-perbedaan-per-tipe.md) | Yang berbeda dan yang ternyata sama |
| 09 | [Matriks Kondisi](logika-pembuatan-invoice/09-matriks-kondisi.md) | Tabel rujukan 93 kondisi |
| 10 | [Temuan](logika-pembuatan-invoice/10-temuan.md) | Celah dan perilaku yang perlu diketahui |

Berkas ini dipertahankan sebagai penunjuk arah agar tautan lama tidak putus, dan boleh dihapus kapan saja.
