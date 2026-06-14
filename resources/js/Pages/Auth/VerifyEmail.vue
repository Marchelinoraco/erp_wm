<script setup>
import { computed } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Button } from '@/Components/ui/button';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    status: {
        type: String,
    },
});

const form = useForm({});

const submit = () => {
    form.post(route('verification.send'));
};

const verificationLinkSent = computed(
    () => props.status === 'verification-link-sent',
);
</script>

<template>
    <GuestLayout>
        <Head title="Email Verification" />

        <h1 class="mb-1 text-lg font-semibold text-gray-900">Verifikasi Email</h1>
        <p class="mb-4 text-sm text-muted-foreground">
            Terima kasih sudah mendaftar! Sebelum mulai, verifikasi email kamu lewat tautan yang baru
            kami kirim. Jika belum menerima email, kami bisa kirim ulang.
        </p>

        <div
            v-if="verificationLinkSent"
            class="mb-4 text-sm font-medium text-green-600"
        >
            Tautan verifikasi baru telah dikirim ke email kamu.
        </div>

        <form @submit.prevent="submit">
            <div class="flex items-center justify-between">
                <Button type="submit" :disabled="form.processing">
                    Kirim Ulang Email Verifikasi
                </Button>

                <Link
                    :href="route('logout')"
                    method="post"
                    as="button"
                    class="text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                >
                    Log Out
                </Link>
            </div>
        </form>
    </GuestLayout>
</template>
