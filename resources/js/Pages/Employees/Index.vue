<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import { fmtRp } from '@/lib/fmt'

const props = defineProps({ employees: Array })

const showAddForm = ref(false)
const addForm = useForm({
    name: '', position: '', phone: '', email: '', base_salary: '',
    join_date: '', bank_name: '', bank_account_number: '', bank_account_holder: '', notes: '',
})
function submitAdd() {
    addForm.post(route('employees.store'), {
        onSuccess: () => { addForm.reset(); showAddForm.value = false },
    })
}

const editingId = ref(null)
const editForm = useForm({
    name: '', position: '', phone: '', email: '', base_salary: '',
    join_date: '', bank_name: '', bank_account_number: '', bank_account_holder: '',
    is_active: true, notes: '',
})
function startEdit(e) {
    editingId.value = e.id
    Object.assign(editForm, {
        name: e.name, position: e.position ?? '', phone: e.phone ?? '', email: e.email ?? '',
        base_salary: e.base_salary, join_date: e.join_date ?? '', bank_name: e.bank_name ?? '',
        bank_account_number: e.bank_account_number ?? '', bank_account_holder: e.bank_account_holder ?? '',
        is_active: e.is_active, notes: e.notes ?? '',
    })
}
function saveEdit(id) {
    editForm.patch(route('employees.update', id), { onSuccess: () => { editingId.value = null } })
}

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
            <h2 class="text-base font-semibold text-gray-900">Karyawan</h2>
        </template>

        <div class="p-6 max-w-5xl mx-auto space-y-6">
            <div class="flex justify-between items-center">
                <h1 class="text-lg font-bold">Karyawan</h1>
                <button @click="showAddForm = !showAddForm" class="px-3 py-1.5 bg-indigo-600 text-white rounded text-sm">
                    + Tambah Karyawan
                </button>
            </div>

            <form v-if="showAddForm" @submit.prevent="submitAdd" class="border rounded p-4 grid grid-cols-2 gap-3 text-sm">
                <input v-model="addForm.name" placeholder="Nama" class="border rounded px-2 py-1" required />
                <input v-model="addForm.position" placeholder="Jabatan" class="border rounded px-2 py-1" />
                <input v-model="addForm.phone" placeholder="Telepon" class="border rounded px-2 py-1" />
                <input v-model="addForm.email" placeholder="Email" class="border rounded px-2 py-1" />
                <input v-model="addForm.base_salary" type="number" placeholder="Gaji Pokok" class="border rounded px-2 py-1" required />
                <input v-model="addForm.join_date" type="date" class="border rounded px-2 py-1" />
                <input v-model="addForm.bank_name" placeholder="Nama Bank" class="border rounded px-2 py-1" />
                <input v-model="addForm.bank_account_number" placeholder="No. Rekening" class="border rounded px-2 py-1" />
                <input v-model="addForm.bank_account_holder" placeholder="Atas Nama" class="border rounded px-2 py-1" />
                <button type="submit" class="col-span-2 bg-indigo-600 text-white rounded py-1.5">Simpan</button>
            </form>

            <div v-for="e in employees" :key="e.id" class="border rounded p-4 text-sm space-y-2">
                <div class="flex justify-between items-center">
                    <div>
                        <p class="font-bold">{{ e.name }}</p>
                        <p class="text-gray-500">{{ e.position }} &middot; {{ fmtRp(e.base_salary) }}</p>
                    </div>
                    <div class="text-right">
                        <p v-if="e.sisa_kas_bon > 0" class="text-amber-600 font-mono text-xs">
                            Sisa kas bon: {{ fmtRp(e.sisa_kas_bon) }}
                        </p>
                        <button @click="startEdit(e)" class="text-indigo-600 text-xs">Ubah</button>
                    </div>
                </div>

                <form v-if="editingId === e.id" @submit.prevent="saveEdit(e.id)" class="grid grid-cols-2 gap-2 border-t pt-2">
                    <input v-model="editForm.name" class="border rounded px-2 py-1" />
                    <input v-model="editForm.position" class="border rounded px-2 py-1" />
                    <input v-model="editForm.base_salary" type="number" class="border rounded px-2 py-1" />
                    <label class="flex items-center gap-1">
                        <input v-model="editForm.is_active" type="checkbox" /> Aktif
                    </label>
                    <button type="submit" class="col-span-2 bg-indigo-600 text-white rounded py-1">Simpan</button>
                </form>

                <div class="border-t pt-2">
                    <p class="text-xs font-semibold text-gray-400 uppercase">Komponen Tetap</p>
                    <div v-for="c in e.components" :key="c.id" class="flex justify-between text-xs py-0.5">
                        <span>{{ c.name }} ({{ c.type }})</span>
                        <span class="font-mono">{{ fmtRp(c.amount) }}</span>
                    </div>
                    <form @submit.prevent="addComponent(e.id)" class="flex gap-1 mt-1">
                        <input v-model="componentForm(e.id).name" placeholder="Nama komponen" class="border rounded px-2 py-0.5 text-xs flex-1" />
                        <select v-model="componentForm(e.id).type" class="border rounded text-xs">
                            <option value="tunjangan">Tunjangan</option>
                            <option value="potongan">Potongan</option>
                        </select>
                        <input v-model="componentForm(e.id).amount" type="number" placeholder="Nominal" class="border rounded px-2 py-0.5 text-xs w-28" />
                        <button type="submit" class="text-indigo-600 text-xs px-2">+</button>
                    </form>
                    <!-- Item Cepat #3, fix wave final review 2026-08-01: keputusan
                         pemilik produk — "Potongan" hanya untuk penyesuaian
                         prorata, BUKAN withholding pajak/BPJS/koperasi (itu
                         butuh akun liabilitas terpisah, di luar cakupan rilis
                         ini). Teks statis saja, tidak ada validasi/logika baru. -->
                    <p class="text-[11px] text-gray-400 mt-1">
                        Potongan mengurangi beban gaji langsung — pakai untuk penyesuaian prorata (mis. masuk/keluar tengah bulan), BUKAN untuk withholding pajak/BPJS/koperasi (itu perlu dicatat sebagai utang, bukan potongan).
                    </p>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
