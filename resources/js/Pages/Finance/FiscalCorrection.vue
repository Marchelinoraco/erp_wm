<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { fmtRp } from '@/lib/fmt'

const props = defineProps({
    year:          Number,
    years:         Array,
    regime:        String,
    // Laba komersial
    totalRevenue:  Number,
    totalCogs:     Number,
    grossProfit:   Number,
    totalOpex:     Number,
    otherIncome:   Number,
    depKomersial:  Number,
    labaKomersial: Number,
    // Penyusutan fiskal
    depAssets:     Array,
    depFiskal:     Number,
    selisihDep:    Number,
    // Koreksi
    corrections:   Array,
    korPositif:    Number,
    korNegatif:    Number,
    pkp:           Number,
    // PPh
    taxBase:       Number,
    taxRatePct:    Number,
    pphTerutang:   Number,
    // Consts
    fiscalGroups:  Object,
})

function navigate(params) {
    router.get(route('finance.fiscal'), params, { preserveState: false })
}

// ── Add correction ────────────────────────────────────────────────
const showAddForm = ref(false)
const addForm = useForm({
    year:   props.year,
    name:   '',
    type:   'positive',
    amount: '',
    notes:  '',
})
function submitAdd() {
    addForm.post(route('fiscal.corrections.store'), {
        onSuccess: () => { addForm.reset('name', 'amount', 'notes'); addForm.year = props.year; showAddForm.value = false },
    })
}

// ── Edit correction ───────────────────────────────────────────────
const editingId = ref(null)
const editForm  = useForm({ name: '', type: '', amount: '', notes: '' })
function startEdit(c) {
    editingId.value = c.id
    Object.assign(editForm, { name: c.name, type: c.type, amount: c.amount, notes: c.notes ?? '' })
}
function submitEdit(id) {
    editForm.patch(route('fiscal.corrections.update', id), {
        onSuccess: () => { editingId.value = null },
    })
}
function deleteCorrection(id) {
    if (confirm('Hapus koreksi ini?')) router.delete(route('fiscal.corrections.destroy', id))
}

// ── Helpers ───────────────────────────────────────────────────────
const positiveCorrections = computed(() => props.corrections.filter(c => c.type === 'positive'))
const negativeCorrections = computed(() => props.corrections.filter(c => c.type === 'negative'))

function selisihClass(v) {
    if (v > 0)  return 'text-red-600'
    if (v < 0)  return 'text-emerald-600'
    return 'text-gray-500'
}

const regimeLabel = computed(() =>
    props.regime === 'pp23' ? 'PP 23/2018 — 0,5% dari Omzet' : 'PPh Badan Umum — 22% dari PKP'
)
</script>

