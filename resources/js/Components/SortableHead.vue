<script setup>
/**
 * Header tabel yang bisa diklik untuk sortir.
 *
 * Klik berputar 3 tahap: default → A-Z → Z-A → default, supaya user bisa
 * membatalkan sortir tanpa reload halaman.
 *
 * Komponen ini tidak melakukan sortir sendiri — ia hanya mengabarkan state
 * berikutnya lewat event `sort`. Pengurutan sebenarnya dikerjakan server,
 * karena tabelnya paginated (kalau disortir di klien, hanya halaman aktif
 * yang ikut terurut).
 */
import { computed } from 'vue'
import { TableHead } from '@/Components/ui/table'
import { cn } from '@/lib/utils'

const props = defineProps({
    /** Nama kolom di database — harus ada di whitelist SupplierController::SORTABLE */
    column: { type: String, required: true },
    /** Kolom yang sedang aktif disortir (dari props.filters) */
    sort:   { type: String, default: null },
    /** Arah sortir aktif: 'asc' | 'desc' */
    dir:    { type: String, default: null },
    class:  { type: [String, Object, Array], default: '' },
})

const emit = defineEmits(['sort'])

const active = computed(() => props.sort === props.column)

const ariaSort = computed(() => {
    if (! active.value) return 'none'
    return props.dir === 'desc' ? 'descending' : 'ascending'
})

function toggle() {
    if (! active.value)       return emit('sort', { sort: props.column, dir: 'asc' })
    if (props.dir === 'asc')  return emit('sort', { sort: props.column, dir: 'desc' })

    emit('sort', { sort: null, dir: null })   // tahap ketiga: kembali ke urutan default
}
</script>

<template>
    <TableHead :aria-sort="ariaSort" :class="cn('p-0', props.class)">
        <button
            type="button"
            class="group flex h-10 w-full select-none items-center gap-1.5 px-2 text-left font-medium transition-colors hover:text-foreground"
            @click="toggle"
        >
            <slot />
            <span
                aria-hidden="true"
                :class="[
                    'text-[10px] leading-none transition-opacity',
                    active ? 'opacity-100' : 'opacity-0 group-hover:opacity-40',
                ]"
            >{{ active && dir === 'desc' ? '▼' : '▲' }}</span>
        </button>
    </TableHead>
</template>
