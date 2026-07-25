# Desain — Usulan & Keputusan Arsitektur

Dokumen desain untuk perubahan sistem: masalah, pertimbangan (termasuk keamanan data & risiko), dan keputusan yang dikunci — ditulis sebelum/selama implementasi. Sering berpasangan dengan sebuah plan di [../rencana/](../rencana/README.md).

| Dokumen | Isi | Status |
|---|---|---|
| [pemisahan-invoice-per-jenis.md](pemisahan-invoice-per-jenis.md) | Memberi tiap jenis penjualan (Tour/Hotel/Guide/Transport/MICE/Document/Ticketing) aturan hitung tagihan sendiri lewat kontrak + registry. Termasuk §7 protokol keamanan data untuk migrasi di production. | Fase 0–2 selesai di `dev`; Fase 3–5 belum |
| [integrasi-brevo-email.md](integrasi-brevo-email.md) | Brevo sebagai pipa pengiriman email (quotation/invoice ke customer + digest ke Sales), dibangun bertahap 3 layer. | L1+L2 selesai & teruji lokal; L3 ditunda |
