<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

/**
 * Import data dari website welcomemanado.com (dbwm3juni2026.sql)
 *
 * Hotels  → suppliers (type: hotel). Produk per kamar ditambah manual lewat UI.
 * Transport → suppliers (type: transport) + products (type: transport, cost=0 dulu).
 */
class WelcomeMaadoImportSeeder extends Seeder
{
    public function run(): void
    {
        $this->importHotels();
        $this->importTransport();
    }

    // ─── Hotels ──────────────────────────────────────────────────────────────

    private function importHotels(): void
    {
        $hotels = [
            ['name' => 'Manado Tateli Beach Resort',                      'notes' => 'Jalan Raya Tanawangko, Tateli Satu | resort'],
            ['name' => 'Sintesa Peninsula Hotel Manado',                  'notes' => 'Jl. Jend Sudirman, Gunung Wenang | city_hotel'],
            ['name' => 'Swiss-Belhotel Manado',                           'notes' => 'Jl. Jendral Sudirman No.85-87 | city_hotel'],
            ['name' => 'Manado Quality Hotel',                            'notes' => 'Jl. Piere Tendean No.88-89 | city_hotel'],
            ['name' => 'Novotel Manado Golf Resort & Convention Center',  'notes' => 'Grand Kawanua City | resort'],
            ['name' => 'Grand Whiz Manado',                               'notes' => 'Megamas Area | city_hotel'],
            ['name' => 'Gran Puri Hotel Manado',                          'notes' => 'Manado | city_hotel'],
            ['name' => 'Lion Hotel & Plaza Manado',                       'notes' => 'Manado | city_hotel'],
            ['name' => 'Luwansa Hotel & Convention Center Manado',        'notes' => 'Manado | city_hotel'],
            ['name' => 'Siladen Resort & Spa',                            'notes' => 'Siladen Island | resort'],
            ['name' => 'Yama Resort Tondano',                             'notes' => 'Tondano | resort'],
            ['name' => 'Grand Master Villa Tomohon',                      'notes' => 'Tomohon | resort'],
            ['name' => 'Jhoanie Hotel Tomohon',                           'notes' => 'Tomohon | city_hotel'],
            ['name' => 'Villa Emitta Hotel Tomohon',                      'notes' => 'Tomohon | boutique'],
            ['name' => 'Cocotinos Manado',                                'notes' => 'Manado | resort'],
            ['name' => 'Ibis Hotel Manado',                               'notes' => 'Jl. Pierre Tendean Boulevard No.85 | city_hotel'],
            ['name' => 'Casa de Wanea Hotel & Resto',                     'notes' => 'Jl. Sam Ratulangi No. 90-92 | city_hotel'],
            ['name' => 'Genio Hotel Manado',                              'notes' => 'Manado | city_hotel'],
            ['name' => 'Whiz Prime Hotel Manado',                         'notes' => 'Manado | city_hotel'],
            ['name' => 'Formosa Hotel Manado',                            'notes' => 'Manado | city_hotel'],
            ['name' => 'Bobocabin Bunaken Hills',                         'notes' => 'Jl. Molas-Tongkaina | resort'],
            ['name' => 'Aston Manado Hotel',                              'notes' => 'Manado | city_hotel'],
            ['name' => 'Top Hotel Manado by Gran Puri',                   'notes' => 'Manado | city_hotel'],
            ['name' => 'Aryaduta Hotel Manado',                           'notes' => 'Manado | city_hotel'],
            ['name' => 'Victoria Inn',                                     'notes' => 'Manado | city_hotel'],
            ["name" => "Mel's Inn Manado",                                 'notes' => 'Manado | city_hotel'],
            ['name' => 'Highland Resort & Nature Tours',                  'notes' => 'Tomohon | resort'],
            ['name' => 'Travello Hotel Manado',                           'notes' => 'Manado | city_hotel'],
            ["name" => "Jle's Hotel Manado",                              'notes' => 'Jalan Walanda Maramis | city_hotel'],
            ['name' => 'THREE R HOTEL',                                   'notes' => 'Jl Pangeran Hidayat No 19 | city_hotel'],
            ['name' => 'Sutan Raja Manado Hotel and Convention Centre',   'notes' => 'Jl. Raya Manado Bitung, Watutumou II | city_hotel'],
            ['name' => 'The Sentra Manado',                               'notes' => 'Jalan Ir. Soekarno | city_hotel'],
            ['name' => 'Hotel Gran Central',                              'notes' => 'Jl. Jendral Sudirman No.45 | city_hotel'],
            ['name' => 'Paradise Hotel Golf & Resort',                    'notes' => 'Likupang | resort'],
            ['name' => 'Kuda Laut Boutique Dive Resort',                  'notes' => 'Siladen Island | resort'],
            ['name' => 'Bunaken Oasis Dive Resort and Spa',               'notes' => 'Liang Beach, Bunaken | resort'],
            ['name' => 'PS Bunaken Resort and Dive',                      'notes' => 'Bunaken | resort'],
            ['name' => 'Bastianos Bunaken Dive Resort',                   'notes' => 'Bunaken | resort'],
            ['name' => 'Tangkoko Sanctuary Villa',                        'notes' => 'Batu Putih Bawah, Bitung Utara | eco_lodge'],
            ['name' => 'Botanica Nature Resort',                          'notes' => 'Danowudu, Bitung | resort'],
            ['name' => 'Fave Hotel Bitung',                               'notes' => 'Bitung | city_hotel'],
            ['name' => 'Lun Hotel Manado',                                'notes' => 'Manado | city_hotel'],
            ['name' => 'Uma Villa Manado',                                'notes' => 'Manado | boutique'],
            ['name' => 'De Ggii Hotel by Flip Inn Hotel',                 'notes' => 'Jl. Temboan No.5, Winangun Dua | city_hotel'],
            ['name' => 'Biz Boulevard Hotel',                             'notes' => 'Manado | city_hotel'],
            ['name' => 'Maleosan Inn Hotel',                              'notes' => 'Tikala Kumaraka, Wenang | city_hotel'],
            ['name' => 'Green Eden Hotel',                                'notes' => 'Manado | city_hotel'],
            ['name' => 'Arcadia Hotel',                                   'notes' => 'Jalan Pramuka No 74-76 | city_hotel'],
            ['name' => 'Istanaku Hotel',                                  'notes' => 'Jl. W.R. Supratman, Lawangirung | city_hotel'],
            ["name" => "Avon's Residence",                                'notes' => 'Jl. 17 Agustus, Titiwungen | city_hotel'],
            ['name' => 'Coral Eye Boutique Resort and Marine Outpost',    'notes' => 'Pulau Bangka, Desa Lihunu | resort'],
            ['name' => 'Tangkoko Lodge',                                  'notes' => 'Batu Putih | eco_lodge'],
            ['name' => 'Tangkoko Hill',                                   'notes' => 'Batu Putih | eco_lodge'],
        ];

        foreach ($hotels as $hotel) {
            Supplier::firstOrCreate(
                ['name' => $hotel['name']],
                ['type' => 'hotel', 'notes' => $hotel['notes']]
            );
        }

        $this->command->info('✓ ' . count($hotels) . ' hotel diimport sebagai supplier.');
    }

