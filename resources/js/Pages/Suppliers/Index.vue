<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import { Button } from '@/Components/ui/button'
import { Input } from '@/Components/ui/input'
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/Components/ui/select'
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow, TableEmpty,
} from '@/Components/ui/table'
import { DropdownMenuItem, DropdownMenuSeparator } from '@/Components/ui/dropdown-menu'
import RowActions from '@/Components/RowActions.vue'
import { confirm } from '@/lib/confirm'

const props = defineProps({
    suppliers: Object,
    filters:   Object,
})

const search = ref(props.filters?.search ?? '')
const type   = ref(props.filters?.type   ?? 'all')

let searchTimer = null
watch(search, () => {
    clearTimeout(searchTimer)
    searchTimer = setTimeout(() => applyFilter(), 400)
})
watch(type, () => applyFilter())

function applyFilter() {
    router.get(route('suppliers.index'), {
        search: search.value || undefined,
        type:   type.value === 'all' ? undefined : type.value,
    }, { preserveState: true, replace: true })
}

const TYPE_LABELS = {
    hotel: 'Hotel',
    transport: 'Transport',
    guide: 'Guide',
    restaurant: 'Restaurant',
    attraction: 'Attraction',
    other: 'Lainnya',
}

const TYPE_COLORS = {
    hotel:      'bg-blue-100 text-blue-700',
    transport:  'bg-orange-100 text-orange-700',
    guide:      'bg-green-100 text-green-700',
    restaurant: 'bg-yellow-100 text-yellow-700',
    attraction: 'bg-purple-100 text-purple-700',
    other:      'bg-gray-100 text-gray-700',
}

async function confirmDelete(id) {
    if (await confirm({
        title: 'Hapus supplier?',
        description: 'Supplier ini akan dihapus permanen.',
        confirmLabel: 'Hapus',
    })) {
        router.delete(route('suppliers.destroy', id))
    }
}
</script>

<template>
    <Head title="Suppliers" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold">Suppliers</h2>
                <Link :href="route('suppliers.create')">
                    <Button>+ Tambah Supplier</Button>
                </Link>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-4">
                <!-- Filter -->
                <div class="flex gap-3">
                    <Input v-model="search" placeholder="Cari supplier..." class="max-w-xs" />
                    <Select v-model="type">
                        <SelectTrigger class="w-44">
                            <SelectValue placeholder="Semua tipe" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">Semua tipe</SelectItem>
                            <SelectItem value="hotel">Hotel</SelectItem>
                            <SelectItem value="transport">Transport</SelectItem>
                            <SelectItem value="guide">Guide</SelectItem>
                            <SelectItem value="restaurant">Restaurant</SelectItem>
                            <SelectItem value="attraction">Attraction</SelectItem>
                            <SelectItem value="other">Lainnya</SelectItem>
                        </SelectContent>
                    </Select>
                </div>

                <div class="rounded-lg border bg-white shadow-sm">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nama</TableHead>
                                <TableHead>Tipe</TableHead>
                                <TableHead>Kontak</TableHead>
                                <TableHead>Telepon</TableHead>
                                <TableHead>Produk</TableHead>
                                <TableHead class="w-32"></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableEmpty v-if="suppliers.data.length === 0" :colspan="6">
                                Belum ada supplier.
                            </TableEmpty>
                            <TableRow v-for="s in suppliers.data" :key="s.id">
                                <TableCell class="font-medium">{{ s.name }}</TableCell>
                                <TableCell>
                                    <span
                                        v-if="s.type"
                                        :class="[TYPE_COLORS[s.type] ?? 'bg-gray-100 text-gray-700', 'inline-flex px-2 py-0.5 rounded-full text-xs font-medium']"
                                    >
                                        {{ TYPE_LABELS[s.type] ?? s.type }}
                                    </span>
                                    <span v-else class="text-muted-foreground">—</span>
                                </TableCell>
                                <TableCell>{{ s.contact_person ?? '—' }}</TableCell>
                                <TableCell>{{ s.phone ?? '—' }}</TableCell>
                                <TableCell>{{ s.products_count }}</TableCell>
                                <TableCell class="text-right">
                                    <RowActions>
                                        <DropdownMenuItem as-child>
                                            <Link :href="route('suppliers.edit', s.id)">Edit</Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem variant="destructive" @click="confirmDelete(s.id)">
                                            Hapus
                                        </DropdownMenuItem>
                                    </RowActions>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <div v-if="suppliers.last_page > 1" class="flex justify-center gap-2">
                    <Link
                        v-for="link in suppliers.links"
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
