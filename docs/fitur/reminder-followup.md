# Reminder & Follow-up

> **Status:** ✅ Berjalan · **Peran:** admin, sales · **Sejak:** Jun 2026 (otomatis: Jul 2026)
> **Terkait:** [rencana/tour-ownership.md](../rencana/tour-ownership.md)

## 1. Ringkasan

Pengingat follow-up per akun (biasanya sales), baik dibuat manual (tugas bertanggal, opsional ditautkan ke satu tour yang masih aktif di pipeline) maupun otomatis — reminder H+1 dibuat saat inquiry baru masuk dan berantai setiap kali status tour berubah, berhenti begitu tour mencapai confirmed atau cancelled. Setiap user hanya melihat & mengelola reminder miliknya sendiri. Dipakai oleh admin & sales.

## 2. Cara kerja (as-built)

> _Dilengkapi pada batch domain — lihat rencana._

## 3. Keterkaitan

> _Dilengkapi pada batch domain — lihat rencana._

## 4. Batasan & jebakan ⚠️

> _Dilengkapi pada batch domain — lihat rencana._

## 5. Status & yang belum

Reminder manual berjalan sejak fondasi MVP. Reminder otomatis berantai (H+1, mengikuti status tour) selesai & terverifikasi di kode (18 Juli 2026), tapi migrasi `tours.created_by` yang mendasari kepemilikan tour **belum dijalankan di production** — lihat [rencana/tour-ownership.md](../rencana/tour-ownership.md).

## 6. Dokumen terkait

> _Dilengkapi pada batch domain — lihat rencana._
