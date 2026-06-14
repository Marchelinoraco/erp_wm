<script setup>
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
    Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter,
} from '@/Components/ui/dialog';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;
    nextTick(() => passwordInput.value?.$el?.focus?.());
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value?.$el?.focus?.(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;
    form.clearErrors();
    form.reset();
};
</script>

<template>
    <section class="space-y-5">
        <header>
            <h2 class="text-lg font-medium text-gray-900">Hapus Akun</h2>
            <p class="mt-1 text-sm text-muted-foreground">
                Setelah akun dihapus, semua data akan hilang permanen. Simpan dulu data yang ingin
                kamu pertahankan sebelum menghapus.
            </p>
        </header>

        <Button variant="destructive" @click="confirmUserDeletion">Hapus Akun</Button>

        <Dialog v-model:open="confirmingUserDeletion">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Yakin ingin menghapus akun?</DialogTitle>
                    <DialogDescription>
                        Setelah dihapus, seluruh data akan hilang permanen. Masukkan password untuk
                        mengonfirmasi penghapusan akun.
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-1.5">
                    <Label for="password" class="sr-only">Password</Label>
                    <Input
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        placeholder="Password"
                        @keyup.enter="deleteUser"
                    />
                    <p v-if="form.errors.password" class="text-sm text-destructive">{{ form.errors.password }}</p>
                </div>

                <DialogFooter>
                    <Button variant="outline" @click="closeModal">Batal</Button>
                    <Button variant="destructive" :disabled="form.processing" @click="deleteUser">
                        Hapus Akun
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </section>
</template>
