<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Tour;
use Illuminate\Database\Seeder;

class ExampleQuotationSeeder extends Seeder
{
    public function run(): void
    {
        $title = '4D3N Manado Highlights Tour (Contoh)';

        // Idempotent: hapus contoh lama bila ada
        Tour::where('title', $title)->get()->each->delete();

        $customer = Customer::firstOrCreate(
            ['name' => 'Bpk. Andi Wijaya'],
            [
                'type'           => 'direct',
                'country'        => 'Indonesia',
                'contact_person' => 'Andi Wijaya',
                'phone'          => '+62 812 3456 7890',
                'email'          => 'andi.wijaya@example.com',
                'notes'          => 'Customer contoh untuk demo quotation.',
            ]
        );

        $tour = Tour::create([
            'customer_id'    => $customer->id,
            'title'          => $title,
            'pax'            => 4,
            'start_date'     => '2026-07-10',
            'end_date'       => '2026-07-13',
            'status'         => 'quotation_sent',
            'sales_person'   => 'Welcome Manado',
            'default_markup' => 0,
            'notes'          => 'Tour & harga dapat berubah tanpa pemberitahuan jika terjadi pengurangan jumlah peserta. Waktu di titik tamasya menyesuaikan kondisi lalu lintas & cuaca.',
            'pricing'        => $this->pricing(),
            'price_validity' => '2027-03-31',
            // Teks customer-facing diisi dari template standar (config/quotation.php)
            'included'       => config('quotation.included'),
            'excluded'       => config('quotation.excluded'),
            'child_policy'   => config('quotation.child_policy'),
            'terms'          => config('quotation.terms'),
        ]);

        // ── Itinerary harian (naratif) ──
        foreach ($this->itineraryDays() as $day) {
            $tour->itineraryDays()->create($day);
        }

        // ── Itinerary jam-ke-jam (contoh Hari 1) ──
        foreach ($this->itineraryHours() as $h) {
            $tour->itineraryHours()->create($h);
        }

        // ── Item internal (costing) — tidak tampil di quotation, untuk profit panel ──
        foreach ($this->items() as $i => $item) {
            $tour->items()->create(array_merge($item, ['sort_order' => $i]));
        }

        $this->command?->info("Contoh quotation dibuat: {$tour->code} — {$tour->title}");
    }

    private function pricing(): array
    {
        $tiers = [
            ['id' => 't1', 'label' => 'Min. 2 pax', 'note' => 'New Avanza'],
            ['id' => 't2', 'label' => 'Min. 4 pax', 'note' => 'Innova Reborn'],
            ['id' => 't3', 'label' => 'Min. 8 pax', 'note' => 'Hiace'],
        ];

        // [nama, tipe kamar, harga t1, t2, t3, single supplement]
        $hotelRows = [
            ['Luwansa Hotel 4*',           'Superior Room',          4599000, 3449000, 2829000, 1075000],
            ['Swiss-Belhotel Maloesan 4*', 'Deluxe Room',            4569000, 3419000, 2799000, 1040000],
            ['Aryaduta Hotel 4*',          'Deluxe Room',            4509000, 3359000, 2739000,  985000],
            ['Best Western Hotel 4*',      'Superior Room',          4489000, 3329000, 2709000,  955000],
            ['Sintesa Peninsula Hotel 4*', 'Superior Room',          4479000, 3319000, 2699000,  945000],
            ['Aston Hotel 4*',             'Superior Room',          4399000, 3249000, 2629000,  870000],
            ['The Sentra Manado 4*',       'Deluxe Room',            4399000, 3249000, 2629000,  870000],
            ["Roger's Hotel 3+*",          'Superior Room',          4369000, 3219000, 2599000,  840000],
            ['Ibis Boulevard 3*',          'Superior Room',          4359000, 3199000, 2579000,  825000],
            ['Manado Quality Hotel 4*',    'Superior Room',          4329000, 3169000, 2549000,  795000],
            ['Whiz Prime Megamas 3*',      'Superior Room (No View)',4309000, 3159000, 2539000,  780000],
            ['Three R Hotel 3*',           'Deluxe Room',            4239000, 3079000, 2459000,  705000],
        ];

        $hotels = [];
        foreach ($hotelRows as $n => $r) {
            $hotels[] = [
                'id'         => 'h' . ($n + 1),
                'name'       => $r[0],
                'room'       => $r[1],
                'prices'     => ['t1' => $r[2], 't2' => $r[3], 't3' => $r[4]],
                'single_sup' => $r[5],
            ];
        }

        return [
            'tiers' => $tiers,
            'base'  => [
                'label'   => 'Tanpa Hotel & Sarapan Pagi',
                'enabled' => true,
                'prices'  => ['t1' => 3579000, 't2' => 2419000, 't3' => 1809000],
            ],
            'hotels'    => $hotels,
            'optionals' => [
                ['label' => '1x Snorkeling', 'price' => 150000, 'note' => 'termasuk mask, snorkel & fins'],
                ['label' => '1x Diving',     'price' => 850000, 'note' => 'termasuk mask, fins, wetsuit, tank, weight, regulator & BCD'],
            ],
        ];
    }

