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
    customer: Object,
})

const isEdit = !!props.customer

const form = useForm({
    name:           props.customer?.name           ?? '',
    type:           props.customer?.type           ?? 'direct',
    country:        props.customer?.country        ?? '',
    contact_person: props.customer?.contact_person ?? '',
    phone:          props.customer?.phone          ?? '',
    email:          props.customer?.email          ?? '',
    notes:          props.customer?.notes          ?? '',
})

function submit() {
    if (isEdit) {
        form.patch(route('customers.update', props.customer.id))
    } else {
        form.post(route('customers.store'))
    }
}
</script>

<template>
    <Head :title="isEdit ? 'Edit Customer' : 'Tambah Customer'" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-3">
                <Link :href="route('customers.index')" class="text-muted-foreground hover:text-foreground">
                    ← Customers
                </Link>
                <span class="text-muted-foreground">/</span>
                <h2 class="text-xl font-semibold">{{ isEdit ? 'Edit Customer' : 'Tambah Customer' }}</h2>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
                <div class="rounded-lg border bg-white p-6 shadow-sm">
                    <form @submit.prevent="submit" class="space-y-5">
                        <div class="space-y-1.5">
                            <Label for="name">Nama Customer <span class="text-destructive">*</span></Label>
                            <Input id="name" v-model="form.name" placeholder="Mis. Korea Travel Agency" />
                            <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <Label>Tipe <span class="text-destructive">*</span></Label>
                                <Select v-model="form.type">
                                    <SelectTrigger>
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="direct">Direct</SelectItem>
                                        <SelectItem value="agent">Agent</SelectItem>
                                        <SelectItem value="corporate">Korporat</SelectItem>
                                    </SelectContent>
                                </Select>
                                <p v-if="form.errors.type" class="text-sm text-destructive">{{ form.errors.type }}</p>
                            </div>

                            <div class="space-y-1.5">
                                <Label for="country">Negara</Label>
                                <Input id="country" v-model="form.country" placeholder="Mis. Korea, Malaysia, Singapore" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <Label for="contact_person">Nama Kontak</Label>
                                <Input id="contact_person" v-model="form.contact_person" placeholder="Mis. Kim Min-jun" />
                            </div>
                            <div class="space-y-1.5">
                                <Label for="phone">Telepon / WhatsApp</Label>
                                <Input id="phone" v-model="form.phone" placeholder="+82xx atau 08xx" />
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="email">Email</Label>
                            <Input id="email" type="email" v-model="form.email" placeholder="customer@email.com" />
                            <p v-if="form.errors.email" class="text-sm text-destructive">{{ form.errors.email }}</p>
                        </div>

                        <div class="space-y-1.5">
                            <Label for="notes">Catatan</Label>
                            <Textarea id="notes" v-model="form.notes" rows="3" placeholder="Info tambahan..." />
                        </div>

                        <div class="flex justify-end gap-3 pt-2">
                            <Link :href="route('customers.index')">
                                <Button type="button" variant="outline">Batal</Button>
                            </Link>
                            <Button type="submit" :disabled="form.processing">
                                {{ isEdit ? 'Simpan Perubahan' : 'Tambah Customer' }}
                            </Button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
