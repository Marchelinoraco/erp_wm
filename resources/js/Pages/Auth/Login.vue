<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps({
    canResetPassword: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};
</script>

<template>
    <GuestLayout>
        <Head title="Log in" />

        <h1 class="mb-1 text-lg font-semibold text-gray-900">Masuk</h1>
        <p class="mb-6 text-sm text-muted-foreground">Masuk ke akun ERP Welcome Manado.</p>

        <div v-if="status" class="mb-4 text-sm font-medium text-green-600">
            {{ status }}
        </div>

        <form @submit.prevent="submit" class="space-y-4">
            <div class="space-y-1.5">
                <Label for="email">Email</Label>
                <Input
                    id="email"
                    type="email"
                    v-model="form.email"
                    required
                    autofocus
                    autocomplete="username"
                />
                <p v-if="form.errors.email" class="text-sm text-destructive">{{ form.errors.email }}</p>
            </div>

            <div class="space-y-1.5">
                <Label for="password">Password</Label>
                <Input
                    id="password"
                    type="password"
                    v-model="form.password"
                    required
                    autocomplete="current-password"
                />
                <p v-if="form.errors.password" class="text-sm text-destructive">{{ form.errors.password }}</p>
            </div>

            <label class="flex items-center gap-2 text-sm text-muted-foreground">
                <input
                    type="checkbox"
                    v-model="form.remember"
                    class="h-4 w-4 rounded border-input text-primary focus:ring-ring"
                />
                Ingat saya
            </label>

            <div class="flex items-center justify-between pt-2">
                <Link
                    v-if="canResetPassword"
                    :href="route('password.request')"
                    class="text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                >
                    Lupa password?
                </Link>
                <Button type="submit" class="ml-auto" :disabled="form.processing" :loading="form.processing">
                    {{ form.processing ? 'Masuk...' : 'Masuk' }}
                </Button>
            </div>
        </form>
    </GuestLayout>
</template>
