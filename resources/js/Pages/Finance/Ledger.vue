<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { fmtRp } from '@/lib/fmt'

const props = defineProps({
    year: Number,
    years: Array,
    month: [String, null],
    accounts: Array,
    profit: Object,
})

const GROUPS = {
    aset:       { label: 'Kas & Bank (Aset)', color: 'text-blue-700' },
    pendapatan: { label: 'Pendapatan',        color: 'text-green-700' },
    beban:      { label: 'Beban',             color: 'text-red-700' },
}

function fmtDate(d) {
    return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short' })
}
function changePeriod() {
    router.get(route('finance.ledger'), { year: yearSel.value, month: monthSel.value || undefined }, { preserveState: false })
}
const yearSel = ref(props.year)
const monthSel = ref(props.month || '')

const open = ref(new Set())
function toggle(key) {
    open.value.has(key) ? open.value.delete(key) : open.value.add(key)
    open.value = new Set(open.value)
}

const grouped = computed(() => {
    const g = { aset: [], pendapatan: [], beban: [] }
    props.accounts.forEach((a, i) => g[a.group]?.push({ ...a, _key: a.group + '-' + i }))
    return g
})

// Chart: Pendapatan vs Beban
const barOptions = {
    chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'inherit', sparkline: { enabled: false } },
    plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '50%', distributed: true } },
    colors: ['#16a34a', '#dc2626'],
    dataLabels: { enabled: true, formatter: (v) => fmtRp(v) },
    xaxis: { categories: ['Pendapatan', 'Beban'], labels: { show: false } },
    legend: { show: false },
    grid: { borderColor: '#eef2f7' },
    tooltip: { y: { formatter: (v) => fmtRp(v) } },
}
const barSeries = computed(() => [{ data: [props.profit.income, props.profit.expense] }])
const monthName = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember']
</script>

<template>
    <Head title="Buku Besar" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-base font-semibold text-gray-800">Buku Besar</h1>
                <div class="flex gap-2">
                    <select v-model="monthSel" @change="changePeriod" class="text-sm border rounded-md px-2 py-1">
                        <option value="">Setahun</option>
                        <option v-for="m in 12" :key="m" :value="m">{{ monthName[m] }}</option>
                    </select>
                    <select v-model="yearSel" @change="changePeriod" class="text-sm border rounded-md px-2 py-1">
                        <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                    </select>
                </div>
            </div>
        </template>

        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">
            <!-- Laba Akuntansi -->
            <div class="bg-white rounded-xl border shadow-sm p-5">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 items-center">
                    <div>
                        <h2 class="text-sm font-semibold text-gray-800 mb-3">Laba (Rugi) Akuntansi</h2>
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm"><span class="text-gray-500">Total Pendapatan</span><span class="font-mono font-semibold text-green-600">{{ fmtRp(profit.income) }}</span></div>
                            <div class="flex justify-between text-sm"><span class="text-gray-500">Total Beban</span><span class="font-mono font-semibold text-red-600">− {{ fmtRp(profit.expense) }}</span></div>
                            <div class="flex justify-between border-t pt-2 mt-1">
                                <span class="font-semibold text-gray-800">{{ profit.net >= 0 ? 'Laba Bersih' : 'Rugi Bersih' }}</span>
                                <span class="font-mono font-bold text-lg" :class="profit.net >= 0 ? 'text-green-700' : 'text-red-700'">{{ fmtRp(profit.net) }}</span>
                            </div>
                        </div>
                    </div>
                    <apexchart type="bar" height="160" :options="barOptions" :series="barSeries" />
                </div>
            </div>

            <!-- Akun-akun -->
            <div v-for="(list, gkey) in grouped" :key="gkey">
                <h3 class="text-xs font-semibold uppercase tracking-wide mb-2" :class="GROUPS[gkey].color">{{ GROUPS[gkey].label }}</h3>
                <div v-if="!list.length" class="text-sm text-gray-400 mb-4">Tidak ada.</div>
                <div v-else class="bg-white rounded-xl border shadow-sm overflow-hidden mb-4 divide-y">
                    <div v-for="a in list" :key="a._key">
                        <button class="w-full px-5 py-3 flex items-center justify-between hover:bg-gray-50 transition-colors" @click="toggle(a._key)">
                            <span class="flex items-center gap-2">
                                <span class="text-gray-400 text-xs">{{ open.has(a._key) ? '▾' : '▸' }}</span>
                                <span class="text-sm font-medium text-gray-800">{{ a.name }}</span>
                            </span>
                            <span class="font-mono font-semibold" :class="a.balance >= 0 ? 'text-gray-900' : 'text-red-600'">{{ fmtRp(a.balance) }}</span>
                        </button>
                        <div v-if="open.has(a._key)" class="px-5 pb-3 bg-gray-50/50">
                            <table class="w-full text-xs">
                                <thead>
                                    <tr class="text-gray-400 text-left">
                                        <th class="py-1 font-medium w-20">Tgl</th>
                                        <th class="py-1 font-medium">Keterangan</th>
                                        <th class="py-1 font-medium text-right w-28">Debit</th>
                                        <th class="py-1 font-medium text-right w-28">Kredit</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                    <tr v-for="(p, pi) in a.postings" :key="pi">
                                        <td class="py-1 text-gray-500">{{ fmtDate(p.date) }}</td>
                                        <td class="py-1 text-gray-700">{{ p.desc }}</td>
                                        <td class="py-1 text-right font-mono">{{ p.debit > 0 ? fmtRp(p.debit) : '' }}</td>
                                        <td class="py-1 text-right font-mono">{{ p.credit > 0 ? fmtRp(p.credit) : '' }}</td>
                                    </tr>
                                </tbody>
                                <tfoot class="border-t font-semibold">
                                    <tr>
                                        <td colspan="2" class="py-1">Jumlah</td>
                                        <td class="py-1 text-right font-mono">{{ fmtRp(a.debit) }}</td>
                                        <td class="py-1 text-right font-mono">{{ fmtRp(a.credit) }}</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
