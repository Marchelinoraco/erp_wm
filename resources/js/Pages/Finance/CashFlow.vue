<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import { fmtRp } from '@/lib/fmt'

const props = defineProps({
    year: Number,
    years: Array,
    months: Array,
    incomeSeries: Array,
    expenseSeries: Array,
    balanceSeries: Array,
    incomeByCat: Array,
    expenseByCat: Array,
    accounts: Array,
    totals: Object,
})

function changeYear(e) {
    router.get(route('finance.cashflow'), { year: e.target.value }, { preserveState: false })
}

const rpAxis = (v) => 'Rp ' + (Math.abs(v) >= 1e6 ? (v / 1e6).toFixed(1).replace('.0', '') + ' jt' : Math.round(v / 1e3) + ' rb')

// ── Chart: Pemasukan vs Pengeluaran (bar) ──
const barOptions = computed(() => ({
    chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'inherit' },
    plotOptions: { bar: { columnWidth: '60%', borderRadius: 3 } },
    colors: ['#16a34a', '#dc2626'],
    dataLabels: { enabled: false },
    xaxis: { categories: props.months },
    yaxis: { labels: { formatter: rpAxis } },
    legend: { position: 'top', horizontalAlign: 'right' },
    tooltip: { y: { formatter: (v) => fmtRp(v) } },
    grid: { borderColor: '#eef2f7' },
}))
const barSeries = computed(() => [
    { name: 'Pemasukan', data: props.incomeSeries },
    { name: 'Pengeluaran', data: props.expenseSeries },
])

// ── Chart: Saldo berjalan (area) ──
const areaOptions = computed(() => ({
    chart: { type: 'area', toolbar: { show: false }, fontFamily: 'inherit' },
    colors: ['#0f3460'],
    dataLabels: { enabled: false },
    stroke: { curve: 'smooth', width: 2 },
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.05 } },
    xaxis: { categories: props.months },
    yaxis: { labels: { formatter: rpAxis } },
    tooltip: { y: { formatter: (v) => fmtRp(v) } },
    grid: { borderColor: '#eef2f7' },
}))
const areaSeries = computed(() => [{ name: 'Saldo', data: props.balanceSeries }])

// ── Chart: Pengeluaran per kategori (donut) ──
const donutOptions = computed(() => ({
    chart: { type: 'donut', fontFamily: 'inherit' },
    labels: props.expenseByCat.map((c) => c.name),
    colors: ['#dc2626', '#ea580c', '#d97706', '#ca8a04', '#65a30d', '#0891b2', '#7c3aed', '#db2777'],
    legend: { position: 'bottom' },
    dataLabels: { enabled: true, formatter: (v) => v.toFixed(0) + '%' },
    tooltip: { y: { formatter: (v) => fmtRp(v) } },
    plotOptions: { pie: { donut: { labels: { show: true, total: { show: true, label: 'Total', formatter: () => fmtRp(props.totals.expense) } } } } },
}))
const donutSeries = computed(() => props.expenseByCat.map((c) => c.total))

const hasExpense = computed(() => props.expenseByCat.length > 0)
</script>

<template>
    <Head title="Arus Kas" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-base font-semibold text-gray-800">Arus Kas</h1>
                <select :value="year" @change="changeYear" class="text-sm border rounded-md px-2 py-1">
                    <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                </select>
            </div>
        </template>

        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">
            <!-- Ringkasan -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
                <div class="bg-white rounded-xl border shadow-sm p-4">
                    <p class="text-xs text-gray-500">Total Pemasukan {{ year }}</p>
                    <p class="text-lg font-bold text-green-600 mt-1">{{ fmtRp(totals.income) }}</p>
                </div>
                <div class="bg-white rounded-xl border shadow-sm p-4">
                    <p class="text-xs text-gray-500">Total Pengeluaran {{ year }}</p>
                    <p class="text-lg font-bold text-red-600 mt-1">{{ fmtRp(totals.expense) }}</p>
                </div>
                <div class="bg-white rounded-xl border shadow-sm p-4">
                    <p class="text-xs text-gray-500">Arus Kas Bersih</p>
                    <p class="text-lg font-bold mt-1" :class="totals.net >= 0 ? 'text-green-600' : 'text-red-600'">{{ fmtRp(totals.net) }}</p>
                </div>
                <div class="bg-white rounded-xl border shadow-sm p-4">
                    <p class="text-xs text-gray-500">Saldo Kas Sekarang</p>
                    <p class="text-lg font-bold text-gray-900 mt-1">{{ fmtRp(totals.balance) }}</p>
                </div>
            </div>

            <!-- Bar + Donut -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
                <div class="lg:col-span-2 bg-white rounded-xl border shadow-sm p-5">
                    <h2 class="text-sm font-semibold text-gray-800 mb-3">Pemasukan vs Pengeluaran per Bulan</h2>
                    <apexchart type="bar" height="300" :options="barOptions" :series="barSeries" />
                </div>
                <div class="bg-white rounded-xl border shadow-sm p-5">
                    <h2 class="text-sm font-semibold text-gray-800 mb-3">Pengeluaran per Kategori</h2>
                    <apexchart v-if="hasExpense" type="donut" height="300" :options="donutOptions" :series="donutSeries" />
                    <p v-else class="text-sm text-gray-400 text-center py-20">Belum ada pengeluaran tahun ini.</p>
                </div>
            </div>

            <!-- Saldo trend -->
            <div class="bg-white rounded-xl border shadow-sm p-5">
                <h2 class="text-sm font-semibold text-gray-800 mb-3">Tren Saldo Kas (kumulatif)</h2>
                <apexchart type="area" height="280" :options="areaOptions" :series="areaSeries" />
            </div>

            <!-- Saldo per akun -->
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b">
                    <h2 class="text-sm font-semibold text-gray-800">Saldo per Akun Kas</h2>
                </div>
                <div class="divide-y">
                    <div v-for="a in accounts" :key="a.name" class="px-5 py-3 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                                :class="a.type === 'cash' ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700'">
                                {{ a.type === 'cash' ? 'Kas' : 'Bank' }}
                            </span>
                            <span class="text-sm font-medium text-gray-800">{{ a.name }}</span>
                        </div>
                        <span class="font-mono font-semibold" :class="a.balance >= 0 ? 'text-gray-900' : 'text-red-600'">{{ fmtRp(a.balance) }}</span>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
