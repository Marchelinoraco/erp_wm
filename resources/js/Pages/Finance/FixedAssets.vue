<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { fmtRp } from '@/lib/fmt'

const props = defineProps({
    assets:       Array,
    categories:   Object,
    fiscalGroups: Object,
    currentYear:  Number,
    totals:       Object,
})

// ── Tambah Aset ───────────────────────────────────────────────────────────────
const showAddForm = ref(false)
const addForm = useForm({
    name:              '',
    category:          'vehicle',
    acquisition_date:  '',
    acquisition_cost:  '',
    useful_life_years: 8,
    residual_value:    '',
    fiscal_group:      '',
    notes:             '',
})

function submitAdd() {
    addForm.post(route('fixed-assets.store'), {
        preserveScroll: true,
        onSuccess: () => { showAddForm.value = false; addForm.reset() },
    })
}

// ── Edit Aset ─────────────────────────────────────────────────────────────────
const editingId = ref(null)
const editForm  = useForm({
    name:              '',
    category:          '',
    acquisition_date:  '',
    acquisition_cost:  '',
    useful_life_years: 8,
    residual_value:    '',
    fiscal_group:      '',
    notes:             '',
    is_active:         true,
})

function startEdit(a) {
    editingId.value          = a.id
    editForm.name            = a.name
    editForm.category        = a.category
    editForm.acquisition_date = a.acquisition_date?.substring(0, 10) ?? ''
    editForm.acquisition_cost = a.acquisition_cost
    editForm.useful_life_years = a.useful_life_years
    editForm.residual_value  = a.residual_value
    editForm.fiscal_group    = a.fiscal_group ?? ''
    editForm.notes           = a.notes ?? ''
    editForm.is_active       = a.is_active
}

function submitEdit(id) {
    editForm.patch(route('fixed-assets.update', id), {
        preserveScroll: true,
        onSuccess: () => { editingId.value = null },
    })
}

function deleteAsset(a) {
    if (a.is_active) {
        alert('Nonaktifkan aset terlebih dahulu sebelum menghapus.')
        return
    }
    if (confirm(`Hapus aset "${a.name}"?`)) {
        router.delete(route('fixed-assets.destroy', a.id), { preserveScroll: true })
    }
}

const CAT_COLORS = {
    vehicle:   'bg-blue-100 text-blue-700',
    equipment: 'bg-purple-100 text-purple-700',
    building:  'bg-amber-100 text-amber-700',
    other:     'bg-gray-100 text-gray-600',
}

function depreciationYears(a) {
    if (!a.acquisition_date) return '—'
    const acqYear  = parseInt(a.acquisition_date.substring(0, 4))
    const endYear  = acqYear + a.useful_life_years - 1
    return `${acqYear}–${endYear}`
}
</script>

