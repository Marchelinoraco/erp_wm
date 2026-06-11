<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'
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
            <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
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
            </div>
        </div>
    </AuthenticatedLayout>
</template>
