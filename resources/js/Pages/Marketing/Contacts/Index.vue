<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, router, useForm } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import { Button } from '@/Components/ui/button'
import { Badge } from '@/Components/ui/badge'
import { Input } from '@/Components/ui/input'
import { Label } from '@/Components/ui/label'
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow, TableEmpty,
} from '@/Components/ui/table'
import {
    Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle, DialogTrigger,
} from '@/Components/ui/dialog'

const props = defineProps({
    contacts: Array,
    count:    Number,
    page:     Number,
    perPage:  Number,
    search:   String,
    lists:    Array,
    error:    String,
})

const search = ref(props.search ?? '')

// Brevo tidak menyediakan pencarian teks — hanya lookup email persis.
function doSearch() {
    router.get(route('marketing.contacts.index'), { email: search.value || undefined }, {
        preserveState: true, replace: true,
    })
}

function clearSearch() {
    search.value = ''
    router.get(route('marketing.contacts.index'), {}, { preserveState: true, replace: true })
}

const isSearching = computed(() => (props.search ?? '') !== '')
const lastPage = computed(() => Math.max(1, Math.ceil((props.count ?? 0) / (props.perPage || 50))))

function goToPage(p) {
    router.get(route('marketing.contacts.index'), { page: p }, { preserveState: true, replace: true })
}

const dialogOpen = ref(false)
const form = useForm({ email: '', name: '', list_ids: [] })

function submit() {
    form.post(route('marketing.contacts.store'), {
        preserveScroll: true,
        onSuccess: () => { dialogOpen.value = false; form.reset() },
    })
}

function toggleList(id) {
    const i = form.list_ids.indexOf(id)
    if (i === -1) form.list_ids.push(id)
    else form.list_ids.splice(i, 1)
}
</script>

<template>
    <Head title="Kontak Brevo" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold">Kontak Brevo</h2>
                <Dialog v-model:open="dialogOpen">
                    <DialogTrigger as-child>
                        <Button :disabled="!!error">+ Tambah Kontak</Button>
                    </DialogTrigger>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Tambah Kontak ke Brevo</DialogTitle>
                            <DialogDescription>
                                Bila email sudah terdaftar, datanya diperbarui dan ditambahkan ke list terpilih.
                            </DialogDescription>
                        </DialogHeader>

                        <div class="space-y-4">
                            <div>
                                <Label for="email">Email</Label>
                                <Input id="email" v-model="form.email" type="email" placeholder="nama@contoh.com" />
                                <p v-if="form.errors.email" class="mt-1 text-sm text-red-600">{{ form.errors.email }}</p>
                            </div>
                            <div>
                                <Label for="name">Nama (opsional)</Label>
                                <Input id="name" v-model="form.name" placeholder="Budi" />
                            </div>
                            <div v-if="lists.length">
                                <Label>List tujuan</Label>
                                <div class="mt-2 space-y-2 max-h-40 overflow-y-auto">
                                    <label v-for="l in lists" :key="l.id" class="flex items-center gap-2 text-sm">
                                        <input type="checkbox" :value="l.id"
                                               :checked="form.list_ids.includes(l.id)"
                                               @change="toggleList(l.id)" />
                                        {{ l.name }}
                                    </label>
                                </div>
                            </div>
                        </div>

                        <DialogFooter>
                            <Button variant="outline" @click="dialogOpen = false">Batal</Button>
                            <Button :disabled="form.processing" @click="submit">Simpan</Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-4">
                <div v-if="error" class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    {{ error }}
                </div>

                <div class="flex flex-wrap items-end gap-2">
                    <div>
                        <Label for="cari" class="text-xs text-gray-500">Cari (alamat email lengkap)</Label>
                        <Input id="cari" v-model="search" placeholder="nama@contoh.com"
                               class="max-w-xs" @keyup.enter="doSearch" />
                    </div>
                    <Button variant="secondary" @click="doSearch">Cari</Button>
                    <Button v-if="isSearching" variant="ghost" @click="clearSearch">Reset</Button>
                    <p class="ml-auto text-sm text-gray-500">
                        <span v-if="isSearching">Hasil pencarian</span>
                        <span v-else>{{ count.toLocaleString('id-ID') }} kontak</span>
                    </p>
                </div>

                <p class="text-xs text-gray-500">
                    Brevo tidak mendukung pencarian sebagian kata — ketik alamat email lengkap.
                </p>

                <div class="rounded-lg border bg-white shadow-sm">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Email</TableHead>
                                <TableHead>Nama</TableHead>
                                <TableHead>Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableEmpty v-if="contacts.length === 0" :colspan="3">
                                <span v-if="error">Tidak dapat memuat kontak.</span>
                                <span v-else-if="isSearching">Kontak tidak ditemukan.</span>
                                <span v-else>Belum ada kontak.</span>
                            </TableEmpty>
                            <TableRow v-for="c in contacts" :key="c.email">
                                <TableCell class="font-medium">{{ c.email }}</TableCell>
                                <TableCell>{{ c.attributes?.FIRSTNAME ?? '—' }}</TableCell>
                                <TableCell>
                                    <Badge v-if="c.emailBlacklisted" variant="destructive">Blocklisted</Badge>
                                    <Badge v-else variant="secondary">Subscribed</Badge>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <div v-if="!isSearching && lastPage > 1" class="flex items-center justify-between">
                    <Button variant="outline" :disabled="page <= 1" @click="goToPage(page - 1)">Sebelumnya</Button>
                    <span class="text-sm text-gray-600">Halaman {{ page }} dari {{ lastPage }}</span>
                    <Button variant="outline" :disabled="page >= lastPage" @click="goToPage(page + 1)">Berikutnya</Button>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