<template>
    <Head title="Aset Tetap" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-base font-semibold text-gray-800">Aset Tetap &amp; Penyusutan</h1>
                <Button size="sm" @click="showAddForm = !showAddForm">
                    {{ showAddForm ? '✕ Tutup' : '+ Tambah Aset' }}
                </Button>
            </div>
        </template>

        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">

            <!-- Summary cards -->
            <div class="grid grid-cols-3 gap-4">
                <div class="bg-white rounded-xl border shadow-sm p-4">
                    <p class="text-xs text-gray-500 mb-1">Total Harga Perolehan</p>
                    <p class="text-lg font-bold font-mono text-blue-800">{{ fmtRp(totals.cost) }}</p>
                </div>
                <div class="bg-white rounded-xl border shadow-sm p-4">
                    <p class="text-xs text-gray-500 mb-1">Akumulasi Penyusutan</p>
                    <p class="text-lg font-bold font-mono text-red-700">{{ fmtRp(totals.accumulated) }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">s/d {{ currentYear }}</p>
                </div>
                <div class="bg-emerald-50 rounded-xl border border-emerald-200 shadow-sm p-4">
                    <p class="text-xs text-gray-500 mb-1">Nilai Buku Bersih</p>
                    <p class="text-lg font-bold font-mono text-emerald-700">{{ fmtRp(totals.book_value) }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">per 31 Des {{ currentYear }}</p>
                </div>
            </div>

            <!-- Form tambah aset -->
            <div v-if="showAddForm" class="bg-white rounded-xl border shadow-sm p-5 space-y-4">
                <h2 class="text-sm font-bold text-gray-700">Tambah Aset Tetap</h2>
                <form @submit.prevent="submitAdd" class="space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <Label>Nama Aset <span class="text-destructive">*</span></Label>
                            <Input v-model="addForm.name" placeholder="Mis. Toyota Innova Zenix" />
                            <p v-if="addForm.errors.name" class="text-xs text-destructive">{{ addForm.errors.name }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Kategori <span class="text-destructive">*</span></Label>
                            <select v-model="addForm.category"
                                class="w-full h-9 rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring">
                                <option v-for="(label, key) in categories" :key="key" :value="key">{{ label }}</option>
                            </select>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Tanggal Perolehan <span class="text-destructive">*</span></Label>
                            <Input type="date" v-model="addForm.acquisition_date" />
                            <p v-if="addForm.errors.acquisition_date" class="text-xs text-destructive">{{ addForm.errors.acquisition_date }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Harga Perolehan (IDR) <span class="text-destructive">*</span></Label>
                            <Input type="number" v-model.number="addForm.acquisition_cost" min="0" step="1000" placeholder="0" />
                            <p v-if="addForm.errors.acquisition_cost" class="text-xs text-destructive">{{ addForm.errors.acquisition_cost }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Masa Manfaat (tahun) <span class="text-destructive">*</span></Label>
                            <Input type="number" v-model.number="addForm.useful_life_years" min="1" max="50" />
                            <p class="text-xs text-muted-foreground">Kendaraan: 4–8 th · Peralatan: 4–8 th · Bangunan: 20 th</p>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Nilai Sisa / Scrap Value</Label>
                            <Input type="number" v-model.number="addForm.residual_value" min="0" step="1000" placeholder="0 (default)" />
                        </div>
                        <div class="space-y-1.5">
                            <Label>Kelompok Fiskal (PMK 96/2009)</Label>
                            <select v-model="addForm.fiscal_group"
                                class="w-full h-9 rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring">
                                <option value="">— Belum ditentukan —</option>
                                <option v-for="(grp, key) in fiscalGroups" :key="key" :value="key">{{ grp.label }}</option>
                            </select>
                            <p class="text-xs text-muted-foreground">Kendaraan angkutan → Kel. 2 (8 thn). Diperlukan untuk Koreksi Fiskal.</p>
                        </div>
                        <div class="col-span-2 space-y-1.5">
                            <Label>Catatan</Label>
                            <Input v-model="addForm.notes" placeholder="Mis. No. polisi, no. BPKB..." />
                        </div>
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button type="button" variant="outline" @click="showAddForm = false; addForm.reset()">Batal</Button>
                        <Button type="submit" :disabled="addForm.processing">Simpan Aset</Button>
                    </div>
                </form>
            </div>

            <!-- Tabel aset -->
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b bg-gray-50">
                    <h2 class="text-sm font-bold text-gray-700 uppercase tracking-wide">
                        Daftar Aset Tetap — per 31 Des {{ currentYear }}
                    </h2>
                </div>

                <div v-if="!assets.length" class="px-5 py-10 text-sm text-gray-400 text-center">
                    Belum ada aset tetap. Klik "+ Tambah Aset" untuk mulai.
                </div>

                <table v-else class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-gray-50/60 text-gray-600 text-xs uppercase tracking-wide">
                            <th class="px-5 py-2.5 text-left">Aset</th>
                            <th class="px-4 py-2.5 text-right">Harga Perolehan</th>
                            <th class="px-4 py-2.5 text-right">Masa Manfaat</th>
                            <th class="px-4 py-2.5 text-right">Penyusutan/Th</th>
                            <th class="px-4 py-2.5 text-right">Akum. Penyusutan</th>
                            <th class="px-4 py-2.5 text-right">Nilai Buku</th>
                            <th class="px-5 py-2.5 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <!-- Baris normal -->
                        <template v-for="(a, i) in assets" :key="a.id">
                            <tr v-if="editingId !== a.id"
                                :class="[i % 2 === 1 ? 'bg-gray-50/30' : '', !a.is_active ? 'opacity-50' : '']">
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs px-1.5 py-0.5 rounded font-medium"
                                            :class="CAT_COLORS[a.category] ?? 'bg-gray-100 text-gray-600'">
                                            {{ categories[a.category] ?? a.category }}
                                        </span>
                                        <div>
                                            <p class="font-medium text-gray-800">{{ a.name }}</p>
                                            <p class="text-xs text-gray-400">{{ depreciationYears(a) }} · Perolehan: {{ a.acquisition_date?.substring(0,10) }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-right font-mono">{{ fmtRp(a.acquisition_cost) }}</td>
                                <td class="px-4 py-3 text-right text-gray-600">{{ a.useful_life_years }} tahun</td>
                                <td class="px-4 py-3 text-right font-mono text-amber-700">{{ fmtRp(a.annual_depreciation) }}</td>
                                <td class="px-4 py-3 text-right font-mono text-red-600">({{ fmtRp(a.accumulated) }})</td>
                                <td class="px-4 py-3 text-right font-mono font-semibold text-emerald-700">{{ fmtRp(a.book_value) }}</td>
                                <td class="px-5 py-3 text-center">
                                    <div class="flex gap-1 justify-center">
                                        <Button size="sm" variant="outline" class="h-7 text-xs" @click="startEdit(a)">Edit</Button>
                                        <Button size="sm" variant="destructive" class="h-7 text-xs" @click="deleteAsset(a)">Hapus</Button>
                                    </div>
                                </td>
                            </tr>

                            <!-- Baris edit inline -->
                            <tr v-else class="bg-blue-50/30">
                                <td colspan="7" class="px-5 py-4">
                                    <form @submit.prevent="submitEdit(a.id)" class="grid grid-cols-3 gap-3">
                                        <div class="space-y-1">
                                            <Label class="text-xs">Nama</Label>
                                            <Input v-model="editForm.name" class="h-8 text-sm" />
                                        </div>
                                        <div class="space-y-1">
                                            <Label class="text-xs">Kategori</Label>
                                            <select v-model="editForm.category"
                                                class="w-full h-8 rounded-md border border-input bg-background px-2 text-sm">
                                                <option v-for="(label, key) in categories" :key="key" :value="key">{{ label }}</option>
                                            </select>
                                        </div>
                                        <div class="space-y-1">
                                            <Label class="text-xs">Tgl Perolehan</Label>
                                            <Input type="date" v-model="editForm.acquisition_date" class="h-8 text-sm" />
                                        </div>
                                        <div class="space-y-1">
                                            <Label class="text-xs">Harga Perolehan</Label>
                                            <Input type="number" v-model.number="editForm.acquisition_cost" min="0" step="1000" class="h-8 text-sm" />
                                        </div>
                                        <div class="space-y-1">
                                            <Label class="text-xs">Masa Manfaat (th)</Label>
                                            <Input type="number" v-model.number="editForm.useful_life_years" min="1" max="50" class="h-8 text-sm" />
                                        </div>
                                        <div class="space-y-1">
                                            <Label class="text-xs">Nilai Sisa</Label>
                                            <Input type="number" v-model.number="editForm.residual_value" min="0" step="1000" class="h-8 text-sm" />
                                        </div>
                                        <div class="space-y-1">
                                            <Label class="text-xs">Kelompok Fiskal</Label>
                                            <select v-model="editForm.fiscal_group"
                                                class="w-full h-8 rounded-md border border-input bg-background px-2 text-sm">
                                                <option value="">— Belum —</option>
                                                <option v-for="(grp, key) in fiscalGroups" :key="key" :value="key">{{ grp.label }}</option>
                                            </select>
                                        </div>
                                        <div class="col-span-2 space-y-1">
                                            <Label class="text-xs">Catatan</Label>
                                            <Input v-model="editForm.notes" class="h-8 text-sm" />
                                        </div>
                                        <div class="space-y-1">
                                            <Label class="text-xs">Status</Label>
                                            <select v-model="editForm.is_active"
                                                class="w-full h-8 rounded-md border border-input bg-background px-2 text-sm">
                                                <option :value="true">Aktif</option>
                                                <option :value="false">Nonaktif</option>
                                            </select>
                                        </div>
                                        <div class="col-span-3 flex justify-end gap-2 pt-1">
                                            <Button type="button" size="sm" variant="outline" class="h-7 text-xs" @click="editingId = null">Batal</Button>
                                            <Button type="submit" size="sm" class="h-7 text-xs" :disabled="editForm.processing">Simpan</Button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                    <tfoot>
                        <tr class="bg-gray-100 border-t-2 font-bold text-sm">
                            <td class="px-5 py-3 text-gray-700">TOTAL AKTIF</td>
                            <td class="px-4 py-3 text-right font-mono text-blue-800">{{ fmtRp(totals.cost) }}</td>
                            <td class="px-4 py-3"></td>
                            <td class="px-4 py-3"></td>
                            <td class="px-4 py-3 text-right font-mono text-red-600">({{ fmtRp(totals.accumulated) }})</td>
                            <td class="px-4 py-3 text-right font-mono text-emerald-700">{{ fmtRp(totals.book_value) }}</td>
                            <td class="px-5 py-3"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Panduan penyusutan -->
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-sm text-amber-800 space-y-1">
                <p class="font-semibold">Cara kerja penyusutan</p>
                <p>Metode: Garis Lurus (Straight-Line). Tahun pertama dihitung prorata dari bulan perolehan.</p>
                <p>Penyusutan otomatis muncul di Laporan Laba Rugi sebagai Beban Penyusutan, dan di Neraca sebagai pengurang Aset Tetap.</p>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
