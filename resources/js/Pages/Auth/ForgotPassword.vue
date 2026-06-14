<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Head, useForm } from '@inertiajs/vue3';

defineProps({
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
});

const submit = () => {
    form.post(route('password.email'));
};
</script>

<template>
    <GuestLayout>
        <Head title="Forgot Password" />

        <h1 class="mb-1 text-lg font-semibold text-gray-900">Lupa Password</h1>
        <p class="mb-6 text-sm text-muted-foreground">
            Masukkan email kamu dan kami akan mengirim tautan untuk mengatur ulang password.
        </p>

        <div v-if="status" class="mb-4 text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div class="space-y-1.5">
                <Label for="email">Email</Label>
                <Input id="email" type="email" v-model="form.email" required autofocus autocomplete="username" />
                <p v-if="form.errors.email" class="text-sm text-destructive">{{ form.errors.email }}</p>
            </div>

            <div class="flex justify-end pt-2">
                <Button type="submit" :disabled="form.processing">
                    Kirim Tautan Reset
                </Button>
            </div>
        </form>
    </GuestLayout>
</template>
