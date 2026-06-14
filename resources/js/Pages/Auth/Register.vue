<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Head, Link, useForm } from '@inertiajs/vue3';

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Register" />

        <h1 class="mb-1 text-lg font-semibold text-gray-900">Daftar Akun</h1>
        <p class="mb-6 text-sm text-muted-foreground">Buat akun baru untuk mulai.</p>

        <form @submit.prevent="submit" class="space-y-4">
            <div class="space-y-1.5">
                <Label for="name">Nama</Label>
                <Input id="name" type="text" v-model="form.name" required autofocus autocomplete="name" />
                <p v-if="form.errors.name" class="text-sm text-destructive">{{ form.errors.name }}</p>
            </div>

            <div class="space-y-1.5">
                <Label for="email">Email</Label>
                <Input id="email" type="email" v-model="form.email" required autocomplete="username" />
                <p v-if="form.errors.email" class="text-sm text-destructive">{{ form.errors.email }}</p>
            </div>

            <div class="space-y-1.5">
                <Label for="password">Password</Label>
                <Input id="password" type="password" v-model="form.password" required autocomplete="new-password" />
                <p v-if="form.errors.password" class="text-sm text-destructive">{{ form.errors.password }}</p>
            </div>

            <div class="space-y-1.5">
                <Label for="password_confirmation">Konfirmasi Password</Label>
                <Input id="password_confirmation" type="password" v-model="form.password_confirmation" required autocomplete="new-password" />
                <p v-if="form.errors.password_confirmation" class="text-sm text-destructive">{{ form.errors.password_confirmation }}</p>
            </div>

            <div class="flex items-center justify-between pt-2">
                <Link
                    :href="route('login')"
                    class="text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                >
                    Sudah punya akun?
                </Link>
                <Button type="submit" :disabled="form.processing" :loading="form.processing">
                    {{ form.processing ? 'Mendaftar...' : 'Daftar' }}
                </Button>
            </div>
        </form>
    </GuestLayout>
</template>
