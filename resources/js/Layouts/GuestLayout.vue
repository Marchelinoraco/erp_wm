<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import FlashToaster from '@/Components/FlashToaster.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center bg-gray-50 px-4 py-10">
        <FlashToaster />

        <Link href="/" class="flex flex-col items-center gap-2">
            <ApplicationLogo class="h-14 w-14" />
            <div class="text-center leading-tight">
                <div class="text-lg font-bold text-gray-900">Welcome Manado</div>
                <div class="text-[11px] uppercase tracking-wide text-gray-400">Tour &amp; Travel</div>
            </div>
        </Link>

        <div class="mt-6 w-full max-w-md rounded-xl border bg-white px-6 py-7 shadow-sm sm:px-8">
            <slot />
        </div>
    </div>
</template>
