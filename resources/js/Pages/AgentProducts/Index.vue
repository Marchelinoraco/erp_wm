<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/Components/ui/select'
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow, TableEmpty,
} from '@/Components/ui/table'

const props = defineProps({
    supplier: Object,
    products: Array,
})

const TYPE_LABELS = {
    hotel: 'Hotel', transport: 'Transport', guide: 'Guide',
    restaurant: 'Restaurant', attraction: 'Attraction', other: 'Lainnya',
}
const UNIT_LABELS = { per_pax: 'Per Pax', per_unit: 'Per Unit', per_night: 'Per Malam' }

function fmt(val) {
    return Number(val ?? 0).toLocaleString('id-ID')
}

// ── Tambah produk ──
const showForm = ref(false)
const form = useForm({ name: '', type: 'hotel', unit: 'per_unit', cost: '' })

function submit() {
    form.post(route('agent.products.store'), {
        preserveScroll: true,
        onSuccess: () => { form.reset(); showForm.value = false },
    })
}

// ── Edit inline ──
const editingId = ref(null)
const editForm = useForm({ name: '', type: '', unit: '', cost: '' })

function startEdit(p) {
    editingId.value = p.id
    editForm.name = p.name
    editForm.type = p.type
    editForm.unit = p.unit
    editForm.cost = p.price_status === 'pending' ? p.pending_cost : p.cost
}
function saveEdit(id) {
    editForm.patch(route('agent.products.update', id), {
        preserveScroll: true,
        onSuccess: () => { editingId.value = null },
    })
}
function cancelEdit() { editingId.value = null }

function remove(id) {
    if (confirm('Hapus produk ini?')) {
        router.delete(route('agent.products.destroy', id), { preserveScroll: true })
    }
}
</script>

<template>
    <Head title="Produk Saya" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-xl font-semibold">Produk Saya</h2>
                    <p class="text-sm text-muted-foreground">{{ supplier.name }}</p>
                </div>
                <Button @click="showForm = !showForm">{{ showForm ? 'Tutup' : '+ Tambah Produk' }}</Button>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8 space-y-4">

                <div class="rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
                    Harga modal yang kamu masukkan akan <strong>menunggu persetujuan tim Welcome Manado</strong> sebelum aktif.
                    Setelah disetujui, status berubah menjadi "Aktif".
                </div>

                <!-- Form tambah -->
                <div v-if="showForm" class="rounded-lg border bg-white p-5 shadow-sm">
                    <form @submit.prevent="submit" class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-1.5 sm:col-span-2">
                            <Label>Nama Produk <span class="text-destructive">*</span></Label>
                            <Input v-model="form.name" placeholder="Mis. Room Deluxe Hotel Arcadia" />
                            <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Tipe</Label>
                            <Select v-model="form.type">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="(label, key) in TYPE_LABELS" :key="key" :value="key">{{ label }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-1.5">
                            <Label>Satuan</Label>
                            <Select v-model="form.unit">
                                <SelectTrigger><SelectValue /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="(label, key) in UNIT_LABELS" :key="key" :value="key">{{ label }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div class="space-y-1.5 sm:col-span-2">
                            <Label>Harga Modal (IDR) <span class="text-destructive">*</span></Label>
                            <Input type="number" v-model="form.cost" min="0" placeholder="0" />
                            <p v-if="form.errors.cost" class="text-sm text-destructive">{{ form.errors.cost }}</p>
                        </div>
                        <div class="sm:col-span-2 flex justify-end">
                            <Button type="submit" :disabled="form.processing">Ajukan Produk</Button>
                        </div>
                    </form>
                </div>

                <!-- Tabel produk -->
                <div class="rounded-lg border bg-white shadow-sm">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nama</TableHead>
                                <TableHead>Tipe</TableHead>
                                <TableHead>Satuan</TableHead>
                                <TableHead class="text-right">Harga Modal</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead class="w-32"></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableEmpty v-if="!products.length" :colspan="6">
                                Belum ada produk. Klik "Tambah Produk" untuk memulai.
                            </TableEmpty>
                            <TableRow v-for="p in products" :key="p.id">
                                <template v-if="editingId === p.id">
                                    <TableCell><Input v-model="editForm.name" class="h-8 text-sm" /></TableCell>
                                    <TableCell>
                                        <Select v-model="editForm.type">
                                            <SelectTrigger class="h-8 text-sm"><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="(label, key) in TYPE_LABELS" :key="key" :value="key">{{ label }}</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </TableCell>
                                    <TableCell>
                                        <Select v-model="editForm.unit">
                                            <SelectTrigger class="h-8 text-sm"><SelectValue /></SelectTrigger>
                                            <SelectContent>
                                                <SelectItem v-for="(label, key) in UNIT_LABELS" :key="key" :value="key">{{ label }}</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </TableCell>
                                    <TableCell><Input type="number" v-model="editForm.cost" class="h-8 text-sm text-right" min="0" /></TableCell>
                                    <TableCell><span class="text-xs text-muted-foreground">—</span></TableCell>
                                    <TableCell class="text-right space-x-1">
                                        <Button size="sm" @click="saveEdit(p.id)" class="h-7 text-xs">Simpan</Button>
                                        <Button size="sm" variant="outline" @click="cancelEdit" class="h-7 text-xs">Batal</Button>
                                    </TableCell>
                                </template>
                                <template v-else>
                                    <TableCell class="font-medium">{{ p.name }}</TableCell>
                                    <TableCell class="text-sm">{{ TYPE_LABELS[p.type] ?? p.type }}</TableCell>
                                    <TableCell class="text-sm text-muted-foreground">{{ UNIT_LABELS[p.unit] ?? p.unit }}</TableCell>
                                    <TableCell class="text-right font-mono text-sm">
                                        <div>{{ fmt(p.cost) }}</div>
                                        <div v-if="p.price_status === 'pending'" class="text-xs text-orange-600">
                                            diajukan: {{ fmt(p.pending_cost) }}
                                        </div>
                                    </TableCell>
                                    <TableCell>
                                        <span v-if="p.price_status === 'pending'" class="text-xs px-2 py-0.5 rounded-full bg-orange-100 text-orange-700 font-medium">
                                            Menunggu Persetujuan
                                        </span>
                                        <span v-else-if="p.is_active" class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700 font-medium">
                                            Aktif
                                        </span>
                                        <span v-else class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 font-medium">
                                            Non-aktif
                                        </span>
                                    </TableCell>
                                    <TableCell class="text-right space-x-1">
                                        <Button size="sm" variant="outline" @click="startEdit(p)" class="h-7 text-xs">Edit</Button>
                                        <Button size="sm" variant="destructive" @click="remove(p.id)" class="h-7 text-xs">Hapus</Button>
                                    </TableCell>
                                </template>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
