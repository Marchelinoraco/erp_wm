<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import { fmtRp } from '@/lib/fmt'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Badge } from '@/Components/ui/badge'
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/Components/ui/select'
import {
    Card, CardHeader, CardTitle, CardContent,
} from '@/Components/ui/card'
import {
    Dialog, DialogContent, DialogHeader, DialogTitle,
} from '@/Components/ui/dialog'

const props = defineProps({ employees: Array })

// ── Tambah karyawan ──────────────────────────────────────────────
const showAddDialog = ref(false)
const addForm = useForm({
    name: '', position: '', phone: '', email: '', base_salary: '',
    join_date: '', bank_name: '', bank_account_number: '', bank_account_holder: '', notes: '',
})
function openAdd() {
    addForm.reset()
    addForm.clearErrors()
    showAddDialog.value = true
}
function submitAdd() {
    addForm.post(route('employees.store'), {
        onSuccess: () => { showAddDialog.value = false },
    })
}

// ── Ubah karyawan ────────────────────────────────────────────────
const showEditDialog = ref(false)
const editForm = useForm({
    name: '', position: '', phone: '', email: '', base_salary: '',
    join_date: '', bank_name: '', bank_account_number: '', bank_account_holder: '',
    is_active: true, notes: '',
})
function startEdit(e) {
    Object.assign(editForm, {
        id: e.id,
        name: e.name, position: e.position ?? '', phone: e.phone ?? '', email: e.email ?? '',
        base_salary: e.base_salary, join_date: e.join_date ?? '', bank_name: e.bank_name ?? '',
        bank_account_number: e.bank_account_number ?? '', bank_account_holder: e.bank_account_holder ?? '',
        is_active: e.is_active, notes: e.notes ?? '',
    })
    editForm.clearErrors()
    showEditDialog.value = true
}
function submitEdit() {
    editForm.patch(route('employees.update', editForm.id), {
        onSuccess: () => { showEditDialog.value = false },
    })
}

// ── Komponen tetap (tunjangan/potongan) ──────────────────────────
const componentForms = {}
function componentForm(employeeId) {
    if (!componentForms[employeeId]) {
        componentForms[employeeId] = useForm({ name: '', type: 'tunjangan', amount: '' })
    }
    return componentForms[employeeId]
}
function addComponent(employeeId) {
    componentForm(employeeId).post(route('employees.components.store', employeeId), {
        onSuccess: () => { componentForm(employeeId).reset() },
    })
}
</script>

