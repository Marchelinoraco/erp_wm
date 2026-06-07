<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import { Button } from '@/Components/ui/button'
import { Badge } from '@/Components/ui/badge'
import { Input } from '@/Components/ui/input'
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow, TableEmpty,
} from '@/Components/ui/table'

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

const TYPE_LABELS = { agent: 'Agent', corporate: 'Korporat', direct: 'Direct' }
const TYPE_VARIANTS = { agent: 'default', corporate: 'secondary', direct: 'outline' }

function confirmDelete(id) {
    if (confirm('Hapus customer ini?')) {
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
                                <TableCell class="text-right space-x-2">
                                    <Link :href="route('customers.edit', c.id)">
                                        <Button variant="outline" size="sm">Edit</Button>
                                    </Link>
                                    <Button variant="destructive" size="sm" @click="confirmDelete(c.id)">
                                        Hapus
                                    </Button>
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
    </AuthenticatedLayout>
</template>
