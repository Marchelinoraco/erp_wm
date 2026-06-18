<script setup>
import { ref, computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import ApplicationLogo from '@/Components/ApplicationLogo.vue'
import FlashToaster from '@/Components/FlashToaster.vue'
import ConfirmDialog from '@/Components/ConfirmDialog.vue'

const page = usePage()
const user = computed(() => page.props.auth.user)
const pendingBookings = computed(() => page.props.pendingBookings ?? 0)

const sidebarOpen = ref(false)

const ROLE_LABEL = {
    admin:        'Admin',
    sales:        'Sales',
    accountant:   'Akuntansi',
    operation:    'Operasional',
    guide:        'Guide',
    driver:       'Driver',
    tour_leader:  'Tour Leader',
    travel_agent: 'Travel Agent',
}

const ROLE_COLOR = {
    admin:        'bg-red-100 text-red-700',
    sales:        'bg-blue-100 text-blue-700',
    accountant:   'bg-green-100 text-green-700',
    operation:    'bg-indigo-100 text-indigo-700',
    guide:        'bg-purple-100 text-purple-700',
    driver:       'bg-yellow-100 text-yellow-700',
    tour_leader:  'bg-orange-100 text-orange-700',
    travel_agent: 'bg-teal-100 text-teal-700',
}

const ICON = {
    dashboard: `<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />`,
    tours:     `<path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498 4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689C3.24 5.88 3 6.27 3 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z" />`,
    customers: `<path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />`,
    products:  `<path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />`,
    suppliers: `<path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z" />`,
    myjobs:    `<path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />`,
    finance:   `<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />`,
    reminder:  `<path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />`,
    users:     `<path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />`,
    channel:   `<path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />`,
    rental:    `<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />`,
    guide:     `<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />`,
    document:  `<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />`,
    ticketing: `<path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 010 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 010-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375z" />`,
    booking:   `<path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />`,
}

const navGroups = computed(() => {
    const role = user.value?.role

    if (role === 'guide' || role === 'driver' || role === 'tour_leader') {
        return [{ label: null, items: [
            { label: 'Jadwal Saya', route: 'my-jobs', match: 'my-jobs*', icon: ICON.myjobs },
        ]}]
    }

    if (role === 'travel_agent') {
        return [{ label: null, items: [
            { label: 'Produk Saya', route: 'agent.products.index', match: 'agent.products.*', icon: ICON.products },
        ]}]
    }

    if (role === 'operation') {
        return [{ label: null, items: [
            { label: 'Booking', route: 'bookings.index', match: 'bookings.*', icon: ICON.booking, badge: pendingBookings.value },
        ]}]
    }

    const groups = [
        { label: null, items: [
            { label: 'Dashboard', route: 'dashboard', match: 'dashboard', icon: ICON.dashboard },
        ]},
    ]

    if (role === 'admin' || role === 'sales') {
        groups.push({ label: 'Penjualan', items: [
            { label: 'Tour',        route: 'tours.index', params: { type: 'tour' },      type: 'tour',      icon: ICON.tours },
            { label: 'Rental',      route: 'tours.index', params: { type: 'rental' },    type: 'rental',    icon: ICON.rental },
            { label: 'Jasa Guide',  route: 'tours.index', params: { type: 'guide' },     type: 'guide',     icon: ICON.guide },
            { label: 'Visa/Paspor', route: 'tours.index', params: { type: 'document' },  type: 'document',  icon: ICON.document },
            { label: 'Ticketing',   route: 'tours.index', params: { type: 'ticketing' }, type: 'ticketing', icon: ICON.ticketing },
        ]})

        groups.push({ label: 'Operasional', items: [
            { label: 'Booking',  route: 'bookings.index',  match: 'bookings.*',  icon: ICON.booking, badge: pendingBookings.value },
            { label: 'Reminder', route: 'reminders.index', match: 'reminders.*', icon: ICON.reminder },
        ]})

        const dataItems = [
            { label: 'Customers',       route: 'customers.index',       match: 'customers.*',       icon: ICON.customers },
            { label: 'Produk',          route: 'products.index',        match: 'products.*',        icon: ICON.products },
            { label: 'Channel Manager', route: 'channel-manager.index', match: 'channel-manager.*', icon: ICON.channel },
        ]
        if (role === 'admin') {
            dataItems.push({ label: 'Suppliers', route: 'suppliers.index', match: 'suppliers.*', icon: ICON.suppliers })
        }
        groups.push({ label: 'Data Master', items: dataItems })
    }

    if (role === 'admin') {
        groups.push({ label: 'Administrasi', items: [
            { label: 'Kelola Akun', route: 'users.index', match: 'users.*', icon: ICON.users },
        ]})
    }

    if (role === 'admin' || role === 'accountant') {
        groups.push({ label: 'Keuangan', items: [
            { label: 'Keuangan',  route: 'finance.index',        match: ['finance.index', 'finance.tour'], icon: ICON.finance },
            { label: 'Arus Kas',  route: 'finance.cashflow',     match: 'finance.cashflow', icon: ICON.finance },
            { label: 'Transaksi', route: 'finance.transactions', match: 'finance.transactions', icon: ICON.finance },
            { label: 'Rekening',  route: 'bank-accounts.index',  match: 'bank-accounts.*', icon: ICON.finance },
        ]})
    }

    return groups
})

const activeBreadcrumb = computed(() => {
    for (const group of navGroups.value) {
        if (!group.label) continue
        for (const item of group.items) {
            if (isActive(item)) return group.label
        }
    }
    return null
})

const currentType = computed(() => {
    const qs = page.url.split('?')[1] ?? ''
    return new URLSearchParams(qs).get('type') ?? ''
})

function isActive(item) {
    if (item.type !== undefined) {
        return route().current('tours.index') && currentType.value === item.type
    }
    const patterns = Array.isArray(item.match) ? item.match : [item.match]
    return patterns.some(p => route().current(p))
}
</script>

<template>
    <div class="flex h-screen overflow-hidden bg-gray-50">

        <FlashToaster />
        <ConfirmDialog />

        <!-- Mobile overlay -->
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-20 bg-black/40 lg:hidden"
            @click="sidebarOpen = false"
        />

        <!-- Sidebar -->
        <aside
            id="sidebar"
            :class="[
                'fixed inset-y-0 left-0 z-30 flex w-60 flex-col bg-white border-r border-gray-200 shadow-sm transition-transform duration-200',
                sidebarOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
            ]"
        >
            <!-- Logo -->
            <div class="flex h-16 items-center gap-2.5 border-b border-gray-100 px-5">
                <Link :href="route('dashboard')" class="flex items-center gap-2.5">
                    <ApplicationLogo class="h-8 w-8" />
                    <div class="leading-tight">
                        <div class="text-sm font-bold text-gray-900">Welcome Manado</div>
                        <div class="text-[10px] text-gray-400 tracking-wide uppercase">Tour & Travel</div>
                    </div>
                </Link>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 overflow-y-auto px-3 py-3 space-y-0.5">
                <template v-for="group in navGroups" :key="group.label ?? 'main'">
                    <p v-if="group.label" class="px-3 pt-3 pb-0.5 text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">
                        {{ group.label }}
                    </p>
                    <Link
                        v-for="item in group.items"
                        :key="item.label"
                        :href="route(item.route, item.params)"
                        :aria-current="isActive(item) ? 'page' : undefined"
                        :class="[
                            'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors',
                            isActive(item)
                                ? 'bg-primary/10 text-primary'
                                : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900',
                        ]"
                        @click="sidebarOpen = false"
                    >
                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                            class="h-5 w-5 shrink-0"
                            v-html="item.icon"
                        />
                        <span class="flex-1">{{ item.label }}</span>
                        <span
                            v-if="item.badge"
                            class="ml-auto inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-bold leading-none text-primary-foreground"
                        >
                            {{ item.badge }}
                        </span>
                    </Link>
                </template>
            </nav>

            <!-- User section -->
            <div class="border-t border-gray-100 p-3 space-y-0.5">
                <div class="flex items-center gap-2.5 px-3 py-2">
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary text-xs font-bold uppercase">
                        {{ user.name.charAt(0) }}
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-medium text-gray-900">{{ user.name }}</div>
                        <span
                            v-if="user.role"
                            class="inline-block text-[10px] font-semibold px-1.5 py-0.5 rounded mt-0.5"
                            :class="ROLE_COLOR[user.role] ?? 'bg-gray-100 text-gray-600'"
                        >
                            {{ ROLE_LABEL[user.role] ?? user.role }}
                        </span>
                    </div>
                </div>
                <Link
                    :href="route('profile.edit')"
                    class="flex items-center gap-3 rounded-lg px-3 py-1.5 text-sm text-gray-500 hover:bg-gray-100 hover:text-gray-900 transition-colors"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                    </svg>
                    Profile
                </Link>
                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="flex w-full items-center gap-3 rounded-lg px-3 py-1.5 text-sm text-gray-500 hover:bg-gray-100 hover:text-gray-900 transition-colors"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
                    </svg>
                    Log Out
                </Link>
            </div>
        </aside>

        <!-- Main area -->
        <div class="flex flex-1 flex-col lg:pl-60 min-h-screen">

            <!-- Top bar -->
            <header class="sticky top-0 z-10 bg-white border-b border-gray-200 shadow-sm">
                <div class="flex h-14 items-center gap-3 px-4 sm:px-6">
                    <!-- Mobile hamburger -->
                    <button
                        class="lg:hidden rounded-md p-1.5 text-gray-500 hover:bg-gray-100"
                        :aria-label="sidebarOpen ? 'Tutup menu' : 'Buka menu'"
                        :aria-expanded="sidebarOpen"
                        aria-controls="sidebar"
                        @click="sidebarOpen = !sidebarOpen"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                    </button>

                    <!-- Page header slot -->
                    <div class="flex-1 min-w-0">
                        <p v-if="activeBreadcrumb" class="text-[11px] uppercase tracking-wider font-medium text-muted-foreground leading-none mb-1">
                            {{ activeBreadcrumb }}
                        </p>
                        <slot name="header" />
                    </div>
                </div>
            </header>

            <!-- Page content -->
            <main class="flex-1 overflow-y-auto">
                <slot />
            </main>
        </div>
    </div>
</template>
