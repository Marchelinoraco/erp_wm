# Dokumentasi ERP Welcome Manado

Peta seluruh dokumentasi proyek. **Mulai dari [ikhtisar-proyek.md](ikhtisar-proyek.md)** untuk gambaran umum sistem, stack, konsep inti, dan status.

## Struktur

| Folder / berkas | Isi |
|---|---|
| **[ikhtisar-proyek.md](ikhtisar-proyek.md)** | Gambaran umum & status proyek — titik masuk utama |
| [`../CHANGELOG.md`](../CHANGELOG.md) | Riwayat perubahan yang terasa oleh pengguna, per tanggal |
| **[design-system/](design-system/README.md)** | *Bagaimana tiap fitur bekerja* — dokumentasi per modul (alur bisnis, model data, route/controller, pola UI), ditulis dari pembacaan kode |
| **[logika-pembuatan-invoice/](logika-pembuatan-invoice/README.md)** | Alur pembuatan invoice dipecah per tahap, tiap kondisi bernomor rujukan dan ditandai ditegakkan di server atau hanya UI |
| **[desain/](desain/README.md)** | Dokumen desain / usulan perubahan arsitektur (sebelum & selama implementasi) |
| **[rencana/](rencana/README.md)** | Rencana implementasi bertahap (task-by-task) untuk fitur/refactor tertentu |
| **[referensi/](referensi/README.md)** | Dokumen acuan yang mencerminkan kondisi kode saat ini (mis. hak akses per role) |

## Bedanya "design-system" vs "desain"

- **design-system/** menjelaskan fitur **sebagaimana berjalan sekarang** (as-built) — untuk memahami sistem yang ada.
- **desain/** berisi **usulan/keputusan arsitektur** untuk perubahan — sering berpasangan dengan sebuah plan di **rencana/**.

## Konvensi

- Dokumen bertahap dikelompokkan dalam folder dengan berkas bernomor (`01-…`, `02-…`) + sebuah `README.md` sebagai indeks.
- Nama berkas memakai **kebab-case** (`nama-seperti-ini.md`).
- Dokumen "as-built" ditulis dari pembacaan kode, bukan spekulasi — kalau kode berubah signifikan, perbarui juga dokumennya.
