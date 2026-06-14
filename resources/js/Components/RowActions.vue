<script setup>
import { Button } from '@/Components/ui/button'
import {
    DropdownMenu, DropdownMenuContent, DropdownMenuTrigger,
} from '@/Components/ui/dropdown-menu'

// Trigger ikon "⋯" untuk aksi per-baris tabel. Item menu diisi via default slot
// (gunakan <DropdownMenuItem>). De-emphasize aksi merusak agar tak mengundang
// klik tak sengaja dan tabel lebih bersih.
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <Button variant="ghost" size="sm" class="h-8 w-8 p-0" aria-label="Aksi baris">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                    <circle cx="5" cy="12" r="1.8" />
                    <circle cx="12" cy="12" r="1.8" />
                    <circle cx="19" cy="12" r="1.8" />
                </svg>
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-44">
            <slot />
        </DropdownMenuContent>
    </DropdownMenu>
</template>
