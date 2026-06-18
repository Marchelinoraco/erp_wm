<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, router } from '@inertiajs/vue3'
import { fmtRp } from '@/lib/fmt'

const props = defineProps({
    month: String,
    entries: Array,
    totals: Object,
})

function fmtDate(d) {
    return new Date(d).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' })
}
function changeMonth(e) {
    router.get(route('finance.journal'), { month: e.target.value }, { preserveState: false })
}
const balanced = props.totals.debit === props.totals.credit
</script>

<template>
    <Head title="Jurnal" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-base font-semibold text-gray-800">Jurnal Pencatatan</h1>
                <input type="month" :value="month" @change="changeMonth" class="text-sm border rounded-md px-2 py-1" />
            </div>
        </template>

        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-4">
            <!-- Bukti keseimbangan -->
            <div class="rounded-xl border shadow-sm p-4 flex items-center justify-between"
                :class="balanced ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200'">
                <div>
                    <p class="text-xs text-gray-500">Prinsip Keseimbangan</p>
                    <p class="text-sm font-semibold" :class="balanced ? 'text-green-700' : 'text-red-700'">
                        {{ balanced ? '✓ Debit = Kredit (seimbang)' : '✗ Tidak seimbang!' }}
                    </p>
                </div>
                <div class="flex gap-6 text-right">
                    <div>
                        <p class="text-xs text-gray-500">Total Debit</p>
                        <p class="font-mono font-bold text-gray-900">{{ fmtRp(totals.debit) }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-gray-500">Total Kredit</p>
                        <p class="font-mono font-bold text-gray-900">{{ fmtRp(totals.credit) }}</p>
                    </div>
                </div>
            </div>

            <!-- Jurnal -->
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 border-b">
                        <tr class="text-left text-xs text-gray-500 uppercase tracking-wide">
                            <th class="px-4 py-2 w-24">Tanggal</th>
                            <th class="px-4 py-2">Akun & Keterangan</th>
                            <th class="px-4 py-2 text-right w-32">Debit</th>
                            <th class="px-4 py-2 text-right w-32">Kredit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-if="entries.length">
                            <template v-for="(e, i) in entries" :key="i">
                                <tr v-for="(ln, li) in e.lines" :key="li" :class="li === 0 ? 'border-t' : ''">
                                    <td class="px-4 py-1.5 text-gray-500 align-top">{{ li === 0 ? fmtDate(e.date) : '' }}</td>
                                    <td class="px-4 py-1.5" :class="ln.credit > 0 ? 'pl-10 text-gray-600' : 'font-medium text-gray-800'">
                                        {{ ln.account }}
                                    </td>
                                    <td class="px-4 py-1.5 text-right font-mono">{{ ln.debit > 0 ? fmtRp(ln.debit) : '' }}</td>
                                    <td class="px-4 py-1.5 text-right font-mono">{{ ln.credit > 0 ? fmtRp(ln.credit) : '' }}</td>
                                </tr>
                                <tr>
                                    <td></td>
                                    <td class="px-4 pb-2 text-xs text-gray-400 italic" colspan="3">{{ e.description }} <span class="not-italic">· {{ e.ref }}</span></td>
                                </tr>
                            </template>
                        </template>
                        <tr v-else>
                            <td colspan="4" class="px-4 py-10 text-center text-gray-400">Belum ada jurnal pada bulan ini.</td>
                        </tr>
                    </tbody>
                    <tfoot v-if="entries.length" class="border-t-2 border-gray-800 bg-gray-50 font-bold">
                        <tr>
                            <td class="px-4 py-2" colspan="2">TOTAL</td>
                            <td class="px-4 py-2 text-right font-mono">{{ fmtRp(totals.debit) }}</td>
                            <td class="px-4 py-2 text-right font-mono">{{ fmtRp(totals.credit) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
