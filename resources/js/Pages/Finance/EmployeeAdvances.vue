<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { fmtRp } from '@/lib/fmt'

const props = defineProps({ employees: Array, advances: Array, cashAccounts: Array })

const form = useForm({
    employee_id: '', date: new Date().toISOString().slice(0, 10),
    amount: '', cash_account_id: '', note: '',
})
function submit() {
    form.post(route('employee-advances.store'), { onSuccess: () => form.reset('amount', 'note') })
}
function hapus(advance) {
    if (!advance.bisa_dihapus) return
    if (!confirm(`Hapus kas bon ${advance.employee}?`)) return
    router.delete(route('employee-advances.destroy', advance.id))
}
</script>

<template>
    <Head title="Kas Bon" />
    <AuthenticatedLayout>
        <div class="p-6 max-w-4xl mx-auto space-y-6">
            <h1 class="text-lg font-bold">Kas Bon Karyawan</h1>

            <form @submit.prevent="submit" class="border rounded p-4 grid grid-cols-2 gap-3 text-sm">
                <select v-model="form.employee_id" class="border rounded px-2 py-1" required>
                    <option value="" disabled>Pilih karyawan</option>
                    <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
                </select>
                <input v-model="form.date" type="date" class="border rounded px-2 py-1" required />
                <input v-model="form.amount" type="number" placeholder="Nominal" class="border rounded px-2 py-1" required />
                <select v-model="form.cash_account_id" class="border rounded px-2 py-1" required>
                    <option value="" disabled>Dari akun kas</option>
                    <option v-for="a in cashAccounts" :key="a.id" :value="a.id">{{ a.name }}</option>
                </select>
                <input v-model="form.note" placeholder="Catatan (opsional)" class="border rounded px-2 py-1 col-span-2" />
                <button type="submit" class="col-span-2 bg-indigo-600 text-white rounded py-1.5">Catat Kas Bon</button>
            </form>

            <table class="w-full text-sm border">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="text-left p-2">Karyawan</th><th class="text-left p-2">Tanggal</th>
                        <th class="text-right p-2">Nominal</th><th class="text-right p-2">Sisa</th>
                        <th class="p-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="a in advances" :key="a.id" class="border-t">
                        <td class="p-2">{{ a.employee }}</td>
                        <td class="p-2">{{ a.date }}</td>
                        <td class="p-2 text-right font-mono">{{ fmtRp(a.amount) }}</td>
                        <td class="p-2 text-right font-mono" :class="a.sisa > 0 ? 'text-amber-600' : 'text-gray-400'">
                            {{ fmtRp(a.sisa) }}
                        </td>
                        <td class="p-2 text-right">
                            <button v-if="a.bisa_dihapus" @click="hapus(a)" class="text-red-600 text-xs">Hapus</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </AuthenticatedLayout>
</template>
