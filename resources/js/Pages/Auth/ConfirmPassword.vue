<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Head, useForm } from '@inertiajs/vue3';

const form = useForm({
    password: '',
});

const submit = () => {
    form.post(route('password.confirm'), {
        onFinish: () => form.reset(),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Confirm Password" />

        <h1 class="mb-1 text-lg font-semibold text-gray-900">Konfirmasi Password</h1>
        <p class="mb-6 text-sm text-muted-foreground">
            Ini area aman. Konfirmasi password kamu sebelum melanjutkan.
        </p>

        <form @submit.prevent="submit" class="space-y-4">
            <div class="space-y-1.5">
                <Label for="password">Password</Label>
                <Input id="password" type="password" v-model="form.password" required autocomplete="current-password" autofocus />
                <p v-if="form.errors.password" class="text-sm text-destructive">{{ form.errors.password }}</p>
            </div>

            <div class="flex justify-end pt-2">
                <Button type="submit" :disabled="form.processing">Konfirmasi</Button>
            </div>
        </form>
    </GuestLayout>
</template>
