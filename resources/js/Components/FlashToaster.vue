<script setup>
import { ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'

const page = usePage()
const toasts = ref([])
let seq = 0

function push(type, message) {
    if (!message) return
    const id = ++seq
    toasts.value.push({ id, type, message })
    setTimeout(() => dismiss(id), 4000)
}

function dismiss(id) {
    toasts.value = toasts.value.filter((t) => t.id !== id)
}

// Pantau flash dari Inertia; tiap navigasi yang membawa flash baru memunculkan toast.
watch(
    () => page.props.flash,
    (flash) => {
        if (!flash) return
        push('success', flash.success)
        push('error', flash.error)
    },
    { immediate: true, deep: true },
)

const STYLE = {
    success: 'border-green-200 bg-green-50 text-green-800',
    error:   'border-red-200 bg-red-50 text-red-800',
}
</script>

<template>
    <div
        aria-live="polite"
        aria-atomic="false"
        class="pointer-events-none fixed top-4 right-4 z-[100] flex w-full max-w-sm flex-col gap-2"
    >
        <TransitionGroup
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="translate-x-4 opacity-0"
            enter-to-class="translate-x-0 opacity-100"
            leave-active-class="transition duration-150 ease-in absolute"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-for="t in toasts"
                :key="t.id"
                :class="[
                    'pointer-events-auto flex items-start gap-2.5 rounded-lg border px-4 py-3 text-sm shadow-md',
                    STYLE[t.type] ?? STYLE.success,
                ]"
            >
                <svg v-if="t.type === 'success'" class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <svg v-else class="mt-0.5 h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                </svg>
                <span class="flex-1">{{ t.message }}</span>
                <button
                    type="button"
                    class="shrink-0 opacity-60 transition-opacity hover:opacity-100"
                    aria-label="Tutup notifikasi"
                    @click="dismiss(t.id)"
                >
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>
