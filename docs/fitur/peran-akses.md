# Peran & Akses

> **Status:** ✅ Berjalan · **Peran:** admin · **Sejak:** Jun 2026
> **Terkait:** [referensi/roles-permissions.md](../referensi/roles-permissions.md)

## 1. Ringkasan

CRUD akun login sistem (tabel `users`) — nama, email, password, dan salah satu dari 8 role (`admin`, `sales`, `accountant`, `guide`, `driver`, `tour_leader`, `travel_agent`, `operation`) yang ditegakkan lewat middleware `role:` di tiap route dan menentukan halaman utama (`homePath()`) serta menu sidebar masing-masing. Modul Kelola Akun ini sendiri admin-only. Dipakai oleh admin.

## 2. Cara kerja (as-built)

> _Dilengkapi pada batch domain — lihat rencana._

## 3. Keterkaitan

> _Dilengkapi pada batch domain — lihat rencana._

## 4. Batasan & jebakan ⚠️

> _Dilengkapi pada batch domain — lihat rencana._

## 5. Status & yang belum

Berjalan penuh di production; halaman Kelola Akun dirombak & role `operation`/`travel_agent` yang sempat hilang dari daftar sudah diperbaiki (17 Juli 2026).

## 6. Dokumen terkait

> _Dilengkapi pada batch domain — lihat rencana._