<template>
    <Head title="Koreksi Fiskal" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between flex-wrap gap-2">
                <h1 class="text-base font-semibold text-gray-800">Koreksi Fiskal &amp; PPh Badan</h1>
                <div class="flex items-center gap-2 flex-wrap">
                    <select :value="year" @change="navigate({ year: $event.target.value, regime })"
                        class="text-sm border rounded-md px-2 py-1 bg-white">
                        <option v-for="y in years" :key="y" :value="y">Tahun {{ y }}</option>
                    </select>
                    <select :value="regime" @change="navigate({ year, regime: $event.target.value })"
                        class="text-sm border rounded-md px-2 py-1 bg-white">
                        <option value="badan_22">PPh Badan 22%</option>
                        <option value="pp23">PP 23 (0,5% Omzet)</option>
                    </select>
                    <a :href="route('finance.fiscal.pdf', { year, regime })" target="_blank"
                        class="text-sm px-3 py-1 rounded-md border bg-white hover:bg-gray-50">⬇ PDF</a>
                </div>
            </div>
        </template>

        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">

            <!-- ── 1. Laba Komersial ─────────────────────────────── -->
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b bg-blue-50/50">
                    <h2 class="text-sm font-bold text-blue-800 uppercase tracking-wide">Laba Rugi Komersial — {{ year }}</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Sebelum koreksi fiskal</p>
                </div>
                <div class="divide-y text-sm">
                    <div class="px-5 py-2.5 flex justify-between">
                        <span class="text-gray-600">Penjualan (Invoice AR)</span>
                        <span class="font-mono">{{ fmtRp(totalRevenue) }}</span>
                    </div>
                    <div class="px-5 py-2.5 flex justify-between">
                        <span class="text-gray-600">(–) HPP / COGS (Bill AP)</span>
                        <span class="font-mono text-red-600">{{ fmtRp(totalCogs) }}</span>
                    </div>
                    <div class="px-5 py-2.5 flex justify-between font-semibold bg-gray-50/50">
                        <span>= Laba Kotor</span>
                        <span class="font-mono" :class="grossProfit < 0 ? 'text-red-700' : 'text-gray-800'">{{ fmtRp(grossProfit) }}</span>
                    </div>
                    <div class="px-5 py-2.5 flex justify-between">
                        <span class="text-gray-600">(–) Biaya Operasional</span>
                        <span class="font-mono text-red-600">{{ fmtRp(totalOpex) }}</span>
                    </div>
                    <div class="px-5 py-2.5 flex justify-between">
                        <span class="text-gray-600">(–) Penyusutan Komersial</span>
                        <span class="font-mono text-red-600">{{ fmtRp(depKomersial) }}</span>
                    </div>
                    <div v-if="otherIncome > 0" class="px-5 py-2.5 flex justify-between">
                        <span class="text-gray-600">(+) Pendapatan Lain-lain</span>
                        <span class="font-mono text-blue-700">{{ fmtRp(otherIncome) }}</span>
                    </div>
                </div>
                <div class="px-5 py-3 border-t-2 flex justify-between font-bold"
                    :class="labaKomersial >= 0 ? 'bg-emerald-50 border-emerald-200' : 'bg-red-50 border-red-200'">
                    <span :class="labaKomersial >= 0 ? 'text-emerald-800' : 'text-red-800'">LABA KOMERSIAL</span>
                    <span class="font-mono" :class="labaKomersial >= 0 ? 'text-emerald-700' : 'text-red-700'">
                        {{ fmtRp(labaKomersial) }}
                    </span>
                </div>
            </div>

            <!-- ── 2. Penyusutan Komersial vs Fiskal ────────────── -->
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b bg-amber-50/50">
                    <h2 class="text-sm font-bold text-amber-800 uppercase tracking-wide">Perbandingan Penyusutan Komersial vs Fiskal</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Garis lurus · Fiskal: PMK 96/2009, tanpa nilai sisa</p>
                </div>

                <div v-if="!depAssets.length" class="px-5 py-6 text-sm text-gray-400 text-center">
                    Belum ada aset tetap aktif — atau belum ada yang menghasilkan penyusutan untuk tahun {{ year }}.
                </div>

                <table v-else class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-gray-50 text-gray-500 text-xs uppercase">
                            <th class="px-5 py-2 text-left">Aset</th>
                            <th class="px-4 py-2 text-center">Kelompok Fiskal</th>
                            <th class="px-4 py-2 text-right">Kom.</th>
                            <th class="px-4 py-2 text-right">Fiskal</th>
                            <th class="px-4 py-2 text-right">Selisih</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="(d, i) in depAssets" :key="d.id"
                            :class="i % 2 === 1 ? 'bg-gray-50/30' : ''">
                            <td class="px-5 py-2.5 font-medium text-gray-800">{{ d.name }}</td>
                            <td class="px-4 py-2.5 text-center">
                                <span v-if="d.fiscal_group"
                                    class="text-xs px-2 py-0.5 bg-amber-100 text-amber-800 rounded-full">
                                    {{ d.fiscal_label }}
                                </span>
                                <span v-else class="text-xs text-red-500 font-medium">⚠ Belum diset</span>
                            </td>
                            <td class="px-4 py-2.5 text-right font-mono">{{ fmtRp(d.dep_comm) }}</td>
                            <td class="px-4 py-2.5 text-right font-mono">{{ fmtRp(d.dep_fiscal) }}</td>
                            <td class="px-4 py-2.5 text-right font-mono font-semibold" :class="selisihClass(d.selisih)">
                                {{ d.selisih > 0 ? '+' : '' }}{{ fmtRp(Math.abs(d.selisih)) }}
                                <span class="text-xs font-normal ml-1">{{ d.selisih > 0 ? '(+)' : d.selisih < 0 ? '(–)' : '' }}</span>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t-2 font-bold bg-gray-50">
                            <td class="px-5 py-2.5" colspan="2">TOTAL</td>
                            <td class="px-4 py-2.5 text-right font-mono">{{ fmtRp(depKomersial) }}</td>
                            <td class="px-4 py-2.5 text-right font-mono">{{ fmtRp(depFiskal) }}</td>
                            <td class="px-4 py-2.5 text-right font-mono" :class="selisihClass(selisihDep)">
                                {{ selisihDep > 0 ? '+' : '' }}{{ fmtRp(Math.abs(selisihDep)) }}
                                <span class="text-xs font-normal ml-1">
                                    {{ selisihDep > 0 ? '→ Kor. Positif' : selisihDep < 0 ? '→ Kor. Negatif' : '' }}
                                </span>
                            </td>
                        </tr>
                    </tfoot>
                </table>

                <div v-if="depAssets.some(d => !d.fiscal_group)"
                    class="px-5 py-2.5 border-t bg-red-50 text-xs text-red-700 flex items-center gap-2">
                    ⚠ Beberapa aset belum memiliki Kelompok Fiskal. Set di halaman
                    <a :href="route('finance.fixed-assets')" class="underline font-semibold">Aset Tetap</a>.
                </div>
            </div>

            <!-- ── 3. Koreksi Fiskal Manual ──────────────────────── -->
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b bg-gray-50/50 flex items-center justify-between">
                    <div>
                        <h2 class="text-sm font-bold text-gray-700 uppercase tracking-wide">Koreksi Fiskal Manual</h2>
                        <p class="text-xs text-gray-400 mt-0.5">Biaya non-deductible, penghasilan final, koreksi lainnya</p>
                    </div>
                    <button @click="showAddForm = !showAddForm"
                        class="text-xs px-3 py-1.5 border rounded-md hover:bg-gray-50">
                        {{ showAddForm ? '✕ Tutup' : '+ Tambah' }}
                    </button>
                </div>

                <!-- Add form -->
                <div v-if="showAddForm" class="px-5 py-4 border-b bg-gray-50/30">
                    <form @submit.prevent="submitAdd" class="grid grid-cols-2 gap-3">
                        <div class="col-span-2">
                            <label class="block text-xs text-gray-600 mb-1">Deskripsi Koreksi</label>
                            <input v-model="addForm.name" type="text" required
                                class="w-full border rounded px-2.5 py-1.5 text-sm"
                                placeholder="cth: Biaya Entertainment (Daftar Nominatif), Bunga Jasa Giro (PPh Final)" />
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Jenis Koreksi</label>
                            <select v-model="addForm.type" class="w-full border rounded px-2.5 py-1.5 text-sm">
                                <option value="positive">Koreksi Positif (menambah PKP)</option>
                                <option value="negative">Koreksi Negatif (mengurangi PKP)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs text-gray-600 mb-1">Jumlah (Rp)</label>
                            <input v-model="addForm.amount" type="number" min="0" required
                                class="w-full border rounded px-2.5 py-1.5 text-sm font-mono" />
                        </div>
                        <div class="col-span-2">
                            <label class="block text-xs text-gray-600 mb-1">Catatan (opsional)</label>
                            <input v-model="addForm.notes" type="text"
                                class="w-full border rounded px-2.5 py-1.5 text-sm" />
                        </div>
                        <div class="col-span-2 flex gap-2">
                            <button type="submit" :disabled="addForm.processing"
                                class="px-4 py-1.5 bg-blue-800 text-white text-xs rounded hover:bg-blue-900 disabled:opacity-50">
                                Simpan
                            </button>
                            <button type="button" @click="showAddForm = false"
                                class="px-3 py-1.5 text-xs border rounded hover:bg-gray-50">Batal</button>
                        </div>
                    </form>
                </div>

                <!-- Positif -->
                <div v-if="positiveCorrections.length" class="px-5 py-2 border-b">
                    <p class="text-xs font-semibold text-red-700 uppercase tracking-wide mb-2">Koreksi Positif (tambah PKP)</p>
                    <div class="divide-y">
                        <template v-for="c in positiveCorrections" :key="c.id">
                            <div v-if="editingId !== c.id" class="flex items-center justify-between py-2 text-sm">
                                <div>
                                    <span class="text-gray-700">{{ c.name }}</span>
                                    <span v-if="c.notes" class="text-xs text-gray-400 ml-2">{{ c.notes }}</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="font-mono text-red-700">+{{ fmtRp(c.amount) }}</span>
                                    <button @click="startEdit(c)" class="text-xs text-blue-600 hover:underline">Edit</button>
                                    <button @click="deleteCorrection(c.id)" class="text-xs text-red-500 hover:underline">Hapus</button>
                                </div>
                            </div>
                            <div v-else class="py-2">
                                <form @submit.prevent="submitEdit(c.id)" class="grid grid-cols-2 gap-2">
                                    <div class="col-span-2">
                                        <input v-model="editForm.name" type="text" class="w-full border rounded px-2 py-1 text-xs" />
                                    </div>
                                    <div>
                                        <select v-model="editForm.type" class="w-full border rounded px-2 py-1 text-xs">
                                            <option value="positive">Koreksi Positif</option>
                                            <option value="negative">Koreksi Negatif</option>
                                        </select>
                                    </div>
                                    <div>
                                        <input v-model="editForm.amount" type="number" min="0"
                                            class="w-full border rounded px-2 py-1 text-xs font-mono" />
                                    </div>
                                    <div class="col-span-2 flex gap-2">
                                        <button type="submit" class="px-3 py-1 bg-blue-800 text-white text-xs rounded">Simpan</button>
                                        <button type="button" @click="editingId = null" class="px-2 py-1 text-xs border rounded">Batal</button>
                                    </div>
                                </form>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Negatif -->
                <div v-if="negativeCorrections.length" class="px-5 py-2 border-b">
                    <p class="text-xs font-semibold text-emerald-700 uppercase tracking-wide mb-2">Koreksi Negatif (kurangi PKP)</p>
                    <div class="divide-y">
                        <template v-for="c in negativeCorrections" :key="c.id">
                            <div v-if="editingId !== c.id" class="flex items-center justify-between py-2 text-sm">
                                <div>
                                    <span class="text-gray-700">{{ c.name }}</span>
                                    <span v-if="c.notes" class="text-xs text-gray-400 ml-2">{{ c.notes }}</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="font-mono text-emerald-700">–{{ fmtRp(c.amount) }}</span>
                                    <button @click="startEdit(c)" class="text-xs text-blue-600 hover:underline">Edit</button>
                                    <button @click="deleteCorrection(c.id)" class="text-xs text-red-500 hover:underline">Hapus</button>
                                </div>
                            </div>
                            <div v-else class="py-2">
                                <form @submit.prevent="submitEdit(c.id)" class="grid grid-cols-2 gap-2">
                                    <div class="col-span-2">
                                        <input v-model="editForm.name" type="text" class="w-full border rounded px-2 py-1 text-xs" />
                                    </div>
                                    <div>
                                        <select v-model="editForm.type" class="w-full border rounded px-2 py-1 text-xs">
                                            <option value="positive">Koreksi Positif</option>
                                            <option value="negative">Koreksi Negatif</option>
                                        </select>
                                    </div>
                                    <div>
                                        <input v-model="editForm.amount" type="number" min="0"
                                            class="w-full border rounded px-2 py-1 text-xs font-mono" />
                                    </div>
                                    <div class="col-span-2 flex gap-2">
                                        <button type="submit" class="px-3 py-1 bg-blue-800 text-white text-xs rounded">Simpan</button>
                                        <button type="button" @click="editingId = null" class="px-2 py-1 text-xs border rounded">Batal</button>
                                    </div>
                                </form>
                            </div>
                        </template>
                    </div>
                </div>

                <div v-if="!corrections.length && !showAddForm"
                    class="px-5 py-6 text-sm text-gray-400 text-center">
                    Belum ada koreksi fiskal manual untuk tahun {{ year }}.
                </div>
            </div>

            <!-- ── 4. Ringkasan PKP ──────────────────────────────── -->
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b bg-gray-50">
                    <h2 class="text-sm font-bold text-gray-700 uppercase tracking-wide">Rekonsiliasi Fiskal — PKP</h2>
                </div>
                <div class="divide-y text-sm">
                    <div class="px-5 py-2.5 flex justify-between">
                        <span class="text-gray-600">Laba Komersial</span>
                        <span class="font-mono" :class="labaKomersial < 0 ? 'text-red-600' : ''">{{ fmtRp(labaKomersial) }}</span>
                    </div>
                    <div class="px-5 py-2.5 flex justify-between">
                        <span class="text-gray-600">
                            (+) Koreksi Fiskal Positif
                            <span class="text-xs text-gray-400 ml-1">
                                (selisih dep: {{ fmtRp(Math.max(0, selisihDep)) }} + manual: {{ fmtRp(corrections.filter(c=>c.type==='positive').reduce((s,c)=>s+c.amount,0)) }})
                            </span>
                        </span>
                        <span class="font-mono text-red-600">+{{ fmtRp(korPositif) }}</span>
                    </div>
                    <div class="px-5 py-2.5 flex justify-between">
                        <span class="text-gray-600">
                            (–) Koreksi Fiskal Negatif
                            <span class="text-xs text-gray-400 ml-1">
                                (selisih dep: {{ fmtRp(Math.max(0, -selisihDep)) }} + manual: {{ fmtRp(corrections.filter(c=>c.type==='negative').reduce((s,c)=>s+c.amount,0)) }})
                            </span>
                        </span>
                        <span class="font-mono text-emerald-600">–{{ fmtRp(korNegatif) }}</span>
                    </div>
                </div>
                <div class="px-5 py-4 border-t-2 border-blue-200 bg-blue-50 flex justify-between font-bold">
                    <span class="text-blue-800">PENGHASILAN KENA PAJAK (PKP)</span>
                    <span class="font-mono text-blue-800 text-lg">{{ fmtRp(pkp) }}</span>
                </div>
            </div>

            <!-- ── 5. PPh Terutang ───────────────────────────────── -->
            <div class="rounded-xl border shadow-sm overflow-hidden"
                :class="pphTerutang > 0 ? 'bg-amber-50 border-amber-200' : 'bg-white'">
                <div class="px-5 py-3 border-b" :class="pphTerutang > 0 ? 'border-amber-200' : 'border-gray-200'">
                    <h2 class="text-sm font-bold uppercase tracking-wide"
                        :class="pphTerutang > 0 ? 'text-amber-800' : 'text-gray-700'">
                        PPh Badan Terutang — {{ regimeLabel }}
                    </h2>
                </div>
                <div class="divide-y text-sm" :class="pphTerutang > 0 ? 'divide-amber-100' : 'divide-gray-100'">
                    <div class="px-5 py-2.5 flex justify-between">
                        <span class="text-gray-600">Dasar Pengenaan Pajak</span>
                        <span class="font-mono">{{ fmtRp(taxBase) }}</span>
                    </div>
                    <div class="px-5 py-2.5 flex justify-between">
                        <span class="text-gray-600">Tarif PPh</span>
                        <span class="font-semibold">{{ taxRatePct }}%</span>
                    </div>
                </div>
                <div class="px-5 py-4 border-t-2 flex justify-between font-bold"
                    :class="pphTerutang > 0 ? 'border-amber-300 bg-amber-100' : 'border-gray-200 bg-gray-50'">
                    <span :class="pphTerutang > 0 ? 'text-amber-900' : 'text-gray-700'">PPh BADAN TERUTANG</span>
                    <span class="font-mono text-xl"
                        :class="pphTerutang > 0 ? 'text-amber-800' : 'text-gray-600'">
                        {{ fmtRp(pphTerutang) }}
                    </span>
                </div>
            </div>

            <!-- Catatan -->
            <div class="rounded-xl border border-blue-200 bg-blue-50/50 p-4 text-xs text-blue-700 space-y-1">
                <p class="font-semibold">Catatan penting</p>
                <ul class="list-disc list-inside space-y-0.5 text-blue-600">
                    <li>Angka ini bersifat estimasi — konsultasikan dengan konsultan pajak / akuntan publik sebelum SPT.</li>
                    <li><strong>PPh Badan 22%</strong>: berlaku untuk WP Badan umum (PT/CV). PKP dibulatkan ke ribuan penuh ke bawah.</li>
                    <li><strong>PP 23/2018 (0,5%)</strong>: berlaku untuk WP Badan dengan omzet bruto ≤ Rp 4,8 Miliar/tahun.</li>
                    <li>Penyusutan fiskal dihitung garis lurus, <strong>tanpa nilai sisa</strong>, sesuai kelompok PMK 96/2009.</li>
                    <li>Koreksi fiskal negatif untuk bunga jasa giro: tambahkan sebagai <em>Koreksi Negatif</em> manual di atas.</li>
                </ul>
            </div>

        </div>
    </AuthenticatedLayout>
</template>
