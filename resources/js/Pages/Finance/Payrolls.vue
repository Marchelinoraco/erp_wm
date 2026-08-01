<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { fmtRp } from '@/lib/fmt'

const props = defineProps({
    periods: Array, currentMonth: String,
    period: { type: String, default: null },
    draft: { type: Array, default: () => [] },
    cashAccounts: { type: Array, default: () => [] },
})

function bukaPeriode(p) {
    router.get(route('payrolls.show', p))
}

// Fix wave final review 2026-08-01, Temuan #3: tombol "Buka Periode" cepat
// hanya membuka bulan berjalan — begitu satu bulan terlewat tanpa diproses,
// tidak ada jalan UI untuk membukanya lagi. periodeInput menampung pilihan
// bebas (format YYYY-MM native dari <input type="month">), tombol cepat di
// atas tetap ada untuk bulan berjalan.
const periodeInput = ref(props.currentMonth)

// D6: kas bon otomatis boleh diturunkan admin sebelum bayar (mis. "bulan ini
// potong separuh dulu"). overrides dikeyakan per employee_advance_id — server
// yang jadi otoritas final untuk net_amount (lihat PayrollController::pay()).
const overrides = ref({})
function overrideValue(id, default_) {
    return overrides.value[id] ?? default_
}
function setOverride(id, value) {
    // Fix wave final review 2026-08-01, Temuan #2 (bonus bug): Number('') === 0,
    // jadi mengosongkan input (blur tanpa isi) sebelumnya diam-diam memotong
    // kas bon jadi nol. String kosong sekarang menghapus override — field
    // kosong kembali ke nilai otomatis, bukan "potong nol".
    if (value === '') {
        delete overrides.value[id]
        return
    }
    overrides.value[id] = Number(value)
}

// Fix wave final review 2026-08-01, Temuan #2: net_amount mentah (r.net_amount)
// dan totalNet TIDAK bereaksi terhadap overrides yang diketik admin — angka
// yang tampil di tabel/dialog konfirmasi tetap angka lama sementara server
// membukukan angka baru. netAmountTampil mem-mirror logika
// PayrollController::terapkanOverridesKasBon() persis: gross sebelum kas bon
// tidak berubah (base_salary + tunjangan - potongan komponen), hanya alokasi
// potongan kas bon yang disesuaikan turun (di-cap ke potongan otomatis, D6).
function netAmountTampil(row) {
    const totalPotonganAsli = row.advances.reduce((s, a) => s + a.potongan, 0)
    const gross = row.net_amount + totalPotonganAsli
    const totalPotonganEfektif = row.advances.reduce((s, a) => {
        const efektif = Math.min(overrideValue(a.employee_advance_id, a.potongan), a.potongan)
        return s + efektif
    }, 0)
    return gross - totalPotonganEfektif
}

const payForm = useForm({ cash_account_id: '' })
function bayar() {
    if (!confirm(`Bayar gajian periode ${props.period}? Setelah dibayar, berkas terkunci.`)) return
    payForm.transform(data => ({
        ...data,
        overrides: Object.entries(overrides.value).map(([employee_advance_id, potongan]) => ({
            employee_advance_id: Number(employee_advance_id), potongan,
        })),
    })).post(route('payrolls.pay', props.period))
}

function batalkan(p) {
    if (!confirm(`Batalkan gajian ${p.period}? Jurnal akan dihapus, status kembali draft.`)) return
    router.post(route('payrolls.cancel', p.id))
}

const totalNet = computed(() => props.draft.reduce((s, r) => s + netAmountTampil(r), 0))
</script>

