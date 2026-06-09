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
    supplier: Object,
})

const isEdit = !!props.supplier

const form = useForm({
    name:            props.supplier?.name            ?? '',
    type:            props.supplier?.type            ?? '',
    is_travel_agent: props.supplier?.is_travel_agent ?? false,
    contact_person:  props.supplier?.contact_person  ?? '',
    phone:           props.supplier?.phone           ?? '',
    email:           props.supplier?.email           ?? '',
    notes:           props.supplier?.notes           ?? '',
    account_email:    props.supplier?.user?.email    ?? '',
    account_password: '',
})

function submit() {
    if (isEdit) {
        form.patch(route('suppliers.update', props.supplier.id))
    } else {
        form.post(route('suppliers.store'))
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Supplier' : 'Tambah Supplier'" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('suppliers.index')" class="text-muted-foreground hover:text-foreground">
                    ← Suppliers
                </Link>
                <span class="text-muted-foreground">/</span>
                <h2 class="text-xl font-semibold">{{ isEdit ? 'Edit Supplier' : 'Tambah Supplier' }}</h2>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
                <div class="rounded-lg border bg-white p-6 shadow-sm">
                    <form @submit.prevent="submit" class="space-y-5">
                        <div class="space-y-1.5">
                            <Label for="name">Nama Supplier <span class="text-destructive">*</span></Label>
                            <Input id="name" v-model="form.name" placeholder="Mis. Hotel Swiss-Belhotel" />
                            <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
                        </div>

                        <div class="space-y-1.5">
                            <Label>Tipe</Label>
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
                                    <SelectItem value="other">Lainnya</SelectItem>
                                </SelectContent>
                            </Select>
                            <p v-if="form.errors.type" class="text-sm text-destructive">{{ form.errors.type }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <Label for="contact_person">Nama Kontak</Label>
                                <Input id="contact_person" v-model="form.contact_person" placeholder="Mis. Budi" />
                            </div>
                            <div class="space-y-1.5">
                                <Label for="phone">Telepon</Label>
                                <Input id="phone" v-model="form.phone" placeholder="08xx" />
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="email">Email</Label>
                            <Input id="email" type="email" v-model="form.email" placeholder="supplier@email.com" />
                            <p v-if="form.errors.email" class="text-sm text-destructive">{{ form.errors.email }}</p>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="notes">Catatan</Label>
                            <Textarea id="notes" v-model="form.notes" rows="3" placeholder="Info tambahan..." />
                        </div>

                        <!-- TRAVEL AGENT -->
                        <div class="rounded-lg border border-border bg-muted/30 p-4 space-y-4">
                            <label class="flex items-start gap-3 cursor-pointer">
                                <input
                                    type="checkbox"
                                    v-model="form.is_travel_agent"
                                    class="mt-0.5 h-4 w-4 rounded border-input"
                                />
                                <span class="space-y-0.5">
                                    <span class="block text-sm font-medium">Jadikan Travel Agent (eksternal)</span>
                                    <span class="block text-xs text-muted-foreground">
                                        Akun login otomatis dibuat. Travel agent bisa mengelola produk &amp; mengajukan harga modal sendiri,
                                        yang lalu kamu setujui di menu Channel Manager.
                                    </span>
                                </span>
                            </label>

                            <div v-if="form.is_travel_agent" class="space-y-4 pl-7">
                                <div class="space-y-1.5">
                                    <Label for="account_email">Email Login <span class="text-destructive">*</span></Label>
                                    <Input id="account_email" type="email" v-model="form.account_email" placeholder="agent@email.com" autocomplete="off" />
                                    <p v-if="form.errors.account_email" class="text-sm text-destructive">{{ form.errors.account_email }}</p>
                                </div>
                                <div class="space-y-1.5">
                                    <Label for="account_password">
                                        Password <span v-if="!isEdit" class="text-destructive">*</span>
                                        <span v-else class="text-xs font-normal text-muted-foreground ml-1">(kosongkan jika tidak diubah)</span>
                                    </Label>
                                    <Input id="account_password" type="password" v-model="form.account_password" placeholder="Min. 8 karakter" autocomplete="new-password" />
                                    <p v-if="form.errors.account_password" class="text-sm text-destructive">{{ form.errors.account_password }}</p>
                                </div>
                            </div>
                        </div>

                        <div class="flex justify-end gap-3 pt-2">
                            <Link :href="route('suppliers.index')">
                                <Button type="button" variant="outline">Batal</Button>
                            </Link>
                            <Button type="submit" :disabled="form.processing">
                                {{ isEdit ? 'Simpan Perubahan' : 'Tambah Supplier' }}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
