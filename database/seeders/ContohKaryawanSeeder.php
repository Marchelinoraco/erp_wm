<?php

namespace Database\Seeders;

use App\Models\Employee;
use Illuminate\Database\Seeder;

/**
 * Data contoh untuk uji coba fitur Master Karyawan/Kas Bon/Gajian di dev-erp
 * (database salinan production). HANYA untuk lingkungan dev — jangan pernah
 * dijalankan di production.
 *
 * Idempotent: karyawan lama bertanda "(Contoh)" dihapus dulu sebelum dibuat
 * ulang, supaya seeder ini aman dijalankan berkali-kali tanpa duplikat.
 */
class ContohKaryawanSeeder extends Seeder
{
    public function run(): void
    {
        Employee::where('name', 'like', '%(Contoh)')->get()->each->delete();

        $budi = Employee::create([
            'name'         => 'Budi Santoso (Contoh)',
            'position'     => 'Driver',
            'phone'        => '081234560001',
            'email'        => 'budi.driver@example.com',
            'base_salary'  => 4_000_000,
            'join_date'    => '2024-03-01',
            'bank_name'    => 'BCA',
            'bank_account_number' => '1234567890',
            'bank_account_holder' => 'Budi Santoso',
        ]);
        $budi->components()->createMany([
            ['name' => 'Tunjangan Makan',     'type' => 'tunjangan', 'amount' => 500_000],
            ['name' => 'Tunjangan Transport',  'type' => 'tunjangan', 'amount' => 300_000],
        ]);

        $siti = Employee::create([
            'name'        => 'Siti Rahma (Contoh)',
            'position'    => 'Tour Guide',
            'phone'       => '081234560002',
            'email'       => 'siti.guide@example.com',
            'base_salary' => 3_500_000,
            'join_date'   => '2024-06-15',
            'bank_name'   => 'Mandiri',
            'bank_account_number' => '2233445566',
            'bank_account_holder' => 'Siti Rahma',
        ]);
        $siti->components()->create([
            'name' => 'Tunjangan Komunikasi', 'type' => 'tunjangan', 'amount' => 200_000,
        ]);

        $andi = Employee::create([
            'name'        => 'Andi Wijaya (Contoh)',
            'position'    => 'Staff Operasional',
            'phone'       => '081234560003',
            'email'       => 'andi.ops@example.com',
            'base_salary' => 3_800_000,
            'join_date'   => '2025-01-10',
            'bank_name'   => 'BNI',
            'bank_account_number' => '9988776655',
            'bank_account_holder' => 'Andi Wijaya',
        ]);
        $andi->components()->createMany([
            ['name' => 'Tunjangan Makan', 'type' => 'tunjangan', 'amount' => 500_000],
            ['name' => 'Potongan Terlambat (contoh prorata)', 'type' => 'potongan', 'amount' => 150_000],
        ]);

        // Tanpa komponen tetap sama sekali — kasus paling sederhana untuk uji.
        Employee::create([
            'name'        => 'Maria Kaunang (Contoh)',
            'position'    => 'Admin Kantor',
            'phone'       => '081234560004',
            'email'       => 'maria.admin@example.com',
            'base_salary' => 4_500_000,
            'join_date'   => '2023-11-01',
        ]);

        $this->command?->info('4 karyawan contoh dibuat: Budi (2 tunjangan), Siti (1 tunjangan), Andi (1 tunjangan + 1 potongan), Maria (tanpa komponen).');
        $this->command?->info('Belum ada kas bon — beri kas bon lewat layar Kas Bon untuk menguji potongan otomatis saat gajian.');
    }
}
