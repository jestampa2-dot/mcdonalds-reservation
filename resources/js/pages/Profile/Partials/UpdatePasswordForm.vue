<script setup>
import InputError from '@/Components/InputError.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const form = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const updatePassword = () => {
    form.put(route('password.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset(),
        onError: () => {
            if (form.errors.password) {
                form.reset('password', 'password_confirmation');
                passwordInput.value.focus();
            }
            if (form.errors.current_password) {
                form.reset('current_password');
                currentPasswordInput.value.focus();
            }
        },
    });
};
</script>

<template>
    <section class="space-y-6">
        <header>
            <p class="text-xs font-black uppercase tracking-[0.18em] text-red-700">Security</p>
            <h2 class="mt-3 text-3xl font-black text-slate-900">Update Password</h2>
        </header>

        <form @submit.prevent="updatePassword" class="space-y-6">
            <div class="grid gap-2">
                <label for="current_password" class="text-sm font-black uppercase tracking-[0.08em] text-slate-600">Current password</label>

                <input
                    id="current_password"
                    ref="currentPasswordInput"
                    v-model="form.current_password"
                    type="password"
                    class="mcd-input"
                    autocomplete="current-password"
                />

                <InputError :message="form.errors.current_password" class="mt-2" />
            </div>

            <div class="grid gap-2">
                <label for="password" class="text-sm font-black uppercase tracking-[0.08em] text-slate-600">New password</label>

                <input
                    id="password"
                    ref="passwordInput"
                    v-model="form.password"
                    type="password"
                    class="mcd-input"
                    autocomplete="new-password"
                />

                <InputError :message="form.errors.password" class="mt-2" />
            </div>

            <div class="grid gap-2">
                <label for="password_confirmation" class="text-sm font-black uppercase tracking-[0.08em] text-slate-600">Confirm password</label>

                <input
                    id="password_confirmation"
                    v-model="form.password_confirmation"
                    type="password"
                    class="mcd-input"
                    autocomplete="new-password"
                />

                <InputError :message="form.errors.password_confirmation" class="mt-2" />
            </div>

            <div class="flex items-center gap-4">
                <button type="submit" class="mcd-button" :disabled="form.processing">Save password</button>
            </div>
        </form>
    </section>
</template>
