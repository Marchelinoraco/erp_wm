<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue'
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import { Button } from '@/Components/ui/button'
import { Badge } from '@/Components/ui/badge'
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
import { fmtRp } from '@/lib/fmt'

const props = defineProps({
    products: Object,
    filters:  Object,
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
    router.get(route('products.index'), {
        search: search.value || undefined,
        type:   type.value === 'all' ? undefined : type.value,
    }, { preserveState: true, replace: true })
}

const TYPE_LABELS = {
    hotel: 'Hotel', transport: 'Transport', guide: 'Guide',
    restaurant: 'Restaurant', attraction: 'Attraction', agent: 'Agent', other: 'Lainnya',
}

async function confirmDelete(id) {
    if (await confirm({
        title: 'Hapus produk?',
        description: 'Produk ini akan dihapus permanen.',
        confirmLabel: 'Hapus',
    })) {
        router.delete(route('products.destroy', id))
    }
}
</script>

<template>
    <Head title="Produk" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-semibold">Produk</h2>
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Download template & referensi -->
                    <a :href="route('products.template.suppliers')" target="_blank">
                        <Button variant="outline" size="sm">
                            <svg class="mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m.75 12l3 3m0 0l3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/>
                            </svg>
                            Referensi Supplier
                        </Button>
                    </a>
                    <a :href="route('products.template.download')" target="_blank">
                        <Button variant="outline" size="sm">
                            <svg class="mr-1.5 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                            </svg>
                            Template Produk
                        </Button>
                    </a>
                    <Link :href="route('products.create')">
                        <Button>+ Tambah Produk</Button>
                    </Link>
                </div>
            </div>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 space-y-4">
                <!-- Filter -->
                <div class="flex gap-3">
                    <Input v-model="search" placeholder="Cari produk..." class="max-w-xs" />
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
                            <SelectItem value="agent">Agent</SelectItem>
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
                                <TableHead>Supplier</TableHead>
                                <TableHead>Unit</TableHead>
                                <TableHead class="text-right">Cost (IDR)</TableHead>
                                <TableHead class="text-right">Sell (IDR)</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead class="w-32"></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableEmpty v-if="products.data.length === 0" :colspan="8">
                                Tidak ada produk.
                            </TableEmpty>
                            <TableRow v-for="p in products.data" :key="p.id">
                                <TableCell class="font-medium">{{ p.name }}</TableCell>
                                <TableCell>
                                    <Badge variant="secondary">{{ TYPE_LABELS[p.type] ?? p.type }}</Badge>
                                </TableCell>
                                <TableCell>{{ p.supplier?.name ?? '—' }}</TableCell>
                                <TableCell class="text-muted-foreground text-sm">{{ p.unit }}</TableCell>
                                <TableCell class="text-right font-mono text-sm">{{ fmtRp(p.cost) }}</TableCell>
                                <TableCell class="text-right font-mono text-sm">{{ fmtRp(p.sell) }}</TableCell>
                                <TableCell>
                                    <Badge :variant="p.is_active ? 'default' : 'outline'">
                                        {{ p.is_active ? 'Aktif' : 'Non-aktif' }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-right">
                                    <RowActions>
                                        <DropdownMenuItem as-child>
                                            <Link :href="route('products.edit', p.id)">Edit</Link>
                                        </DropdownMenuItem>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuItem variant="destructive" @click="confirmDelete(p.id)">
                                            Hapus
                                        </DropdownMenuItem>
                                    </RowActions>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>

                <div v-if="products.last_page > 1" class="flex justify-center gap-2">
                    <Link
                        v-for="link in products.links"
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
