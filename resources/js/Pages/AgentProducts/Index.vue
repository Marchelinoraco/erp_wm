<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { confirm } from '@/lib/confirm'
import { fmtRp } from '@/lib/fmt'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/Components/ui/select'

const props = defineProps({
    supplier: Object,
    products: Array,
})

const TYPE_LABELS = {
    hotel: 'Hotel', transport: 'Transport', guide: 'Guide',
    restaurant: 'Restaurant', attraction: 'Attraction', other: 'Lainnya',
}
const UNIT_LABELS = { per_pax: 'Per Pax', per_unit: 'Per Unit', per_night: 'Per Malam' }

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

async function remove(id) {
    if (await confirm({ title: 'Hapus produk?', description: 'Produk ini akan dihapus permanen.', confirmLabel: 'Hapus' })) {
        router.delete(route('agent.products.destroy', id), { preserveScroll: true })
    }
}

// ── Periode harga per produk ──
const expandedPrices = ref(new Set())
function togglePrices(id) {
    expandedPrices.value.has(id)
        ? expandedPrices.value.delete(id)
        : expandedPrices.value.add(id)
    expandedPrices.value = new Set(expandedPrices.value)
}

const addingPriceFor = ref(null)
const priceForm = useForm({ label: '', start_date: '', end_date: '', cost: '', notes: '' })

function openPriceForm(productId) {
    addingPriceFor.value = productId
    priceForm.reset()
    expandedPrices.value.add(productId)
    expandedPrices.value = new Set(expandedPrices.value)
}
function submitPrice(productId) {
    priceForm.post(route('agent.product-prices.store', productId), {
        preserveScroll: true,
        onSuccess: () => { priceForm.reset(); addingPriceFor.value = null },
    })
}

async function removePeriod(id) {
    if (await confirm({ title: 'Hapus periode harga?', description: 'Pengajuan periode ini akan dibatalkan.', confirmLabel: 'Hapus' })) {
        router.delete(route('agent.product-prices.destroy', id), { preserveScroll: true })
    }
}