<template>
    <Head title="Gajian" />
    <AuthenticatedLayout>
        <div class="p-6 max-w-4xl mx-auto space-y-6">
            <div class="flex justify-between items-center">
                <h1 class="text-lg font-bold">Gajian</h1>
                <div class="flex gap-2 items-center">
                    <button @click="bukaPeriode(currentMonth)" class="px-3 py-1.5 bg-indigo-600 text-white rounded text-sm">
                        Buka Periode {{ currentMonth }}
                    </button>
                    <!-- Fix wave final review 2026-08-01, Temuan #3: bulan yang
                         terlewat tanpa diproses tidak punya jalan UI untuk
                         dibuka lagi selain tombol cepat di atas (bulan berjalan
                         saja). Input type="month" menghasilkan YYYY-MM native,
                         cocok dengan format period yang dipakai backend. -->
                    <div class="flex gap-2 items-center">
                        <input v-model="periodeInput" type="month" class="border rounded px-2 py-1 text-sm" />
                        <button @click="bukaPeriode(periodeInput)" class="px-3 py-1.5 bg-indigo-600 text-white rounded text-sm">
                            Buka Periode
                        </button>
                    </div>
                </div>
            </div>

            <table class="w-full text-sm border">
                <thead class="bg-gray-50"><tr><th class="text-left p-2">Periode</th><th class="p-2">Status</th><th class="p-2"></th></tr></thead>
                <tbody>
                    <tr v-for="p in periods" :key="p.period" class="border-t">
                        <td class="p-2">{{ p.period }}</td>
                        <td class="p-2 text-center">
                            <span :class="p.status === 'paid' ? 'text-green-600' : 'text-gray-400'">{{ p.status }}</span>
                        </td>
                        <td class="p-2 text-right space-x-2">
                            <button @click="bukaPeriode(p.period)" class="text-indigo-600 text-xs">Buka</button>
                            <button v-if="p.status === 'paid'" @click="batalkan(p)" class="text-red-600 text-xs">Batalkan</button>
                        </td>
                    </tr>
                </tbody>
            </table>

            <div v-if="period" class="border rounded p-4 space-y-3">
                <h2 class="font-bold">Draft Gajian — {{ period }}</h2>
                <table class="w-full text-sm">
                    <thead><tr>
                        <th class="text-left p-1">Karyawan</th><th class="text-right p-1">Pokok</th>
                        <th class="text-right p-1">Kas Bon</th><th class="text-right p-1">Dibayar</th>
                    </tr></thead>
                    <tbody>
                        <tr v-for="r in draft" :key="r.employee_id" class="border-t">
                            <td class="p-1">{{ r.employee_name }}</td>
                            <td class="p-1 text-right font-mono">{{ fmtRp(r.base_salary) }}</td>
                            <td class="p-1">
                                <div v-for="a in r.advances" :key="a.employee_advance_id" class="flex items-center gap-1 justify-end mb-0.5">
                                    <span class="text-xs text-gray-400">{{ a.label }}</span>
                                    <input
                                        type="number"
                                        :value="overrideValue(a.employee_advance_id, a.potongan)"
                                        @input="setOverride(a.employee_advance_id, $event.target.value)"
                                        :max="a.potongan"
                                        min="0"
                                        class="border rounded px-1 py-0.5 text-xs w-24 text-right font-mono"
                                    />
                                </div>
                            </td>
                            <td class="p-1 text-right font-mono font-bold">{{ fmtRp(netAmountTampil(r)) }}</td>
                        </tr>
                    </tbody>
                    <tfoot><tr class="border-t font-bold"><td class="p-1">TOTAL</td><td></td><td></td>
                        <td class="p-1 text-right font-mono">{{ fmtRp(totalNet) }}</td>
                    </tr></tfoot>
                </table>

                <div v-if="draft.length" class="flex gap-2 items-center pt-2 border-t">
                    <select v-model="payForm.cash_account_id" class="border rounded px-2 py-1 text-sm">
                        <option value="" disabled>Bayar dari akun kas</option>
                        <option v-for="a in cashAccounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                    </select>
                    <button @click="bayar" class="bg-green-600 text-white rounded px-4 py-1.5 text-sm">Bayar Semua</button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
