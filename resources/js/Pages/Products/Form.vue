<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import { confirm } from '@/lib/confirm'
import { fmtRp } from '@/lib/fmt'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import { Textarea } from '@/Components/ui/textarea'
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/Components/ui/select'

const props = defineProps({
    product:   Object,
    suppliers: Array,
})

const isEdit = !!props.product

const form = useForm({
    name:        props.product?.name        ?? '',
    type:        props.product?.type        ?? '',
    supplier_id: props.product?.supplier_id ? String(props.product.supplier_id) : 'none',
    unit:        props.product?.unit        ?? 'per_pax',
    cost:        props.product?.cost        ?? '',
    sell:        props.product?.sell        ?? '',
    currency:    props.product?.currency    ?? 'IDR',
    is_active:   props.product?.is_active   ?? true,
    notes:       props.product?.notes       ?? '',
})

function submit() {
    if (form.supplier_id === 'none') form.supplier_id = ''
    if (isEdit) {
        form.patch(route('products.update', props.product.id))
    } else {
        form.post(route('products.store'))
    }
}

// ── Period prices ──
const showPeriodForm = ref(false)
const periodForm = useForm({
    label: '', start_date: '', end_date: '', cost: '', sell: '', is_active: true, notes: '',
})
function submitPeriod() {
    periodForm.post(route('product-prices.store', props.product.id), {
        preserveScroll: true,
        onSuccess: () => { periodForm.reset(); showPeriodForm.value = false },
    })
}