function fmtDate(d) {
    if (!d) return ''
    return new Date(d).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' })
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

                <!-- Daftar produk (card per produk) -->
                <div v-if="!products.length" class="rounded-lg border bg-white p-10 text-center text-sm text-muted-foreground">
                    Belum ada produk. Klik "+ Tambah Produk" untuk memulai.
                </div>

                <div
                    v-for="p in products"
                    :key="p.id"
                    class="rounded-lg border bg-white shadow-sm overflow-hidden"
                >
                    <!-- Header produk -->
                    <template v-if="editingId === p.id">
                        <div class="p-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div class="sm:col-span-2 space-y-1">
                                <Label class="text-xs">Nama</Label>
                                <Input v-model="editForm.name" class="h-8 text-sm" />
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs">Tipe</Label>
                                <Select v-model="editForm.type">
                                    <SelectTrigger class="h-8 text-sm"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="(label, key) in TYPE_LABELS" :key="key" :value="key">{{ label }}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs">Satuan</Label>
                                <Select v-model="editForm.unit">
                                    <SelectTrigger class="h-8 text-sm"><SelectValue /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="(label, key) in UNIT_LABELS" :key="key" :value="key">{{ label }}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs">Harga Modal (IDR)</Label>
                                <Input type="number" v-model="editForm.cost" class="h-8 text-sm text-right" min="0" />
                            </div>
                            <div class="flex items-end gap-2">
                                <Button size="sm" @click="saveEdit(p.id)" class="h-8 text-xs">Simpan</Button>
                                <Button size="sm" variant="outline" @click="cancelEdit" class="h-8 text-xs">Batal</Button>
                            </div>
                        </div>
                    </template>
                    <template v-else>
                        <div class="flex items-start gap-3 px-5 py-4">
                            <div class="flex-1 min-w-0">
                                <div class="font-medium flex items-center gap-2 flex-wrap">
                                    {{ p.name }}
                                    <span class="text-xs px-1.5 py-0.5 rounded bg-muted text-muted-foreground font-medium">
                                        {{ TYPE_LABELS[p.type] ?? p.type }}
                                    </span>
                                    <span class="text-xs text-muted-foreground">· {{ UNIT_LABELS[p.unit] ?? p.unit }}</span>
                                </div>
                                <div class="text-sm font-mono mt-1">
                                    {{ fmtRp(p.cost) }}
                                    <span v-if="p.price_status === 'pending'" class="ml-2 text-xs text-orange-600">(diajukan: {{ fmtRp(p.pending_cost) }})</span>
                                </div>
                                <div class="mt-1">
                                    <span v-if="p.price_status === 'pending'" class="text-xs px-2 py-0.5 rounded-full bg-orange-100 text-orange-700 font-medium">
                                        Menunggu Persetujuan
                                    </span>
                                    <span v-else-if="p.is_active" class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700 font-medium">Aktif</span>
                                    <span v-else class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 font-medium">Non-aktif</span>
                                </div>
                            </div>
                            <div class="flex gap-1 shrink-0 flex-wrap justify-end">
                                <Button size="sm" variant="outline" @click="startEdit(p)" class="h-7 text-xs">Edit</Button>
                                <Button size="sm" variant="destructive" @click="remove(p.id)" class="h-7 text-xs">Hapus</Button>
                            </div>
                        </div>

                        <!-- Toggle periode harga -->
                        <div class="border-t">
                            <button
                                type="button"
                                class="w-full flex items-center justify-between px-5 py-2.5 text-sm text-muted-foreground hover:bg-muted/30"
                                @click="togglePrices(p.id)"
                            >
                                <span>
                                    Periode Harga
                                    <span v-if="p.prices?.length" class="ml-1 text-xs font-medium text-foreground">({{ p.prices.length }})</span>
                                    <span v-if="p.prices?.some(pr => pr.status === 'pending')" class="ml-1 text-xs text-orange-600 font-medium">· ada yang menunggu</span>
                                </span>
                                <span class="text-base leading-none">{{ expandedPrices.has(p.id) ? '−' : '+' }}</span>
                            </button>

                            <div v-if="expandedPrices.has(p.id)" class="border-t">
                                <!-- Form tambah periode -->
                                <div v-if="addingPriceFor === p.id" class="p-4 bg-muted/20 border-b">
                                    <form @submit.prevent="submitPrice(p.id)" class="grid grid-cols-2 gap-3">
                                        <div class="col-span-2 space-y-1">
                                            <Label class="text-xs">Label Periode</Label>
                                            <Input v-model="priceForm.label" placeholder="Mis. High Season 2026" class="h-8 text-sm" />
                                        </div>
                                        <div class="space-y-1">
                                            <Label class="text-xs">Mulai <span class="text-destructive">*</span></Label>
                                            <Input type="date" v-model="priceForm.start_date" class="h-8 text-sm" />
                                            <p v-if="priceForm.errors.start_date" class="text-xs text-destructive">{{ priceForm.errors.start_date }}</p>
                                        </div>
                                        <div class="space-y-1">
                                            <Label class="text-xs">Selesai <span class="text-destructive">*</span></Label>
                                            <Input type="date" v-model="priceForm.end_date" class="h-8 text-sm" />
                                            <p v-if="priceForm.errors.end_date" class="text-xs text-destructive">{{ priceForm.errors.end_date }}</p>
                                        </div>
                                        <div class="col-span-2 space-y-1">
                                            <Label class="text-xs">Harga Modal (IDR) <span class="text-destructive">*</span></Label>
                                            <Input type="number" v-model="priceForm.cost" min="0" class="h-8 text-sm text-right" />
                                            <p v-if="priceForm.errors.cost" class="text-xs text-destructive">{{ priceForm.errors.cost }}</p>
                                        </div>
                                        <div class="col-span-2 flex justify-end gap-2">
                                            <Button type="button" size="sm" variant="outline" @click="addingPriceFor = null" class="h-7 text-xs">Batal</Button>
                                            <Button type="submit" size="sm" :disabled="priceForm.processing" :loading="priceForm.processing" class="h-7 text-xs">
                                                Ajukan Periode
                                            </Button>
                                        </div>
                                    </form>
                                </div>

                                <!-- Daftar periode -->
                                <div v-if="!p.prices?.length && addingPriceFor !== p.id" class="px-5 py-5 text-center text-sm text-muted-foreground">
                                    Belum ada periode harga khusus.
                                </div>
                                <div v-else class="divide-y">
                                    <div v-for="pr in p.prices" :key="pr.id" class="px-5 py-3 flex items-center gap-4 flex-wrap"
                                        :class="pr.status === 'pending' ? 'bg-orange-50/40' : ''">
                                        <div class="flex-1 min-w-0">
                                            <div class="text-sm font-medium">{{ pr.label || 'Periode tanpa label' }}</div>
                                            <div class="text-xs text-muted-foreground">
                                                {{ fmtDate(pr.start_date) }} — {{ fmtDate(pr.end_date) }}
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div v-if="pr.status === 'pending'" class="text-xs font-medium text-orange-700 font-mono">
                                                Diajukan: {{ fmtRp(pr.pending_cost) }}
                                            </div>
                                            <div v-else class="text-sm font-mono">{{ fmtRp(pr.cost) }}</div>
                                            <div class="mt-0.5">
                                                <span v-if="pr.status === 'pending'" class="text-[10px] px-1.5 py-0.5 rounded-full bg-orange-100 text-orange-700 font-semibold">Menunggu</span>
                                                <span v-else-if="pr.is_active" class="text-[10px] px-1.5 py-0.5 rounded-full bg-green-100 text-green-700 font-semibold">Aktif</span>
                                                <span v-else class="text-[10px] px-1.5 py-0.5 rounded-full bg-gray-100 text-gray-500 font-semibold">Non-aktif</span>
                                            </div>
                                        </div>
                                        <Button
                                            v-if="pr.status === 'pending'"
                                            size="sm" variant="destructive"
                                            @click="removePeriod(pr.id)"
                                            class="h-7 text-xs shrink-0"
                                        >Batalkan</Button>
                                    </div>
                                </div>

                                <!-- Tombol tambah periode -->
                                <div v-if="addingPriceFor !== p.id" class="px-5 py-3 border-t bg-muted/10">
                                    <Button size="sm" variant="outline" @click="openPriceForm(p.id)" class="h-7 text-xs">
                                        + Ajukan Periode Harga
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
