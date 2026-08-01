<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { fmtRp } from '@/lib/fmt'
import { confirm } from '@/lib/confirm'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Badge } from '@/Components/ui/badge'
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/Components/ui/select'
import {
    Table, TableBody, TableCell, TableFooter, TableHead, TableHeader, TableRow, TableEmpty,
} from '@/Components/ui/table'

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
async function bayar() {
    if (!payForm.cash_account_id) return
    if (!(await confirm({
        title: `Bayar gajian periode ${props.period}?`,
        description: 'Setelah dibayar, berkas ini terkunci — perubahan lebih lanjut hanya lewat Batalkan.',
        confirmLabel: 'Bayar',
        destructive: false,
    }))) return

    payForm.transform(data => ({
        ...data,
        overrides: Object.entries(overrides.value).map(([employee_advance_id, potongan]) => ({
            employee_advance_id: Number(employee_advance_id), potongan,
        })),
    })).post(route('payrolls.pay', props.period))
}

async function batalkan(p) {
    if (await confirm({
        title: `Batalkan gajian ${p.period}?`,
        description: 'Jurnal yang sudah dibukukan akan dihapus, status kembali ke draft.',
        confirmLabel: 'Batalkan',
    })) {
        router.post(route('payrolls.cancel', p.id))
    }
}

const totalNet = computed(() => props.draft.reduce((s, r) => s + netAmountTampil(r), 0))

const STATUS_LABEL = { draft: 'Draft', paid: 'Dibayar' }
</script>

<template>
    <Head title="Gajian" />
    <AuthenticatedLayout>
        <template #header>
            <h1 class="text-base font-semibold text-gray-800">Gajian</h1>
        </template>

        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-4">
            <!-- Buka periode -->
            <div class="bg-white rounded-xl border shadow-sm p-4 flex flex-wrap items-center gap-3">
                <Button @click="bukaPeriode(currentMonth)">Buka Periode {{ currentMonth }}</Button>
                <span class="text-xs text-gray-400">atau pilih periode lain:</span>
                <Input v-model="periodeInput" type="month" class="w-40" />
                <Button variant="outline" @click="bukaPeriode(periodeInput)">Buka</Button>
            </div>

            <!-- Daftar periode -->
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Periode</TableHead>
                            <TableHead>Status</TableHead>
                            <TableHead class="w-40"></TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableEmpty v-if="!periods.length" :colspan="3">
                            Belum ada gajian yang pernah diproses.
                        </TableEmpty>
                        <TableRow v-for="p in periods" :key="p.period">
                            <TableCell class="font-medium font-mono">{{ p.period }}</TableCell>
                            <TableCell>
                                <Badge :variant="p.status === 'paid' ? 'default' : 'secondary'">
                                    {{ STATUS_LABEL[p.status] ?? p.status }}
                                </Badge>
                            </TableCell>
                            <TableCell class="text-right space-x-1">
                                <Button variant="ghost" size="sm" @click="bukaPeriode(p.period)">Buka</Button>
                                <Button
                                    v-if="p.status === 'paid'"
                                    variant="ghost" size="sm"
                                    class="text-destructive hover:text-destructive"
                                    @click="batalkan(p)"
                                >
                                    Batalkan
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <!-- Draft periode terbuka -->
            <div v-if="period" class="bg-white rounded-xl border shadow-sm p-5 space-y-4">
                <h2 class="font-semibold text-gray-800">Draft Gajian &mdash; <span class="font-mono">{{ period }}</span></h2>

                <p v-if="!draft.length" class="text-sm text-gray-400 text-center py-10">
                    Tidak ada karyawan aktif untuk diproses periode ini.
                </p>

                <Table v-else>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Karyawan</TableHead>
                            <TableHead class="text-right">Pokok</TableHead>
                            <TableHead>Potongan Kas Bon</TableHead>
                            <TableHead class="text-right">Dibayar</TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableRow v-for="r in draft" :key="r.employee_id">
                            <TableCell class="font-medium">{{ r.employee_name }}</TableCell>
                            <TableCell class="text-right font-mono">{{ fmtRp(r.base_salary) }}</TableCell>
                            <TableCell>
                                <div v-if="r.advances.length" class="space-y-1">
                                    <div v-for="a in r.advances" :key="a.employee_advance_id" class="flex items-center gap-2 justify-end">
                                        <span class="text-xs text-gray-400">{{ a.label }}</span>
                                        <Input
                                            type="number"
                                            :value="overrideValue(a.employee_advance_id, a.potongan)"
                                            @input="setOverride(a.employee_advance_id, $event.target.value)"
                                            :max="a.potongan"
                                            min="0"
                                            class="h-7 text-xs w-24 text-right font-mono"
                                        />
                                    </div>
                                </div>
                                <span v-else class="text-xs text-gray-300">&mdash;</span>
                            </TableCell>
                            <TableCell class="text-right font-mono font-semibold">{{ fmtRp(netAmountTampil(r)) }}</TableCell>
                        </TableRow>
                    </TableBody>
                    <TableFooter>
                        <TableRow>
                            <TableCell class="font-semibold">TOTAL</TableCell>
                            <TableCell></TableCell>
                            <TableCell></TableCell>
                            <TableCell class="text-right font-mono font-bold">{{ fmtRp(totalNet) }}</TableCell>
                        </TableRow>
                    </TableFooter>
                </Table>

                <div v-if="draft.length" class="flex flex-wrap gap-2 items-center pt-3 border-t">
                    <Label class="text-sm text-gray-500">Bayar dari</Label>
                    <Select v-model="payForm.cash_account_id">
                        <SelectTrigger class="w-52">
                            <SelectValue placeholder="Pilih akun kas" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-for="a in cashAccounts" :key="a.id" :value="a.id">{{ a.name }}</SelectItem>
                        </SelectContent>
                    </Select>
                    <Button :disabled="!payForm.cash_account_id || payForm.processing" @click="bayar">
                        Bayar Semua
                    </Button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
