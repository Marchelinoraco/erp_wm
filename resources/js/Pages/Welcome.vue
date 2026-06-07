<script setup>
import { Head, Link } from '@inertiajs/vue3'

defineProps({
    canLogin:    Boolean,
    canRegister: Boolean,
})
</script>

<template>
    <Head title="Welcome Manado ERP" />

    <div class="min-h-screen bg-gray-50 flex flex-col">

        <!-- Top bar -->
        <header class="w-full border-b border-gray-100 bg-white px-6 py-4">
            <div class="mx-auto flex max-w-5xl items-center justify-between">
                <div class="flex items-center gap-3">
                    <img src="/logo.png" alt="Welcome Manado" class="h-9 w-9 object-contain" />
                    <div class="leading-tight">
                        <div class="text-sm font-bold text-gray-900">Welcome Manado</div>
                        <div class="text-[10px] uppercase tracking-widest text-gray-400">Tour & Travel</div>
                    </div>
                </div>
                <nav v-if="canLogin">
                    <Link
                        v-if="$page.props.auth.user"
                        :href="route('dashboard')"
                        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 transition-colors"
                    >
                        Buka Dashboard →
                    </Link>
                    <Link
                        v-else
                        :href="route('login')"
                        class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 transition-colors"
                    >
                        Masuk
                    </Link>
                </nav>
            </div>
        </header>

        <!-- Hero -->
        <main class="flex flex-1 items-center justify-center px-6 py-16">
            <div class="mx-auto max-w-2xl text-center">

                <!-- Logo besar -->
                <div class="mb-8 flex justify-center">
                    <div class="relative">
                        <div class="absolute inset-0 rounded-3xl bg-red-600/10 blur-2xl scale-110"></div>
                        <img
                            src="/logo.png"
                            alt="Welcome Manado"
                            class="relative h-28 w-28 object-contain drop-shadow-sm"
                        />
                    </div>
                </div>

                <h1 class="text-4xl font-bold tracking-tight text-gray-900 sm:text-5xl">
                    Welcome Manado
                </h1>
                <p class="mt-2 text-lg font-medium text-red-600">Tour & Travel</p>
                <p class="mt-4 text-base text-gray-500 max-w-md mx-auto">
                    Sistem ERP internal untuk manajemen tour, quotation, operasional, dan keuangan.
                </p>

                <!-- CTA -->
                <div class="mt-10">
                    <template v-if="$page.props.auth.user">
                        <Link
                            :href="route('dashboard')"
                            class="inline-flex items-center gap-2 rounded-xl bg-red-600 px-8 py-3.5 text-base font-semibold text-white shadow-sm hover:bg-red-700 transition-colors"
                        >
                            Buka Dashboard
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                            </svg>
                        </Link>
                    </template>
                    <template v-else-if="canLogin">
                        <Link
                            :href="route('login')"
                            class="inline-flex items-center gap-2 rounded-xl bg-red-600 px-8 py-3.5 text-base font-semibold text-white shadow-sm hover:bg-red-700 transition-colors"
                        >
                            Masuk ke Sistem
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/>
                            </svg>
                        </Link>
                        <p class="mt-3 text-xs text-gray-400">Khusus staf Welcome Manado</p>
                    </template>
                </div>

                <!-- Feature pills -->
                <div class="mt-14 flex flex-wrap justify-center gap-2">
                    <span v-for="f in ['Pipeline Inquiry', 'Tour Builder', 'Quotation PDF', 'Keuangan AR/AP', 'Jadwal Lapangan', 'Reminder Sales']"
                        :key="f"
                        class="rounded-full border border-gray-200 bg-white px-3 py-1 text-xs text-gray-500 shadow-sm"
                    >
                        {{ f }}
                    </span>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="border-t border-gray-100 bg-white py-4 text-center text-xs text-gray-400">
            © {{ new Date().getFullYear() }} Welcome Manado Tour & Travel · Manado, Sulawesi Utara
        </footer>
    </div>
</template>
