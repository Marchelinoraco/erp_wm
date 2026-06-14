<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps({
    email: {
        type: String,
        required: true,
    },
    token: {
        type: String,
        required: true,
    },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('password.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Reset Password" />

        <h1 class="mb-6 text-lg font-semibold text-gray-900">Atur Ulang Password</h1>

        <form @submit.prevent="submit" class="space-y-4">
            <div class="space-y-1.5">
                <Label for="email">Email</Label>
                <Input id="email" type="email" v-model="form.email" required autofocus autocomplete="username" />
                <p v-if="form.errors.email" class="text-sm text-destructive">{{ form.errors.email }}</p>
            </div>

            <div class="space-y-1.5">
                <Label for="password">Password Baru</Label>
                <Input id="password" type="password" v-model="form.password" required autocomplete="new-password" />
                <p v-if="form.errors.password" class="text-sm text-destructive">{{ form.errors.password }}</p>
            </div>

            <div class="space-y-1.5">
                <Label for="password_confirmation">Konfirmasi Password</Label>
                <Input id="password_confirmation" type="password" v-model="form.password_confirmation" required autocomplete="new-password" />
                <p v-if="form.errors.password_confirmation" class="text-sm text-destructive">{{ form.errors.password_confirmation }}</p>
            </div>

            <div class="flex justify-end pt-2">
                <Button type="submit" :disabled="form.processing">Reset Password</Button>
            </div>
        </form>
    </GuestLayout>
</template>
