<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'
import { fmtRp } from '@/lib/fmt'

const props = defineProps({
    accounts: Array,
    cashTotal: Number,
    ar: Number,
    ap: Number,
})

const chartOptions = computed(() => ({
    chart: { type: 'bar', toolbar: { show: false }, fontFamily: 'inherit' },
    plotOptions: { bar: { horizontal: true, borderRadius: 4, barHeight: '55%', distributed: true } },
    colors: ['#0f3460', '#1d4ed8', '#0891b2', '#0d9488', '#65a30d', '#ca8a04'],
    dataLabels: { enabled: true, formatter: (v) => fmtRp(v) },
    xaxis: { categories: props.accounts.map(a => a.name), labels: { show: false } },
    legend: { show: false },
    grid: { borderColor: '#eef2f7' },
    tooltip: { y: { formatter: (v) => fmtRp(v) } },
}))
const chartSeries = computed(() => [{ name: 'Saldo', data: props.accounts.map(a => a.saldo) }])
</script>

<template>
    <Head title="Saldo Akun" />

    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-base font-semibold text-gray-800">Saldo per Akun</h1>
        </template>

        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-5">
            <!-- Ringkasan -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                <div class="bg-white rounded-xl border shadow-sm p-4">
                    <p class="text-xs text-gray-500">Total Saldo Kas & Bank</p>
                    <p class="text-lg font-bold text-gray-900 mt-1">{{ fmtRp(cashTotal) }}</p>
                </div>
                <div class="bg-white rounded-xl border shadow-sm p-4">
                    <p class="text-xs text-gray-500">Piutang Usaha (AR)</p>
                    <p class="text-lg font-bold text-blue-600 mt-1">{{ fmtRp(ar) }}</p>
                    <p class="text-[11px] text-gray-400">belum tertagih</p>
                </div>
                <div class="bg-white rounded-xl border shadow-sm p-4">
                    <p class="text-xs text-gray-500">Hutang Usaha (AP)</p>
                    <p class="text-lg font-bold text-red-600 mt-1">{{ fmtRp(ap) }}</p>
                    <p class="text-[11px] text-gray-400">belum dibayar</p>
                </div>
            </div>

            <!-- Saldo per akun kas -->
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b">
                    <h2 class="text-sm font-semibold text-gray-800">Akun Kas & Bank</h2>
                </div>
                <div class="divide-y">
                    <div v-for="a in accounts" :key="a.name" class="px-5 py-4">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                                    :class="a.type === 'cash' ? 'bg-amber-100 text-amber-700' : 'bg-blue-100 text-blue-700'">
                                    {{ a.type === 'cash' ? 'Kas' : 'Bank' }}
                                </span>
                                <span class="font-medium text-gray-800">{{ a.name }}</span>
                                <span class="text-xs text-gray-400">· {{ a.count }} transaksi</span>
                            </div>
                            <span class="font-mono font-bold text-lg" :class="a.saldo < 0 ? 'text-red-600' : 'text-gray-900'">{{ fmtRp(a.saldo) }}</span>
                        </div>
                        <div class="flex gap-4 mt-1.5 text-xs text-gray-500 ml-1">
                            <span v-if="a.opening">Saldo awal: <span class="font-mono">{{ fmtRp(a.opening) }}</span></span>
                            <span class="text-green-600">Masuk: <span class="font-mono">+{{ fmtRp(a.masuk) }}</span></span>
                            <span class="text-red-600">Keluar: <span class="font-mono">−{{ fmtRp(a.keluar) }}</span></span>
                        </div>
                    </div>
                </div>
                <div class="px-5 py-3 border-t bg-gray-50 flex items-center justify-between font-bold">
                    <span class="text-gray-800">TOTAL KAS & BANK</span>
                    <span class="font-mono text-gray-900">{{ fmtRp(cashTotal) }}</span>
                </div>
            </div>

            <!-- Chart saldo per akun -->
            <div v-if="accounts.length" class="bg-white rounded-xl border shadow-sm p-5">
                <h2 class="text-sm font-semibold text-gray-800 mb-3">Perbandingan Saldo per Akun</h2>
                <apexchart type="bar" :height="80 + accounts.length * 44" :options="chartOptions" :series="chartSeries" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
