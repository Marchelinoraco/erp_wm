<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import {
    Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/Components/ui/dialog'
import { Button } from '@/Components/ui/button'
import { Badge } from '@/Components/ui/badge'
import { Input } from '@/Components/ui/input'
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow, TableEmpty,
} from '@/Components/ui/table'
import { DropdownMenuItem, DropdownMenuSeparator } from '@/Components/ui/dropdown-menu'
import RowActions from '@/Components/RowActions.vue'
import { confirm } from '@/lib/confirm'

const props = defineProps({
    customers: Object,
    filters:   Object,
})

const search = ref(props.filters?.search ?? '')

let searchTimer = null
watch(search, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
        router.get(route('customers.index'), { search: search.value || undefined }, {
            preserveState: true, replace: true,
        })
    }, 400)
})

const TYPE_LABELS = { agent: 'Agent', corporate: 'Korporat', direct: 'Direct', buyer: 'Buyer (Travel Agent)' }
const TYPE_VARIANTS = { agent: 'default', corporate: 'secondary', direct: 'outline', buyer: 'default' }

// --- Dorong customer ke Brevo -------------------------------------------
// List ditarik saat dialog dibuka (bukan saat halaman dimuat) supaya halaman
// Customers tidak ikut menunggu Brevo.
const pushOpen     = ref(false)
const pushCustomer = ref(null)
const brevoLists   = ref([])
const listsError   = ref(null)
const loadingLists = ref(false)
const pushForm     = useForm({ list_ids: [] })

async function openPush(customer) {
    pushCustomer.value = customer
    pushForm.reset()
    pushOpen.value = true
    loadingLists.value = true
    listsError.value = null

    try {
        const res  = await fetch(route('marketing.lists'), { headers: { Accept: 'application/json' } })
        const data = await res.json()
        brevoLists.value = data.lists ?? []
        listsError.value = data.error
    } catch {
        listsError.value = 'Tidak dapat memuat list dari Brevo.'
    } finally {
        loadingLists.value = false
    }
}

function toggleList(id) {
    const i = pushForm.list_ids.indexOf(id)
    if (i === -1) pushForm.list_ids.push(id)
    else pushForm.list_ids.splice(i, 1)
}

function submitPush() {
    pushForm.post(route('marketing.customers.push', pushCustomer.value.id), {
        preserveScroll: true,
        onSuccess: () => { pushOpen.value = false },
    })
}

async function confirmDelete(id) {
    if (await confirm({
        title: 'Hapus customer?',
        description: 'Data customer ini akan dihapus permanen.',
        confirmLabel: 'Hapus',
    })) {
        router.delete(route('customers.destroy', id))
    }
}
</script>

<template>
    <Head title="Customers" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold">Customers</h2>
                <Link :href="route('customers.create')">
                    <Button>+ Tambah Customer</Button>
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-4">
                <Input v-model="search" placeholder="Cari nama / kontak..." class="max-w-xs" />

                <div class="rounded-lg border bg-white shadow-sm">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nama</TableHead>
                                <TableHead>Tipe</TableHead>
                                <TableHead>Negara</TableHead>
                                <TableHead>Kontak</TableHead>
                                <TableHead>Telepon</TableHead>
                                <TableHead>Tours</TableHead>
                                <TableHead class="w-32"></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableEmpty v-if="customers.data.length === 0" :colspan="7">
                                Belum ada customer.
                            </TableEmpty>
                            <TableRow v-for="c in customers.data" :key="c.id">
                                <TableCell class="font-medium">{{ c.name }}</TableCell>
                                <TableCell>
                                    <Badge :variant="TYPE_VARIANTS[c.type] ?? 'outline'">
                                        {{ TYPE_LABELS[c.type] ?? c.type }}
                                    </Badge>
                                </TableCell>
                                <TableCell>{{ c.country ?? '—' }}</TableCell>
                                <TableCell>{{ c.contact_person ?? '—' }}</TableCell>
                                <TableCell>{{ c.phone ?? '—' }}</TableCell>
                                <TableCell>{{ c.tours_count }}</TableCell>
                                <TableCell class="text-right">
                                    <RowActions>
                                        <DropdownMenuItem as-child>
                                            <Link :href="route('customers.show', c.id)">Riwayat</Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem as-child>
                                            <Link :href="route('customers.edit', c.id)">Edit</Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuItem :disabled="!c.email" @click="openPush(c)">
                                            Dorong ke Brevo
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem variant="destructive" @click="confirmDelete(c.id)">
                                            Hapus
                                        </DropdownMenuItem>
                                    </RowActions>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <div v-if="customers.last_page > 1" class="flex justify-center gap-2">
                    <Link
                        v-for="link in customers.links"
                        :key="link.label"
                        :href="link.url ?? '#'"
                        v-html="link.label"
                        :class="[
                            'px-3 py-1 rounded border text-sm',
                            link.active ? 'bg-primary text-primary-foreground border-primary' : 'bg-white',
                            !link.url ? 'opacity-40 pointer-events-none' : '',
                        ]"
                    />
                </div>
            </div>
        </div>

        <Dialog v-model:open="pushOpen">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Dorong ke Brevo</DialogTitle>
                    <DialogDescription>
                        {{ pushCustomer?.name }} ({{ pushCustomer?.email }}) akan ditambahkan ke audiens Brevo.
                        Bila sudah terdaftar, datanya diperbarui.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-3">
                    <p v-if="loadingLists" class="text-sm text-gray-500">Memuat list dari Brevo…</p>
                    <p v-else-if="listsError" class="rounded border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                        {{ listsError }}
                    </p>
                    <template v-else>
                        <p class="text-sm font-medium">List tujuan</p>
                        <p v-if="brevoLists.length === 0" class="text-sm text-gray-500">
                            Belum ada list di Brevo. Kontak tetap bisa ditambahkan tanpa list.
                        </p>
                        <div v-else class="space-y-2 max-h-48 overflow-y-auto">
                            <label v-for="l in brevoLists" :key="l.id" class="flex items-center gap-2 text-sm">
                                <input type="checkbox" :checked="pushForm.list_ids.includes(l.id)" @change="toggleList(l.id)" />
                                {{ l.name }}
                            </label>
                        </div>
                    </template>
                    <p v-if="pushForm.errors.email" class="text-sm text-red-600">{{ pushForm.errors.email }}</p>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="pushOpen = false">Batal</Button>
                    <Button :disabled="pushForm.processing || loadingLists" @click="submitPush">Dorong</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AuthenticatedLayout>
</template>