<template>
    <Head title="Karyawan" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-base font-semibold text-gray-800">Karyawan</h1>
                <Button @click="openAdd">+ Tambah Karyawan</Button>
            </div>
        </template>

        <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-4">
            <p v-if="!employees.length" class="text-sm text-gray-400 text-center py-14 bg-white rounded-xl border shadow-sm">
                Belum ada karyawan. Klik &ldquo;+ Tambah Karyawan&rdquo; untuk menambah.
            </p>

            <Card v-for="e in employees" :key="e.id" class="py-0">
                <CardContent class="p-5 space-y-4">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-semibold text-gray-900">{{ e.name }}</span>
                                <Badge v-if="!e.is_active" variant="secondary">Nonaktif</Badge>
                            </div>
                            <p class="text-sm text-gray-500 mt-0.5">
                                {{ e.position || '—' }} &middot;
                                <span class="font-mono">{{ fmtRp(e.base_salary) }}</span> / bulan
                            </p>
                            <p v-if="e.sisa_kas_bon > 0" class="text-xs font-mono text-amber-600 mt-1">
                                Sisa kas bon: {{ fmtRp(e.sisa_kas_bon) }}
                            </p>
                        </div>
                        <Button variant="outline" size="sm" class="shrink-0" @click="startEdit(e)">Ubah</Button>
                    </div>

                    <div class="border-t pt-3">
                        <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-2">Komponen Tetap</p>

                        <div v-if="e.components.length" class="flex flex-wrap gap-1.5 mb-3">
                            <Badge
                                v-for="c in e.components"
                                :key="c.id"
                                :variant="c.type === 'tunjangan' ? 'default' : 'destructive'"
                                :class="{ 'opacity-40': !c.is_active }"
                            >
                                {{ c.name }} · {{ fmtRp(c.amount) }}
                            </Badge>
                        </div>
                        <p v-else class="text-xs text-gray-400 mb-3">Belum ada komponen tetap.</p>

                        <form @submit.prevent="addComponent(e.id)" class="flex flex-wrap gap-2 items-center">
                            <Input
                                v-model="componentForm(e.id).name"
                                placeholder="Nama komponen"
                                class="h-8 text-xs w-40"
                            />
                            <Select v-model="componentForm(e.id).type">
                                <SelectTrigger class="h-8 text-xs w-32">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="tunjangan">Tunjangan</SelectItem>
                                    <SelectItem value="potongan">Potongan</SelectItem>
                                </SelectContent>
                            </Select>
                            <Input
                                v-model="componentForm(e.id).amount"
                                type="number"
                                placeholder="Nominal"
                                class="h-8 text-xs w-28 font-mono"
                            />
                            <Button type="submit" size="sm" variant="outline" :disabled="componentForm(e.id).processing">
                                + Tambah
                            </Button>
                        </form>

                        <!-- Item Cepat #3, fix wave final review 2026-08-01: keputusan
                             pemilik produk — "Potongan" hanya untuk penyesuaian
                             prorata, BUKAN withholding pajak/BPJS/koperasi (itu
                             butuh akun liabilitas terpisah, di luar cakupan rilis
                             ini). Teks statis saja, tidak ada validasi/logika baru. -->
                        <p class="text-xs text-gray-400 mt-2">
                            Potongan mengurangi beban gaji langsung — pakai untuk penyesuaian prorata (mis. masuk/keluar tengah bulan), BUKAN untuk withholding pajak/BPJS/koperasi (itu perlu dicatat sebagai utang, bukan potongan).
                        </p>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- ── Dialog: Tambah Karyawan ── -->
        <Dialog v-model:open="showAddDialog">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Tambah Karyawan</DialogTitle>
                </DialogHeader>
                <form @submit.prevent="submitAdd" class="grid grid-cols-2 gap-3 mt-2">
                    <div class="col-span-2 space-y-1.5">
                        <Label>Nama</Label>
                        <Input v-model="addForm.name" required />
                        <p v-if="addForm.errors.name" class="text-xs text-red-600">{{ addForm.errors.name }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Jabatan</Label>
                        <Input v-model="addForm.position" />
                    </div>
                    <div class="space-y-1.5">
                        <Label>Gaji Pokok (Rp)</Label>
                        <Input v-model="addForm.base_salary" type="number" min="0" class="font-mono" required />
                        <p v-if="addForm.errors.base_salary" class="text-xs text-red-600">{{ addForm.errors.base_salary }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Telepon</Label>
                        <Input v-model="addForm.phone" />
                    </div>
                    <div class="space-y-1.5">
                        <Label>Email</Label>
                        <Input v-model="addForm.email" type="email" />
                    </div>
                    <div class="col-span-2 space-y-1.5">
                        <Label>Tanggal Masuk</Label>
                        <Input v-model="addForm.join_date" type="date" />
                    </div>
                    <div class="space-y-1.5">
                        <Label>Nama Bank</Label>
                        <Input v-model="addForm.bank_name" />
                    </div>
                    <div class="space-y-1.5">
                        <Label>No. Rekening</Label>
                        <Input v-model="addForm.bank_account_number" />
                    </div>
                    <div class="col-span-2 space-y-1.5">
                        <Label>Atas Nama Rekening</Label>
                        <Input v-model="addForm.bank_account_holder" />
                    </div>
                    <div class="col-span-2 flex justify-end gap-2 pt-1">
                        <Button type="button" variant="outline" @click="showAddDialog = false">Batal</Button>
                        <Button type="submit" :disabled="addForm.processing">Simpan</Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <!-- ── Dialog: Ubah Karyawan ── -->
        <Dialog v-model:open="showEditDialog">
            <DialogContent class="max-w-lg">
                <DialogHeader>
                    <DialogTitle>Ubah Karyawan</DialogTitle>
                </DialogHeader>
                <form @submit.prevent="submitEdit" class="grid grid-cols-2 gap-3 mt-2">
                    <div class="col-span-2 space-y-1.5">
                        <Label>Nama</Label>
                        <Input v-model="editForm.name" required />
                    </div>
                    <div class="space-y-1.5">
                        <Label>Jabatan</Label>
                        <Input v-model="editForm.position" />
                    </div>
                    <div class="space-y-1.5">
                        <Label>Gaji Pokok (Rp)</Label>
                        <Input v-model="editForm.base_salary" type="number" min="0" class="font-mono" required />
                    </div>
                    <div class="space-y-1.5">
                        <Label>Telepon</Label>
                        <Input v-model="editForm.phone" />
                    </div>
                    <div class="space-y-1.5">
                        <Label>Email</Label>
                        <Input v-model="editForm.email" type="email" />
                    </div>
                    <div class="col-span-2 space-y-1.5">
                        <Label>Tanggal Masuk</Label>
                        <Input v-model="editForm.join_date" type="date" />
                    </div>
                    <div class="space-y-1.5">
                        <Label>Nama Bank</Label>
                        <Input v-model="editForm.bank_name" />
                    </div>
                    <div class="space-y-1.5">
                        <Label>No. Rekening</Label>
                        <Input v-model="editForm.bank_account_number" />
                    </div>
                    <div class="col-span-2 space-y-1.5">
                        <Label>Atas Nama Rekening</Label>
                        <Input v-model="editForm.bank_account_holder" />
                    </div>
                    <label class="col-span-2 flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" v-model="editForm.is_active" class="rounded border-gray-300" />
                        Karyawan aktif
                    </label>
                    <div class="col-span-2 flex justify-end gap-2 pt-1">
                        <Button type="button" variant="outline" @click="showEditDialog = false">Batal</Button>
                        <Button type="submit" :disabled="editForm.processing">Simpan</Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </AuthenticatedLayout>
</template>
