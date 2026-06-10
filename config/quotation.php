<?php

/*
|--------------------------------------------------------------------------
| Default teks Quotation — Welcome Manado
|--------------------------------------------------------------------------
| Dipakai sebagai nilai awal di editor Tours/Edit.vue dan sebagai fallback
| di PDF (quotation.blade.php) bila field per-tour kosong. Satu item per baris.
*/

return [
    'company' => [
        'legal_name' => 'PT. WELCOME MANADO WISATA',
        'brand'      => 'Welcome Manado',
        'tagline'    => 'Tour & Travel',
        'address'    => 'Grha Merdeka – Jl. AA. Maramis No. 17, Kairagi Dua, Manado – Indonesia',
        'phone'      => '+62 896 0100 6424, +62 821 7373 8822',
        'email'      => 'info@welcomemanado.com, tour.welcomemanado@gmail.com',
        'website'    => 'welcomemanado.com',
    ],

    'included' => "Transportasi Full AC sesuai jumlah peserta
Private Boat ke Pulau Bunaken
Hotel sesuai pilihan – sekamar berdua (dbl/twin)
Guide & driver berpengalaman
Tiket masuk objek wisata & biaya parkir
Pelayanan makan sesuai program
Pelayanan tour sesuai program
Air mineral 1 botol/pax/hari
Handling service",

    'excluded' => "Tiket pesawat PP
Tips guide & driver
Optional tour
Biaya diluar paket (tambahan makan/minum, dll)
Porter, airport tax & kelebihan bagasi
Biaya pribadi (laundry, mini bar, layanan kamar, dll)
Asuransi perjalanan
Biaya-biaya lainnya diluar program tour",

    'child_policy' => "Bayi berusia di bawah 2 tahun yang berbagi kamar dengan orang tuanya tidak dikenakan biaya.
Anak berusia di bawah 12 tahun yang berbagi kamar yang sama dengan 1 Dewasa dikenakan biaya 100%.
Anak berusia di bawah 12 tahun dengan tempat tidur tambahan dan berbagi kamar dengan orang tuanya dikenakan biaya sebesar 80%.
Anak di bawah 12 tahun No extra bed & berbagi kamar dengan orang tuanya dikenakan biaya sebesar 65%.",

    'terms' => "PEMBATALAN (sesuai hari diterimanya pemberitahuan resmi tertulis):
- 21 hari sebelum kedatangan: 20% dari total harga tour (GIT)
- 10-20 hari sebelum kedatangan: 50% dari total harga tour (FIT/GIT)
- 7-10 hari sebelum kedatangan: 85% dari total harga tour (FIT/GIT)
- Kurang dari 7 hari sebelum kedatangan / no-show: 100% dari total harga tour

Semua harga dalam Rupiah (IDR), Nett untuk Agen (Non Komisi). Harga dapat berubah dengan/tanpa pemberitahuan karena fluktuasi moneter, perubahan harga BBM & peraturan pemerintah.
Penyimpangan kecil dalam program tour terkadang diperlukan, tergantung cuaca, kondisi jalan, jadwal penerbangan dan ketersediaan kamar.",
];
