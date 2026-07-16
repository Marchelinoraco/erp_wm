<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import { confirm } from '@/lib/confirm'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import {
    Dialog, DialogContent, DialogHeader, DialogTitle,
} from '@/Components/ui/dialog'

defineProps({
    accounts: Array,
})

const canSeeFinance = computed(() => ['admin', 'accountant'].includes(usePage().props.auth.user?.role))

const dialogOpen = ref(false)
const editing = ref(null)

const form = useForm({
    bank: '',
    account_number: '',
    holder_name: '',
    is_active: true,
})

function openAdd() {
    editing.value = null
    form.reset()
    form.clearErrors()
    dialogOpen.value = true
}

function openEdit(acc) {
    editing.value = acc
    form.bank = acc.bank
    form.account_number = acc.account_number
    form.holder_name = acc.holder_name
    form.is_active = acc.is_active
    form.clearErrors()
    dialogOpen.value = true
}

function submit() {
    if (editing.value) {
        form.patch(route('bank-accounts.update', editing.value.id), {
            preserveScroll: true,
            onSuccess: () => { dialogOpen.value = false },
        })
    } else {
        form.post(route('bank-accounts.store'), {
            preserveScroll: true,
            onSuccess: () => { dialogOpen.value = false },
        })
    }
}

function toggleActive(acc) {
    router.patch(route('bank-accounts.update', acc.id), {
        bank: acc.bank,
        account_number: acc.account_number,
        holder_name: acc.holder_name,
        is_active: !acc.is_active,
    }, { preserveScroll: true })
}

async function remove(acc) {
    if (await confirm({ title: `Hapus rekening ${acc.bank}?`, confirmLabel: 'Hapus' })) {
        router.delete(route('bank-accounts.destroy', acc.id), { preserveScroll: true })
    }
}
</script>

<template>
    <Head title="Rekening Pembayaran" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h1 class="text-base font-semibold text-gray-800">Rekening Pembayaran</h1>
                <Link v-if="canSeeFinance" :href="route('finance.index')" class="text-sm text-gray-500 hover:text-gray-700">← Keuangan</Link>
                <Link v-else :href="route('dashboard')" class="text-sm text-gray-500 hover:text-gray-700">← Dashboard</Link>
            </div>
        </template>

        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-6 space-y-4">
            <div class="flex items-center justify-between">
                <p class="text-sm text-gray-500">
                    Rekening <b>aktif</b> akan tampil di PDF invoice yang dikirim ke customer.
                </p>
                <Button size="sm" @click="openAdd">+ Rekening</Button>
            </div>

            <div class="bg-white rounded-xl border shadow-sm overflow-hidden">
                <div v-if="!accounts.length" class="px-5 py-8 text-sm text-gray-400 text-center">
                    Belum ada rekening. Klik “+ Rekening” untuk menambah.
                </div>

                <div v-else class="divide-y">
                    <div v-for="acc in accounts" :key="acc.id"
                        class="px-5 py-4 flex items-start justify-between gap-4"
                        :class="{ 'opacity-50': !acc.is_active }">
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-semibold text-gray-800">{{ acc.bank }}</span>
                                <span class="text-xs px-2 py-0.5 rounded-full font-medium"
                                    :class="acc.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'">
                                    {{ acc.is_active ? 'Aktif' : 'Nonaktif' }}
                                </span>
                            </div>
                            <p class="font-mono text-lg font-bold text-gray-900 mt-0.5">{{ acc.account_number }}</p>
                            <p class="text-xs text-gray-500">a.n. {{ acc.holder_name }}</p>
                        </div>
                        <div class="flex gap-2 shrink-0">
                            <Button size="sm" variant="outline" @click="toggleActive(acc)">
                                {{ acc.is_active ? 'Nonaktifkan' : 'Aktifkan' }}
                            </Button>
                            <Button size="sm" variant="outline" @click="openEdit(acc)">Edit</Button>
                            <Button v-if="canSeeFinance" size="sm" variant="destructive" @click="remove(acc)">Hapus</Button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── Dialog ── -->
        <Dialog v-model:open="dialogOpen">
            <DialogContent class="max-w-sm">
                <DialogHeader>
                    <DialogTitle>{{ editing ? 'Edit Rekening' : 'Rekening Baru' }}</DialogTitle>
                </DialogHeader>
                <form @submit.prevent="submit" class="space-y-3 mt-2">
                    <div class="space-y-1.5">
                        <Label>Nama Bank</Label>
                        <Input v-model="form.bank" placeholder="Bank BCA" required />
                        <p v-if="form.errors.bank" class="text-xs text-red-600">{{ form.errors.bank }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Nomor Rekening</Label>
                        <Input v-model="form.account_number" placeholder="1234567890" required />
                        <p v-if="form.errors.account_number" class="text-xs text-red-600">{{ form.errors.account_number }}</p>
                    </div>
                    <div class="space-y-1.5">
                        <Label>Atas Nama</Label>
                        <Input v-model="form.holder_name" placeholder="PT. Welcome Manado Wisata" required />
                        <p v-if="form.errors.holder_name" class="text-xs text-red-600">{{ form.errors.holder_name }}</p>
                    </div>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" v-model="form.is_active" class="rounded border-gray-300" />
                        Aktif (tampil di invoice)
                    </label>
                    <div class="flex justify-end gap-2 pt-1">
                        <Button type="button" variant="outline" @click="dialogOpen = false">Batal</Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ editing ? 'Simpan' : 'Tambah' }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </AuthenticatedLayout>
</template>
