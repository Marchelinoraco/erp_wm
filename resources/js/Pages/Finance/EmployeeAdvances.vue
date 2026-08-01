<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { ref } from 'vue'
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
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow, TableEmpty,
} from '@/Components/ui/table'
import {
    Dialog, DialogContent, DialogHeader, DialogTitle,
} from '@/Components/ui/dialog'

const props = defineProps({ employees: Array, advances: Array, cashAccounts: Array })

const showAddDialog = ref(false)
const form = useForm({
    employee_id: '', date: new Date().toISOString().slice(0, 10),
    amount: '', cash_account_id: '', note: '',
})
function openAdd() {
    form.reset()
    form.date = new Date().toISOString().slice(0, 10)
    form.clearErrors()
    showAddDialog.value = true
}
function submit() {
    form.post(route('employee-advances.store'), {
        onSuccess: () => { showAddDialog.value = false },
    })
}

async function hapus(advance) {
    if (!advance.bisa_dihapus) return
    if (await confirm({
        title: `Hapus kas bon ${advance.employee}?`,
        description: 'Transaksi jurnal yang menyertainya akan ikut dihapus.',
        confirmLabel: 'Hapus',
    })) {
        router.delete(route('employee-advances.destroy', advance.id))
    }
}
</script>

<template>
    <Head title="Kas Bon" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-base font-semibold text-gray-800">Kas Bon Karyawan</h1>
                <Button @click="openAdd">+ Catat Kas Bon</Button>
            </div>
        </template>

        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-4">
            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead>Karyawan</TableHead>
                            <TableHead>Tanggal</TableHead>
                            <TableHead class="text-right">Nominal</TableHead>
                            <TableHead class="text-right">Sisa</TableHead>
                            <TableHead class="w-20"></TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        <TableEmpty v-if="!advances.length" :colspan="5">
                            Belum ada kas bon. Klik &ldquo;+ Catat Kas Bon&rdquo; untuk menambah.
                        </TableEmpty>
                        <TableRow v-for="a in advances" :key="a.id">
                            <TableCell class="font-medium">{{ a.employee }}</TableCell>
                            <TableCell>{{ a.date }}</TableCell>
                            <TableCell class="text-right font-mono">{{ fmtRp(a.amount) }}</TableCell>
                            <TableCell class="text-right">
                                <Badge v-if="a.sisa > 0" variant="destructive" class="font-mono">{{ fmtRp(a.sisa) }}</Badge>
                                <Badge v-else variant="secondary" class="font-mono">Lunas</Badge>
                            </TableCell>
                            <TableCell class="text-right">
                                <Button v-if="a.bisa_dihapus" variant="ghost" size="sm" class="text-destructive hover:text-destructive" @click="hapus(a)">
                                    Hapus
                                </Button>
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>
        </div>

        <!-- ── Dialog: Catat Kas Bon ── -->
        <Dialog v-model:open="showAddDialog">
            <DialogContent class="max-w-md">
                <DialogHeader>
                    <DialogTitle>Catat Kas Bon</DialogTitle>
                </DialogHeader>
                <form @submit.prevent="submit" class="space-y-3 mt-2">
                    <div class="space-y-1.5">
                        <Label>Karyawan</Label>
                        <Select v-model="form.employee_id">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Pilih karyawan" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.employee_id" class="text-xs text-red-600">{{ form.errors.employee_id }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Tanggal</Label>
                        <Input v-model="form.date" type="date" required />
                    </div>
                    <div class="space-y-1.5">
                        <Label>Nominal (Rp)</Label>
                        <Input v-model="form.amount" type="number" min="1" class="font-mono" required />
                        <p v-if="form.errors.amount" class="text-xs text-red-600">{{ form.errors.amount }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Dari Akun Kas</Label>
                        <Select v-model="form.cash_account_id">
                            <SelectTrigger class="w-full">
                                <SelectValue placeholder="Pilih akun kas" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="a in cashAccounts" :key="a.id" :value="a.id">{{ a.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <p v-if="form.errors.cash_account_id" class="text-xs text-red-600">{{ form.errors.cash_account_id }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Catatan (opsional)</Label>
                        <Input v-model="form.note" />
                    </div>
                    <div class="flex justify-end gap-2 pt-1">
                        <Button type="button" variant="outline" @click="showAddDialog = false">Batal</Button>
                        <Button type="submit" :disabled="form.processing">Catat Kas Bon</Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </AuthenticatedLayout>
</template>
