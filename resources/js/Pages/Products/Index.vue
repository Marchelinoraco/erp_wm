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
    restaurant: 'Restaurant', attraction: 'Attraction', other: 'Lainnya',
}

function fmt(val) {
    return Number(val).toLocaleString('id-ID')
}

function confirmDelete(id) {
    if (confirm('Hapus produk ini?')) {
        router.delete(route('products.destroy', id))
    }
}
</script>

<template>
    <Head title="Produk" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold">Produk</h2>
                <Link :href="route('products.create')">
                    <Button>+ Tambah Produk</Button>
                </Link>
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
                                <TableCell class="text-right font-mono text-sm">{{ fmt(p.cost) }}</TableCell>
                                <TableCell class="text-right font-mono text-sm">{{ fmt(p.sell) }}</TableCell>
                                <TableCell>
                                    <Badge :variant="p.is_active ? 'default' : 'outline'">
                                        {{ p.is_active ? 'Aktif' : 'Non-aktif' }}
                                    </Badge>
                                </TableCell>
                                <TableCell class="text-right space-x-2">
                                    <Link :href="route('products.edit', p.id)">
                                        <Button variant="outline" size="sm">Edit</Button>
                                    </Link>
                                    <Button variant="destructive" size="sm" @click="confirmDelete(p.id)">
                                        Hapus
                                    </Button>
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
