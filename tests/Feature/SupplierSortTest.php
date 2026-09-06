<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierSortTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): User
    {
        return User::create([
            'name'     => 'Admin Sort',
            'email'    => 'admin.sort@test.local',
            'password' => bcrypt('password'),
            'role'     => 'admin',
        ]);
    }

    private function makeSupplier(string $name, array $attrs = []): Supplier
    {
        return Supplier::create(array_merge(['name' => $name], $attrs));
    }

    /** @return array<int,string> nama supplier sesuai urutan yang dikirim ke halaman */
    private function namesFrom($response): array
    {
        return array_column($response->viewData('page')['props']['suppliers']['data'], 'name');
    }

    public function test_sortir_kolom_nama_a_z(): void
    {
        $this->makeSupplier('Cakra Trans');
        $this->makeSupplier('Andi Tour');
        $this->makeSupplier('Bunaken Dive');

        $response = $this->actingAs($this->makeAdmin())
            ->get(route('suppliers.index', ['sort' => 'name', 'dir' => 'asc']));

        $this->assertSame(
            ['Andi Tour', 'Bunaken Dive', 'Cakra Trans'],
            $this->namesFrom($response),
        );
    }

    public function test_sortir_kolom_nama_z_a(): void
    {
        $this->makeSupplier('Cakra Trans');
        $this->makeSupplier('Andi Tour');
        $this->makeSupplier('Bunaken Dive');

        $response = $this->actingAs($this->makeAdmin())
            ->get(route('suppliers.index', ['sort' => 'name', 'dir' => 'desc']));

        $this->assertSame(
            ['Cakra Trans', 'Bunaken Dive', 'Andi Tour'],
            $this->namesFrom($response),
        );
    }

    public function test_sortir_kolom_tipe_mengikuti_urutan_label_di_layar(): void
    {
        $this->makeSupplier('Si Transport', ['type' => 'transport']);
        $this->makeSupplier('Si Lainnya', ['type' => 'other']);       // label "Lainnya"
        $this->makeSupplier('Si Attraction', ['type' => 'attraction']);
        $this->makeSupplier('Si Hotel', ['type' => 'hotel']);

        $response = $this->actingAs($this->makeAdmin())
            ->get(route('suppliers.index', ['sort' => 'type', 'dir' => 'asc']));

        // Attraction, Hotel, Lainnya, Transport — urut sesuai label yang dilihat user
        $this->assertSame(
            ['Si Attraction', 'Si Hotel', 'Si Lainnya', 'Si Transport'],
            $this->namesFrom($response),
        );
    }

    public function test_sortir_kolom_produk_dari_terbanyak(): void
    {
        $sepi   = $this->makeSupplier('Supplier Sepi');
        $ramai  = $this->makeSupplier('Supplier Ramai');
        $sedang = $this->makeSupplier('Supplier Sedang');

        foreach ([$ramai, $ramai, $ramai, $sedang] as $i => $supplier) {
            Product::create([
                'name'        => 'Produk ' . $i,
                'type'        => 'hotel',
                'supplier_id' => $supplier->id,
            ]);
        }

        $response = $this->actingAs($this->makeAdmin())
            ->get(route('suppliers.index', ['sort' => 'products_count', 'dir' => 'desc']));

        $this->assertSame(
            ['Supplier Ramai', 'Supplier Sedang', 'Supplier Sepi'],
            $this->namesFrom($response),
        );
        $this->assertSame($sepi->name, 'Supplier Sepi');
    }

    public function test_kontak_kosong_ditaruh_di_bawah_saat_a_z(): void
    {
        $this->makeSupplier('Punya Kontak B', ['contact_person' => 'Budi']);
        $this->makeSupplier('Tanpa Kontak', ['contact_person' => null]);
        $this->makeSupplier('Punya Kontak A', ['contact_person' => 'Anton']);

        $response = $this->actingAs($this->makeAdmin())
            ->get(route('suppliers.index', ['sort' => 'contact_person', 'dir' => 'asc']));

        $this->assertSame(
            ['Punya Kontak A', 'Punya Kontak B', 'Tanpa Kontak'],
            $this->namesFrom($response),
        );
    }

    public function test_kontak_kosong_tetap_di_bawah_saat_z_a(): void
    {
        $this->makeSupplier('Punya Kontak B', ['contact_person' => 'Budi']);
        $this->makeSupplier('Tanpa Kontak', ['contact_person' => null]);
        $this->makeSupplier('Punya Kontak A', ['contact_person' => 'Anton']);

        $response = $this->actingAs($this->makeAdmin())
            ->get(route('suppliers.index', ['sort' => 'contact_person', 'dir' => 'desc']));

        $this->assertSame(
            ['Punya Kontak B', 'Punya Kontak A', 'Tanpa Kontak'],
            $this->namesFrom($response),
        );
    }

    public function test_kolom_di_luar_daftar_izin_diabaikan(): void
    {
        $this->makeSupplier('Supplier Satu');
        $this->makeSupplier('Supplier Dua');

        $response = $this->actingAs($this->makeAdmin())
            ->get(route('suppliers.index', ['sort' => 'notes', 'dir' => 'asc']));

        $response->assertOk();
        $this->assertCount(2, $this->namesFrom($response));
    }

    public function test_sortir_tetap_menghormati_filter_pencarian(): void
    {
        $this->makeSupplier('Tangkoko Ranger');
        $this->makeSupplier('Tangkoko Ent. Fee');
        $this->makeSupplier('Bunaken Dive');

        $response = $this->actingAs($this->makeAdmin())
            ->get(route('suppliers.index', ['search' => 'Tangkoko', 'sort' => 'name', 'dir' => 'asc']));

        $this->assertSame(
            ['Tangkoko Ent. Fee', 'Tangkoko Ranger'],
            $this->namesFrom($response),
        );
    }

    public function test_sortir_aktif_dikirim_balik_ke_halaman(): void
    {
        $this->makeSupplier('Supplier Satu');

        $this->actingAs($this->makeAdmin())
            ->get(route('suppliers.index', ['sort' => 'name', 'dir' => 'desc']))
            ->assertInertia(fn ($page) => $page
                ->where('filters.sort', 'name')
                ->where('filters.dir', 'desc'));
    }
}
