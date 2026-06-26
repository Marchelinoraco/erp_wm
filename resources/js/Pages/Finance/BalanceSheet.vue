<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import { fmtRp } from '@/lib/fmt'

const props = defineProps({
    year: Number,
    years: Array,
    aset: Object,
    kewajiban: Object,
    ekuitas: Object,
    balanced: Boolean,
})

function changeYear(e) {
    router.get(route('finance.balance-sheet'), { year: e.target.value }, { preserveState: false })
}

// Donut komposisi aset
const asetParts = computed(() => {
    const parts = props.aset.cash.map(c => ({ name: c.name, value: c.balance }))
    if (props.aset.ar > 0) parts.push({ name: 'Piutang Usaha', value: props.aset.ar })
    return parts.filter(p => p.value > 0)
})
const donutOptions = computed(() => ({
    chart: { type: 'donut', fontFamily: 'inherit' },
    labels: asetParts.value.map(p => p.name),
    colors: ['#0f3460', '#1d4ed8', '#0891b2', '#0d9488', '#65a30d', '#ca8a04', '#c0272d'],
    legend: { position: 'bottom' },
    dataLabels: { enabled: true, formatter: (v) => v.toFixed(0) + '%' },
    tooltip: { y: { formatter: (v) => fmtRp(v) } },
    plotOptions: { pie: { donut: { labels: { show: true, total: { show: true, label: 'Total Aset', formatter: () => fmtRp(props.aset.total) } } } } },
}))
const donutSeries = computed(() => asetParts.value.map(p => p.value))
</script>

