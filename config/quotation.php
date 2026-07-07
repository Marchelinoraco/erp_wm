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

    /*
    | Rekening tujuan pembayaran INVOICE — tampil di PDF invoice.
    | ⚠️ WAJIB diisi dengan rekening resmi perusahaan sebelum invoice dikirim ke customer.
    */
    'bank' => [
        [
            'bank'    => 'Bank BCA',
            'account' => '0000000000',           // ← GANTI dengan no. rekening asli
            'name'    => 'PT. Welcome Manado Wisata',
            'swift'   => 'CENAIDJA',              // opsional — tampil di invoice bila diisi
        ],
        // Tambah rekening lain bila perlu, contoh:
        // ['bank' => 'Bank Mandiri', 'account' => '0000000000', 'name' => 'PT. Welcome Manado Wisata'],
    ],

    // Catatan kecil di bawah tabel invoice (di atas info pembayaran).
    'invoice_note' => 'The prices above do not include interbank transfer fees. Interbank transfer fees should be calculated and added separately.',

    'payment_terms' => "Pembayaran dianggap lunas setelah dana diterima penuh di rekening kami.
Mohon cantumkan NOMOR INVOICE pada keterangan/berita transfer.
Bukti transfer dapat dikirimkan via email atau WhatsApp kami.",

    'included' => "Fully air-conditioned transportation according to group size
Private boat to Bunaken Island
Hotel as selected – twin sharing basis (dbl/twin)
Experienced guide & driver
Entrance fees to tourist attractions & parking fees
Meals as per program
Tour services as per program
Mineral water 1 bottle/pax/day
Handling service",

    'excluded' => "Round-trip airfare
Tips for guide & driver
Optional tours
Expenses outside the package (additional food/drinks, etc.)
Porter, airport tax & excess baggage
Personal expenses (laundry, mini bar, room service, etc.)
Travel insurance
Other expenses outside the tour program",

    'child_policy' => "Infants under 2 years old sharing a room with their parents are free of charge.
Children under 12 years old sharing a room with 1 adult are charged 100%.
Children under 12 years old with an extra bed, sharing a room with their parents, are charged 80%.
Children under 12 years old with no extra bed, sharing a room with their parents, are charged 65%.",

    'terms' => "CANCELLATION (based on the date written notice is received):
- 21 days before arrival: 20% of total tour price (GIT)
- 10-20 days before arrival: 50% of total tour price (FIT/GIT)
- 7-10 days before arrival: 85% of total tour price (FIT/GIT)
- Less than 7 days before arrival / no-show: 100% of total tour price

All prices are in Indonesian Rupiah (IDR), nett for agents (non-commissionable). Prices are subject to change with/without prior notice due to monetary fluctuations, fuel price changes & government regulations.
Minor deviations in the tour program may occasionally be necessary, depending on weather, road conditions, flight schedules and room availability.",
];