    // ─── Transportasi ─────────────────────────────────────────────────────────

    private function importTransport(): void
    {
        $vehicles = [
            ['name' => 'Toyota Avanza',          'capacity' => '7 pax',   'unit' => 'per_unit'],
            ['name' => 'Toyota Innova Reborn',   'capacity' => '7 pax',   'unit' => 'per_unit'],
            ['name' => 'Toyota Innova Zenix',    'capacity' => '7 pax',   'unit' => 'per_unit'],
            ['name' => 'Hiace Commuter',         'capacity' => '14 pax',  'unit' => 'per_unit'],
            ['name' => 'BUS 30/33 Seater',       'capacity' => '33 pax',  'unit' => 'per_unit'],
            ['name' => 'Toyota Alphard',         'capacity' => '7 pax',   'unit' => 'per_unit'],
        ];

        foreach ($vehicles as $v) {
            // Buat/cari supplier
            $supplier = Supplier::firstOrCreate(
                ['name' => $v['name']],
                ['type' => 'transport', 'notes' => 'Kapasitas: ' . $v['capacity']]
            );

            // Buat produk transport (cost=0 dulu)
            Product::firstOrCreate(
                ['name' => $v['name'], 'type' => 'transport'],
                [
                    'supplier_id' => $supplier->id,
                    'unit'        => $v['unit'],
                    'cost'        => 0,
                    'sell'        => 0,
                    'currency'    => 'IDR',
                    'is_active'   => true,
                    'notes'       => 'Kapasitas: ' . $v['capacity'] . '. Import dari data website.',
                ]
            );
        }

        $this->command->info('✓ ' . count($vehicles) . ' kendaraan diimport sebagai supplier + produk.');
    }
}