<template>
    <Head title="Neraca" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-base font-semibold text-gray-800">Neraca (Laporan Posisi Keuangan)</h1>
                <div class="flex items-center gap-2">
                    <select :value="year" @change="changeYear" class="text-sm border rounded-md px-2 py-1">
                        <option v-for="y in years" :key="y" :value="y">Per 31 Des {{ y }}</option>
                    </select>
                    <a :href="route('finance.balance-sheet.pdf', { year })" target="_blank" class="text-sm px-3 py-1 rounded-md border bg-white hover:bg-gray-50">⬇ PDF</a>
                </div>
            </div>
        </template>

        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">
            <!-- Cek keseimbangan -->
            <div class="rounded-xl border shadow-sm p-4 flex items-center justify-between"
                :class="balanced ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'">
                <p class="text-sm font-semibold" :class="balanced ? 'text-green-700' : 'text-red-700'">
                    {{ balanced ? '✓ Seimbang — Aset = Kewajiban + Ekuitas' : '✗ Tidak seimbang' }}
                </p>
                <div class="flex gap-6 text-right text-sm">
                    <div><p class="text-xs text-gray-500">Total Aset</p><p class="font-mono font-bold">{{ fmtRp(aset.total) }}</p></div>
                    <div><p class="text-xs text-gray-500">Kewajiban + Ekuitas</p><p class="font-mono font-bold">{{ fmtRp(kewajiban.total + ekuitas.total) }}</p></div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
                <!-- ASET -->
                <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                    <div class="px-5 py-3 border-b bg-blue-50/50">
                        <h2 class="text-sm font-bold text-blue-800 uppercase tracking-wide">Aset</h2>
                    </div>
                    <div class="px-5 py-3">
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Kas & Bank</p>
                        <div class="space-y-1.5">
                            <div v-for="c in aset.cash" :key="c.name" class="flex justify-between text-sm">
                                <span class="text-gray-600">{{ c.name }}</span>
                                <span class="font-mono" :class="c.balance < 0 ? 'text-red-600' : ''">{{ fmtRp(c.balance) }}</span>
                            </div>
                        </div>
                        <div class="flex justify-between text-sm mt-3 pt-2 border-t">
                            <span class="text-gray-600">Piutang Usaha (AR)</span>
                            <span class="font-mono">{{ fmtRp(aset.ar) }}</span>
                        </div>

                        <!-- Aset Tetap -->
                        <template v-if="aset.fixed && aset.fixed.length">
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mt-4 mb-2">Aset Tetap</p>
                            <div v-for="grp in aset.fixed" :key="grp.category" class="mb-3">
                                <p class="text-xs text-gray-500 font-medium mb-1">{{ grp.label }}</p>
                                <div v-for="item in grp.items" :key="item.name" class="flex justify-between text-sm pl-2">
                                    <span class="text-gray-600">{{ item.name }}</span>
                                    <span class="font-mono">{{ fmtRp(item.cost) }}</span>
                                </div>
                                <div class="flex justify-between text-sm pl-2 text-red-500 italic">
                                    <span>Akumulasi Penyusutan</span>
                                    <span class="font-mono">({{ fmtRp(grp.total_accum) }})</span>
                                </div>
                                <div class="flex justify-between text-sm pl-2 border-t mt-1 pt-1 font-medium">
                                    <span class="text-gray-700">Nilai Buku Bersih</span>
                                    <span class="font-mono text-emerald-700">{{ fmtRp(grp.total_net) }}</span>
                                </div>
                            </div>
                            <div class="flex justify-between text-sm pt-2 border-t font-semibold">
                                <span class="text-gray-700">Total Aset Tetap (Neto)</span>
                                <span class="font-mono text-emerald-700">{{ fmtRp(aset.fixed_net) }}</span>
                            </div>
                        </template>
                    </div>
                    <div class="px-5 py-3 border-t bg-blue-50/50 flex justify-between font-bold">
                        <span class="text-blue-800">TOTAL ASET</span>
                        <span class="font-mono text-blue-800">{{ fmtRp(aset.total) }}</span>
                    </div>
                </div>

                <!-- KEWAJIBAN + EKUITAS -->
                <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                    <div class="px-5 py-3 border-b bg-amber-50/50">
                        <h2 class="text-sm font-bold text-amber-800 uppercase tracking-wide">Kewajiban & Ekuitas</h2>
                    </div>
                    <div class="px-5 py-3">
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">Kewajiban Jangka Pendek</p>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-600">Hutang Usaha (AP)</span>
                            <span class="font-mono">{{ fmtRp(kewajiban.ap) }}</span>
                        </div>

                        <template v-if="kewajiban.loans && kewajiban.loans.length">
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1 mt-4">Kewajiban Jangka Panjang</p>
                            <div v-for="grp in kewajiban.loans" :key="grp.type" class="mb-3">
                                <p class="text-xs text-gray-500 font-medium mb-1">{{ grp.label }}</p>
                                <div v-for="item in grp.items" :key="item.name" class="flex justify-between text-sm pl-2">
                                    <span class="text-gray-600">{{ item.name }}</span>
                                    <span class="font-mono">{{ fmtRp(item.outstanding) }}</span>
                                </div>
                                <div class="flex justify-between text-sm pl-2 border-t mt-1 pt-1 font-medium">
                                    <span class="text-gray-700">Subtotal {{ grp.label }}</span>
                                    <span class="font-mono text-red-700">{{ fmtRp(grp.total) }}</span>
                                </div>
                            </div>
                            <div class="flex justify-between text-sm pt-1 border-t font-semibold">
                                <span class="text-gray-700">Total Hutang Bank/Leasing</span>
                                <span class="font-mono text-red-700">{{ fmtRp(kewajiban.loans_total) }}</span>
                            </div>
                        </template>

                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1 mt-4">Ekuitas</p>
                        <div class="space-y-1.5">
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Modal Disetor</span>
                                <span class="font-mono">{{ fmtRp(ekuitas.modal) }}</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span class="text-gray-600">Laba Ditahan</span>
                                <span class="font-mono" :class="ekuitas.laba_ditahan < 0 ? 'text-red-600' : ''">{{ fmtRp(ekuitas.laba_ditahan) }}</span>
                            </div>
                        </div>
                    </div>
                    <div class="px-5 py-3 border-t bg-amber-50/50 flex justify-between font-bold">
                        <span class="text-amber-800">TOTAL KEWAJIBAN + EKUITAS</span>
                        <span class="font-mono text-amber-800">{{ fmtRp(kewajiban.total + ekuitas.total) }}</span>
                    </div>
                </div>
            </div>

            <!-- Komposisi aset -->
            <div v-if="asetParts.length" class="bg-white rounded-xl border shadow-sm p-5">
                <h2 class="text-sm font-semibold text-gray-800 mb-3">Komposisi Aset</h2>
                <apexchart type="donut" height="300" :options="donutOptions" :series="donutSeries" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
