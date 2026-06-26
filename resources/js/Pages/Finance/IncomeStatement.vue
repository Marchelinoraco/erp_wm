<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import { fmtRp } from '@/lib/fmt'

const props = defineProps({
    year:         Number,
    years:        Array,
    lines:        Array,
    totalRevenue: Number,
    totalCogs:    Number,
    grossProfit:  Number,
    grossMargin:  { type: Number, default: null },
    opex:              Array,
    totalOpex:         Number,
    depreciation:      { type: Array, default: () => [] },
    totalDepreciation: { type: Number, default: 0 },
    otherIncome:       Number,
    netProfit:    Number,
    netMargin:    { type: Number, default: null },
})

function changeYear(e) {
    router.get(route('finance.income-statement'), { year: e.target.value }, { preserveState: false })
}

const chartOptions = computed(() => ({
    chart: { type: 'bar', fontFamily: 'inherit', toolbar: { show: false } },
    plotOptions: { bar: { columnWidth: '60%', borderRadius: 3 } },
    colors: ['#0f3460', '#c0272d'],
    xaxis: { categories: props.lines.map(l => l.label) },
    yaxis: { labels: { formatter: v => 'Rp ' + (v / 1_000_000).toFixed(0) + ' jt' } },
    tooltip: { y: { formatter: v => fmtRp(v) } },
    legend: { position: 'bottom' },
    dataLabels: { enabled: false },
}))

const chartSeries = computed(() => [
    { name: 'Penjualan', data: props.lines.map(l => l.revenue) },
    { name: 'HPP',       data: props.lines.map(l => l.cogs) },
])

function marginClass(pct) {
    if (pct === null) return 'bg-gray-100 text-gray-500'
    if (pct >= 20)   return 'bg-emerald-100 text-emerald-700'
    if (pct >= 10)   return 'bg-amber-100 text-amber-700'
    return 'bg-red-100 text-red-700'
}
</script>