const editingPeriodId = ref(null)
const editPeriodForm = useForm({
    label: '', start_date: '', end_date: '', cost: '', sell: '', is_active: true, notes: '',
})
function startEditPeriod(p) {
    editingPeriodId.value = p.id
    editPeriodForm.label      = p.label ?? ''
    editPeriodForm.start_date = p.start_date
    editPeriodForm.end_date   = p.end_date
    editPeriodForm.cost       = Number(p.cost)
    editPeriodForm.sell       = Number(p.sell)
    editPeriodForm.is_active  = p.is_active
    editPeriodForm.notes      = p.notes ?? ''
}
function saveEditPeriod(id) {
    editPeriodForm.patch(route('product-prices.update', id), {
        preserveScroll: true,
        onSuccess: () => { editingPeriodId.value = null },
    })
}
async function deletePeriod(id) {
    if (await confirm({ title: 'Hapus periode harga?', description: 'Periode ini akan dihapus permanen.', confirmLabel: 'Hapus' })) {
        router.delete(route('product-prices.destroy', id), { preserveScroll: true })
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Produk' : 'Tambah Produk'" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('products.index')" class="text-muted-foreground hover:text-foreground">
                    ← Produk
                </Link>
                <span class="text-muted-foreground">/</span>
                <h2 class="text-xl font-semibold">{{ isEdit ? 'Edit Produk' : 'Tambah Produk' }}</h2>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8 space-y-6">
                <div class="rounded-lg border bg-white p-6 shadow-sm">
                    <form @submit.prevent="submit" class="space-y-5">
                        <div class="space-y-1.5">
                            <Label for="name">Nama Produk <span class="text-destructive">*</span></Label>
                            <Input id="name" v-model="form.name" placeholder="Mis. Hotel Swiss-Bel Superior Room" />
                            <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <Label>Tipe <span class="text-destructive">*</span></Label>
                                <Select v-model="form.type">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih tipe..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="hotel">Hotel</SelectItem>
                                        <SelectItem value="transport">Transport</SelectItem>
                                        <SelectItem value="guide">Guide</SelectItem>
                                        <SelectItem value="restaurant">Restaurant</SelectItem>
                                        <SelectItem value="attraction">Attraction</SelectItem>
                                        <SelectItem value="agent">Agent</SelectItem>
                                        <SelectItem value="other">Lainnya</SelectItem>
                                    </SelectContent>
                                </Select>
                                <p v-if="form.errors.type" class="text-sm text-destructive">{{ form.errors.type }}</p>
                            </div>

                            <div class="space-y-1.5">
                                <Label>Supplier</Label>
                                <Select v-model="form.supplier_id">
                                    <SelectTrigger>
                                        <SelectValue placeholder="Pilih supplier..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">— Tanpa Supplier —</SelectItem>
                                        <SelectItem
                                            v-for="s in suppliers"
                                            :key="s.id"
                                            :value="String(s.id)"
                                        >{{ s.name }}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <Label>Unit <span class="text-destructive">*</span></Label>
                                <Select v-model="form.unit">
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="per_pax">Per Pax</SelectItem>
                                        <SelectItem value="per_unit">Per Unit</SelectItem>
                                        <SelectItem value="per_night">Per Malam</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div class="space-y-1.5">
                                <Label>Mata Uang</Label>
                                <Select v-model="form.currency">
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="IDR">IDR</SelectItem>
                                        <SelectItem value="USD">USD</SelectItem>
                                        <SelectItem value="SGD">SGD</SelectItem>
                                        <SelectItem value="MYR">MYR</SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <Label for="cost">Harga Modal (Cost) <span class="text-destructive">*</span></Label>
                                <Input id="cost" type="number" v-model="form.cost" min="0" step="1000" placeholder="0" />
                                <p v-if="form.errors.cost" class="text-sm text-destructive">{{ form.errors.cost }}</p>
                            </div>

                            <div class="space-y-1.5">
                                <Label for="sell">Harga Jual (Sell) <span class="text-destructive">*</span></Label>
                                <Input id="sell" type="number" v-model="form.sell" min="0" step="1000" placeholder="0" />
                                <p v-if="form.errors.sell" class="text-sm text-destructive">{{ form.errors.sell }}</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3">
                            <input id="is_active" type="checkbox" v-model="form.is_active" class="h-4 w-4 rounded border" />
                            <Label for="is_active" class="cursor-pointer">Produk aktif</Label>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="notes">Catatan</Label>
                            <Textarea id="notes" v-model="form.notes" rows="3" placeholder="Info tambahan..." />
                        </div>

                        <div class="flex justify-end gap-3 pt-2">
                            <Link :href="route('products.index')">
                                <Button type="button" variant="outline">Batal</Button>
                            </Link>
                            <Button type="submit" :disabled="form.processing">
                                {{ isEdit ? 'Simpan Perubahan' : 'Tambah Produk' }}
                            </Button>
                        </div>
                    </form>
                </div>
                <!-- Period prices — edit mode only -->
                <div v-if="isEdit" class="rounded-lg border bg-white shadow-sm overflow-hidden">
                    <div class="flex items-center justify-between px-5 py-4 border-b">
                        <div>
                            <h3 class="font-semibold">Periode Harga</h3>
                            <p class="text-xs text-muted-foreground mt-0.5">Harga modal & jual berdasarkan rentang tanggal tertentu.</p>
                        </div>
                        <Button size="sm" variant="outline" @click="showPeriodForm = !showPeriodForm">
                            {{ showPeriodForm ? 'Tutup' : '+ Tambah Periode' }}
                        </Button>
                    </div>

                    <!-- Tambah periode form -->
                    <div v-if="showPeriodForm" class="p-5 border-b bg-muted/20">
                        <form @submit.prevent="submitPeriod" class="grid grid-cols-2 gap-3">
                            <div class="col-span-2 space-y-1">
                                <Label class="text-xs">Label Periode</Label>
                                <Input v-model="periodForm.label" placeholder="Mis. High Season 2026" class="h-8 text-sm" />
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs">Mulai <span class="text-destructive">*</span></Label>
                                <Input type="date" v-model="periodForm.start_date" class="h-8 text-sm" />
                                <p v-if="periodForm.errors.start_date" class="text-xs text-destructive">{{ periodForm.errors.start_date }}</p>
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs">Selesai <span class="text-destructive">*</span></Label>
                                <Input type="date" v-model="periodForm.end_date" class="h-8 text-sm" />
                                <p v-if="periodForm.errors.end_date" class="text-xs text-destructive">{{ periodForm.errors.end_date }}</p>
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs">Harga Modal <span class="text-destructive">*</span></Label>
                                <Input type="number" v-model="periodForm.cost" min="0" step="1000" class="h-8 text-sm text-right" />
                                <p v-if="periodForm.errors.cost" class="text-xs text-destructive">{{ periodForm.errors.cost }}</p>
                            </div>
                            <div class="space-y-1">
                                <Label class="text-xs">Harga Jual <span class="text-destructive">*</span></Label>
                                <Input type="number" v-model="periodForm.sell" min="0" step="1000" class="h-8 text-sm text-right" />
                                <p v-if="periodForm.errors.sell" class="text-xs text-destructive">{{ periodForm.errors.sell }}</p>
                            </div>
                            <div class="col-span-2 flex items-center justify-between">
                                <label class="flex items-center gap-2 text-sm">
                                    <input type="checkbox" v-model="periodForm.is_active" class="h-4 w-4 rounded border" />
                                    Aktif
                                </label>
                                <Button type="submit" size="sm" :disabled="periodForm.processing" :loading="periodForm.processing">
                                    Simpan Periode
                                </Button>
                            </div>
                        </form>
                    </div>

                    <!-- Daftar periode -->
                    <div v-if="!product.prices?.length" class="px-5 py-8 text-center text-sm text-muted-foreground">
                        Belum ada periode harga khusus. Produk menggunakan harga dasar.
                    </div>
                    <div v-else class="divide-y">
                        <div v-for="p in product.prices" :key="p.id" class="px-5 py-3">
                            <template v-if="editingPeriodId === p.id">
                                <form @submit.prevent="saveEditPeriod(p.id)" class="grid grid-cols-2 gap-3">
                                    <div class="col-span-2 space-y-1">
                                        <Label class="text-xs">Label</Label>
                                        <Input v-model="editPeriodForm.label" class="h-8 text-sm" />
                                    </div>
                                    <div class="space-y-1">
                                        <Label class="text-xs">Mulai</Label>
                                        <Input type="date" v-model="editPeriodForm.start_date" class="h-8 text-sm" />
                                    </div>
                                    <div class="space-y-1">
                                        <Label class="text-xs">Selesai</Label>
                                        <Input type="date" v-model="editPeriodForm.end_date" class="h-8 text-sm" />
                                    </div>
                                    <div class="space-y-1">
                                        <Label class="text-xs">Modal</Label>
                                        <Input type="number" v-model="editPeriodForm.cost" min="0" step="1000" class="h-8 text-sm text-right" />
                                    </div>
                                    <div class="space-y-1">
                                        <Label class="text-xs">Jual</Label>
                                        <Input type="number" v-model="editPeriodForm.sell" min="0" step="1000" class="h-8 text-sm text-right" />
                                    </div>
                                    <div class="col-span-2 flex items-center justify-between">
                                        <label class="flex items-center gap-2 text-sm">
                                            <input type="checkbox" v-model="editPeriodForm.is_active" class="h-4 w-4 rounded border" />
                                            Aktif
                                        </label>
                                        <div class="flex gap-2">
                                            <Button type="button" size="sm" variant="outline" @click="editingPeriodId = null" class="h-7 text-xs">Batal</Button>
                                            <Button type="submit" size="sm" :disabled="editPeriodForm.processing" :loading="editPeriodForm.processing" class="h-7 text-xs">Simpan</Button>
                                        </div>
                                    </div>
                                </form>
                            </template>
                            <template v-else>
                                <div class="flex items-center gap-4 flex-wrap">
                                    <div class="flex-1 min-w-0">
                                        <div class="text-sm font-medium flex items-center gap-2">
                                            <span>{{ p.label || 'Periode tanpa label' }}</span>
                                            <span v-if="!p.is_active" class="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-500 font-semibold">Non-aktif</span>
                                        </div>
                                        <div class="text-xs text-muted-foreground">
                                            {{ p.start_date }} — {{ p.end_date }}
                                        </div>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <div class="text-xs text-muted-foreground">Modal / Jual</div>
                                        <div class="font-mono text-sm">{{ fmtRp(p.cost) }} / {{ fmtRp(p.sell) }}</div>
                                    </div>
                                    <div class="flex gap-1 shrink-0">
                                        <Button size="sm" variant="outline" @click="startEditPeriod(p)" class="h-7 text-xs">Edit</Button>
                                        <Button size="sm" variant="destructive" @click="deletePeriod(p.id)" class="h-7 text-xs">Hapus</Button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
