<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import { fmtRp } from '@/lib/fmt'

const props = defineProps({
    mode: String,
    periodLabel: String,
    year: Number,
    month: String,
    years: Array,
    labels: Array,
    incomeSeries: Array,
    expenseSeries: Array,
    netSeries: Array,
    rows: Array,
    totals: Object,
})

function go(params) {
    router.get(route('finance.recap'), { mode: props.mode, year: props.year, month: props.month, ...params }, { preserveState: false })
}
const rpAxis = (v) => 'Rp ' + (Math.abs(v) >= 1e6 ? (v / 1e6).toFixed(1).replace('.0', '') + ' jt' : Math.round(v / 1e3) + ' rb')

const chartOptions = computed(() => ({
    chart: { type: 'line', toolbar: { show: false }, fontFamily: 'inherit' },
    stroke: { width: [0, 0, 3], curve: 'smooth' },
    plotOptions: { bar: { columnWidth: '55%', borderRadius: 3 } },
    colors: ['#16a34a', '#dc2626', '#0f3460'],
    dataLabels: { enabled: false },
    xaxis: { categories: props.labels },
    yaxis: { labels: { formatter: rpAxis } },
    legend: { position: 'top', horizontalAlign: 'right' },
    tooltip: { y: { formatter: (v) => fmtRp(v) } },
    grid: { borderColor: '#eef2f7' },
    markers: { size: 4 },
}))
const chartSeries = computed(() => [
    { name: 'Pemasukan', type: 'column', data: props.incomeSeries },
    { name: 'Pengeluaran', type: 'column', data: props.expenseSeries },
    { name: 'Net', type: 'line', data: props.netSeries },
])
</script>

<template>
    <Head title="Rekap Keuangan" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-base font-semibold text-gray-800">Rekap Keuangan</h1>
                <div class="flex items-center gap-2">
                    <div class="inline-flex rounded-md border p-0.5 text-sm">
                        <button @click="go({ mode: 'monthly' })" :class="mode === 'monthly' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'" class="px-3 py-1 rounded">Bulanan</button>
                        <button @click="go({ mode: 'weekly' })" :class="mode === 'weekly' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground'" class="px-3 py-1 rounded">Mingguan</button>
                    </div>
                    <input v-if="mode === 'weekly'" type="month" :value="month" @change="(e) => go({ month: e.target.value })" class="text-sm border rounded-md px-2 py-1" />
                    <select v-else :value="year" @change="(e) => go({ year: e.target.value })" class="text-sm border rounded-md px-2 py-1">
                        <option v-for="y in years" :key="y" :value="y">{{ y }}</option>
                    </select>
                </div>
            </div>
        </template>

        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">
            <p class="text-sm text-gray-500">Rekap {{ mode === 'weekly' ? 'per minggu' : 'per bulan' }} — <b>{{ periodLabel }}</b></p>

            <!-- Ringkasan -->
            <div class="grid grid-cols-3 gap-3">
                <div class="bg-white rounded-xl border shadow-sm p-4">
                    <p class="text-xs text-gray-500">Total Pemasukan</p>
                    <p class="text-base font-bold text-green-600 mt-1">{{ fmtRp(totals.income) }}</p>
                </div>
                <div class="bg-white rounded-xl border shadow-sm p-4">
                    <p class="text-xs text-gray-500">Total Pengeluaran</p>
                    <p class="text-base font-bold text-red-600 mt-1">{{ fmtRp(totals.expense) }}</p>
                </div>
                <div class="bg-white rounded-xl border shadow-sm p-4">
                    <p class="text-xs text-gray-500">Net</p>
                    <p class="text-base font-bold mt-1" :class="totals.net >= 0 ? 'text-green-600' : 'text-red-600'">{{ fmtRp(totals.net) }}</p>
                </div>
            </div>

            <!-- Chart -->
            <div class="bg-white rounded-xl border shadow-sm p-5">
                <apexchart type="line" height="320" :options="chartOptions" :series="chartSeries" />
            </div>

            <!-- Tabel rekap -->
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b">
                        <tr class="text-left text-xs text-gray-500 uppercase tracking-wide">
                            <th class="px-4 py-2">{{ mode === 'weekly' ? 'Minggu' : 'Bulan' }}</th>
                            <th class="px-4 py-2 text-right">Pemasukan</th>
                            <th class="px-4 py-2 text-right">Pengeluaran</th>
                            <th class="px-4 py-2 text-right">Net</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="(r, i) in rows" :key="i">
                            <td class="px-4 py-2 font-medium text-gray-800">{{ r.label }}</td>
                            <td class="px-4 py-2 text-right font-mono text-green-600">{{ fmtRp(r.income) }}</td>
                            <td class="px-4 py-2 text-right font-mono text-red-600">{{ fmtRp(r.expense) }}</td>
                            <td class="px-4 py-2 text-right font-mono font-semibold" :class="r.net >= 0 ? 'text-gray-900' : 'text-red-600'">{{ fmtRp(r.net) }}</td>
                        </tr>
                    </tbody>
                    <tfoot class="border-t-2 border-gray-800 bg-gray-50 font-bold">
                        <tr>
                            <td class="px-4 py-2">TOTAL</td>
                            <td class="px-4 py-2 text-right font-mono text-green-700">{{ fmtRp(totals.income) }}</td>
                            <td class="px-4 py-2 text-right font-mono text-red-700">{{ fmtRp(totals.expense) }}</td>
                            <td class="px-4 py-2 text-right font-mono">{{ fmtRp(totals.net) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