<template>
    <Head title="Laba Rugi" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-base font-semibold text-gray-800">Laporan Laba Rugi</h1>
                <div class="flex items-center gap-2">
                    <select :value="year" @change="changeYear"
                        class="text-sm border rounded-md px-2 py-1 bg-white">
                        <option v-for="y in years" :key="y" :value="y">Tahun {{ y }}</option>
                    </select>
                    <a :href="route('finance.income-statement.pdf', { year })" target="_blank"
                        class="text-sm px-3 py-1 rounded-md border bg-white hover:bg-gray-50">⬇ PDF</a>
                </div>
            </div>
        </template>

        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">

            <!-- Summary cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white rounded-xl border shadow-sm p-4">
                    <p class="text-xs text-gray-500 mb-1">Total Penjualan</p>
                    <p class="text-lg font-bold font-mono text-blue-800">{{ fmtRp(totalRevenue) }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">dari Invoice (AR)</p>
                </div>
                <div class="bg-white rounded-xl border shadow-sm p-4">
                    <p class="text-xs text-gray-500 mb-1">HPP / COGS</p>
                    <p class="text-lg font-bold font-mono text-red-700">{{ fmtRp(totalCogs) }}</p>
                    <p class="text-xs text-gray-400 mt-0.5">dari Bill (AP)</p>
                </div>
                <div class="bg-white rounded-xl border shadow-sm p-4">
                    <p class="text-xs text-gray-500 mb-1">Laba Kotor</p>
                    <p class="text-lg font-bold font-mono" :class="grossProfit >= 0 ? 'text-emerald-700' : 'text-red-700'">
                        {{ fmtRp(grossProfit) }}
                    </p>
                    <p v-if="grossMargin !== null" class="text-xs text-gray-400 mt-0.5">Margin {{ grossMargin }}%</p>
                </div>
                <div class="rounded-xl border shadow-sm p-4"
                    :class="netProfit >= 0 ? 'bg-emerald-50 border-emerald-200' : 'bg-red-50 border-red-200'">
                    <p class="text-xs text-gray-500 mb-1">Laba Bersih</p>
                    <p class="text-lg font-bold font-mono" :class="netProfit >= 0 ? 'text-emerald-700' : 'text-red-700'">
                        {{ fmtRp(netProfit) }}
                    </p>
                    <p v-if="netMargin !== null" class="text-xs mt-0.5"
                        :class="netProfit >= 0 ? 'text-emerald-600' : 'text-red-500'">
                        Net Margin {{ netMargin }}%
                    </p>
                </div>
            </div>

            <!-- Penjualan & HPP per Lini Bisnis -->
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b bg-blue-50/50">
                    <h2 class="text-sm font-bold text-blue-800 uppercase tracking-wide">Penjualan &amp; HPP per Lini Bisnis</h2>
                </div>

                <div v-if="!lines.length" class="px-5 py-8 text-sm text-gray-400 text-center">
                    Belum ada invoice atau bill untuk tahun {{ year }}.<br>
                    <span class="text-xs">Pastikan tour sudah memiliki Invoice (AR) dan Bill (AP) yang dicatat.</span>
                </div>

                <table v-else class="w-full text-sm">
                    <thead>
                        <tr class="border-b bg-gray-50 text-gray-600">
                            <th class="px-5 py-2.5 text-left font-semibold">Lini Bisnis</th>
                            <th class="px-4 py-2.5 text-right font-semibold">Penjualan</th>
                            <th class="px-4 py-2.5 text-right font-semibold">HPP</th>
                            <th class="px-4 py-2.5 text-right font-semibold">Laba Kotor</th>
                            <th class="px-5 py-2.5 text-right font-semibold">Margin</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        <tr v-for="(l, i) in lines" :key="l.key"
                            :class="i % 2 === 1 ? 'bg-gray-50/40' : ''">
                            <td class="px-5 py-3 text-gray-700 font-medium">{{ l.label }}</td>
                            <td class="px-4 py-3 text-right font-mono">{{ fmtRp(l.revenue) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-red-700">{{ fmtRp(l.cogs) }}</td>
                            <td class="px-4 py-3 text-right font-mono"
                                :class="l.gross >= 0 ? 'text-emerald-700' : 'text-red-700'">
                                {{ fmtRp(l.gross) }}
                            </td>
                            <td class="px-5 py-3 text-right">
                                <span v-if="l.gross_pct !== null"
                                    class="text-xs px-2 py-0.5 rounded-full font-semibold"
                                    :class="marginClass(l.gross_pct)">
                                    {{ l.gross_pct }}%
                                </span>
                                <span v-else class="text-gray-400">—</span>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="bg-blue-50 border-t-2 border-blue-200 font-bold">
                            <td class="px-5 py-3 text-blue-800">TOTAL</td>
                            <td class="px-4 py-3 text-right font-mono text-blue-800">{{ fmtRp(totalRevenue) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-red-700">{{ fmtRp(totalCogs) }}</td>
                            <td class="px-4 py-3 text-right font-mono"
                                :class="grossProfit >= 0 ? 'text-emerald-700' : 'text-red-700'">
                                {{ fmtRp(grossProfit) }}
                            </td>
                            <td class="px-5 py-3 text-right">
                                <span v-if="grossMargin !== null" class="text-sm font-bold"
                                    :class="grossMargin >= 20 ? 'text-emerald-700' : grossMargin >= 10 ? 'text-amber-700' : 'text-red-700'">
                                    {{ grossMargin }}%
                                </span>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Chart: Penjualan vs HPP -->
            <div v-if="lines.length" class="bg-white rounded-xl border shadow-sm p-5">
                <h2 class="text-sm font-semibold text-gray-800 mb-3">Penjualan vs HPP per Lini Bisnis</h2>
                <apexchart type="bar" height="240" :options="chartOptions" :series="chartSeries" />
            </div>

            <!-- Biaya Operasional -->
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b bg-amber-50/50">
                    <h2 class="text-sm font-bold text-amber-800 uppercase tracking-wide">Biaya Operasional</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Dari transaksi kas manual (keluar)</p>
                </div>
                <div v-if="!opex.length" class="px-5 py-8 text-sm text-gray-400 text-center">
                    Belum ada transaksi biaya operasional manual untuk tahun {{ year }}.
                </div>
                <div v-else class="divide-y">
                    <div v-for="item in opex" :key="item.name"
                        class="px-5 py-2.5 flex justify-between text-sm">
                        <span class="text-gray-600">{{ item.name }}</span>
                        <span class="font-mono text-red-700">{{ fmtRp(item.total) }}</span>
                    </div>
                </div>
                <div v-if="opex.length" class="px-5 py-3 border-t bg-amber-50/50 flex justify-between font-bold">
                    <span class="text-amber-800">TOTAL BIAYA OPERASIONAL</span>
                    <span class="font-mono text-red-700">{{ fmtRp(totalOpex) }}</span>
                </div>
            </div>

            <!-- Beban Penyusutan Aset Tetap -->
            <div v-if="depreciation.length" class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b bg-purple-50/50">
                    <h2 class="text-sm font-bold text-purple-800 uppercase tracking-wide">Beban Penyusutan Aset Tetap</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Non-kas · Garis lurus · Dihitung otomatis dari master Aset Tetap</p>
                </div>
                <div class="divide-y">
                    <div v-for="item in depreciation" :key="item.name" class="px-5 py-2.5 flex justify-between text-sm">
                        <span class="text-gray-600">Penyusutan — {{ item.name }}</span>
                        <span class="font-mono text-red-700">{{ fmtRp(item.total) }}</span>
                    </div>
                </div>
                <div class="px-5 py-3 border-t bg-purple-50/50 flex justify-between font-bold">
                    <span class="text-purple-800">TOTAL BEBAN PENYUSUTAN</span>
                    <span class="font-mono text-red-700">{{ fmtRp(totalDepreciation) }}</span>
                </div>
            </div>

            <!-- Ringkasan Laba Rugi -->
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b bg-gray-50">
                    <h2 class="text-sm font-bold text-gray-700 uppercase tracking-wide">
                        Ringkasan Laba Rugi — Tahun {{ year }}
                    </h2>
                </div>
                <div class="divide-y">
                    <div class="px-5 py-3 flex justify-between text-sm">
                        <span class="text-gray-600">Laba Kotor Penjualan</span>
                        <span class="font-mono font-semibold"
                            :class="grossProfit >= 0 ? 'text-emerald-700' : 'text-red-700'">
                            {{ fmtRp(grossProfit) }}
                        </span>
                    </div>
                    <div class="px-5 py-3 flex justify-between text-sm">
                        <span class="text-gray-600">(–) Total Biaya Operasional</span>
                        <span class="font-mono text-red-700">{{ fmtRp(totalOpex) }}</span>
                    </div>
                    <div v-if="totalDepreciation > 0" class="px-5 py-3 flex justify-between text-sm">
                        <span class="text-gray-600">(–) Beban Penyusutan</span>
                        <span class="font-mono text-red-700">{{ fmtRp(totalDepreciation) }}</span>
                    </div>
                    <div v-if="otherIncome > 0" class="px-5 py-3 flex justify-between text-sm">
                        <span class="text-gray-600">(+) Pendapatan Lain-lain</span>
                        <span class="font-mono text-blue-700">{{ fmtRp(otherIncome) }}</span>
                    </div>
                </div>
                <div class="px-5 py-4 border-t-2 flex items-center justify-between font-bold"
                    :class="netProfit >= 0 ? 'bg-emerald-50 border-emerald-300' : 'bg-red-50 border-red-300'">
                    <div>
                        <span :class="netProfit >= 0 ? 'text-emerald-800' : 'text-red-800'">LABA BERSIH</span>
                        <span v-if="netMargin !== null" class="ml-2 text-xs font-normal"
                            :class="netProfit >= 0 ? 'text-emerald-600' : 'text-red-600'">
                            Net Margin {{ netMargin }}%
                        </span>
                    </div>
                    <span class="font-mono text-xl"
                        :class="netProfit >= 0 ? 'text-emerald-700' : 'text-red-700'">
                        {{ fmtRp(netProfit) }}
                    </span>
                </div>
            </div>

            <p class="text-xs text-gray-400 text-center pb-2">
                Penjualan = Invoice AR · HPP = Bill AP · Biaya Operasional = kas keluar manual ·
                Penyusutan = garis lurus, dihitung otomatis dari master Aset Tetap
            </p>
        </div>
    </AuthenticatedLayout>
</template>