    private function itineraryDays(): array
    {
        return [
            [
                'day_number' => 1,
                'title'      => 'Arrival – Manado City Tour – Makatete Hill – Sun Bae Manado (-/-/D)',
                'description' => 'Setibanya di Bandara Sam Ratulangi Manado, seluruh peserta tour dijemput oleh pemandu wisata dan langsung memulai City Tour: pusat kota Manado, Kawasan Pecinan, Klenteng Ban Hin Kiong (klenteng tertua di Manado, ±300 tahun), melewati Gereja Sentrum & Monumen Perang Dunia II. Dilanjutkan ke kawasan Ring Road Citra Land untuk melihat Monumen Yesus Memberkati. Kemudian menuju Makatete Hill (200 mdpl) dengan pemandangan kota Manado, pulau Bunaken & Siladen, serta gunung Klabat. Makan malam di lokal restoran, lalu menikmati suasana malam tepi laut di SunBae (optional). Setelahnya diantar ke hotel untuk check-in & beristirahat.',
            ],
            [
                'day_number' => 2,
                'title'      => 'Bunaken Island Sightseeing – Produksi Kopi Lokal – Shopping Tour (B/L/D)',
                'description' => 'Setelah sarapan, peserta dijemput menuju dermaga Megamas lalu menyeberang ke Pulau Bunaken (±30 menit) melewati Dermaga Wisata Bunaken (New Bunaken). Menikmati keindahan Taman Laut Bunaken dengan snorkeling/diving (optional) atau bersantai di pantai. Makan siang di lokal restoran, dilanjutkan waktu bebas untuk snorkeling/diving (optional). Kembali ke Megamas, mengunjungi tempat produksi kopi lokal & mencicipi cita rasa khas Sulawesi Utara, lalu Shopping Tour oleh-oleh khas Manado. Makan malam di lokal restoran, kembali ke hotel.',
            ],
            [
                'day_number' => 3,
                'title'      => 'Minahasa Highland Tour (B/L/D)',
                'description' => 'Setelah sarapan, perjalanan ke Dataran Tinggi Minahasa melewati Tinoor (pemandangan kota Manado dari ketinggian 500 m). Di Tomohon: Taman Wisata Pelangi, Pasar Ekstrem Tomohon, dan live cooking kue basah khas Manado. Mengunjungi hutan aren dengan spot foto di Tuur Maasering serta proses pembuatan ‘Cap Tikus’. Makan siang di tepi Danau Tondano. Dilanjutkan ke Bukit Kasih (kawah belerang & kolam air panas alami – optional), Danau Linow (danau sulfur yang berubah warna, disuguhkan kopi/teh), dan desa Woloan (rumah adat Minahasa knock down). Kembali ke Manado, makan malam, lalu ke hotel.',
            ],
            [
                'day_number' => 4,
                'title'      => 'Transfer Out – Departure (B/-/-)',
                'description' => 'Setelah sarapan pagi & proses check-out hotel selesai, peserta dijemput dan diantar ke Bandara Sam Ratulangi untuk penerbangan kembali. Tour selesai.',
            ],
        ];
    }

    private function itineraryHours(): array
    {
        return [
            ['day_number' => 1, 'start_time' => '13:00', 'end_time' => '14:00', 'activity' => 'Penjemputan di Bandara Sam Ratulangi', 'notes' => 'Meeting point pintu kedatangan'],
            ['day_number' => 1, 'start_time' => '14:00', 'end_time' => '16:30', 'activity' => 'Manado City Tour (Klenteng, Gereja Sentrum, Monumen Yesus)', 'notes' => null],
            ['day_number' => 1, 'start_time' => '16:30', 'end_time' => '18:00', 'activity' => 'Makatete Hill – sunset view', 'notes' => null],
            ['day_number' => 1, 'start_time' => '18:30', 'end_time' => '20:00', 'activity' => 'Makan malam + SunBae Manado (optional)', 'notes' => null],
            ['day_number' => 1, 'start_time' => '20:00', 'end_time' => '20:30', 'activity' => 'Check-in hotel & istirahat', 'notes' => null],
        ];
    }

    private function items(): array
    {
        // Costing internal untuk 4 pax (Innova). unit_cost = modal, unit_sell = jual.
        return [
            ['product_type' => 'hotel',      'description' => 'Aston Hotel 4* – Superior (3 malam, 2 kamar)', 'day_number' => 1, 'qty' => 2, 'nights' => 3, 'unit_cost' => 450000,  'unit_sell' => 620000,  'currency' => 'IDR'],
            ['product_type' => 'transport',  'description' => 'Innova Reborn + driver (4 hari)',             'day_number' => 1, 'qty' => 1, 'nights' => 4, 'unit_cost' => 700000,  'unit_sell' => 950000,  'currency' => 'IDR'],
            ['product_type' => 'guide',      'description' => 'Tour Guide berpengalaman (4 hari)',          'day_number' => 1, 'qty' => 1, 'nights' => 4, 'unit_cost' => 350000,  'unit_sell' => 500000,  'currency' => 'IDR'],
            ['product_type' => 'attraction', 'description' => 'Private Boat Pulau Bunaken (PP)',            'day_number' => 2, 'qty' => 1, 'nights' => 1, 'unit_cost' => 1200000, 'unit_sell' => 1600000, 'currency' => 'IDR'],
            ['product_type' => 'restaurant', 'description' => 'Paket makan sesuai program (per pax)',        'day_number' => 1, 'qty' => 4, 'nights' => 1, 'unit_cost' => 450000,  'unit_sell' => 650000,  'currency' => 'IDR'],
            ['product_type' => 'attraction', 'description' => 'Tiket masuk objek wisata & parkir (per pax)', 'day_number' => 1, 'qty' => 4, 'nights' => 1, 'unit_cost' => 200000,  'unit_sell' => 320000,  'currency' => 'IDR'],
        ];
    }
}
