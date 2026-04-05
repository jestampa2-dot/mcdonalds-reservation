<script setup>
import InputError from '@/Components/InputError.vue';
import Modal from '@/Components/Modal.vue';
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
    password: '',
});

const confirmUserDeletion = () => {
    confirmingUserDeletion.value = true;

    nextTick(() => passwordInput.value.focus());
};

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    confirmingUserDeletion.value = false;

    form.reset();
};
</script>

<template>
    <section class="space-y-6">
        <header>
            <p class="text-xs font-black uppercase tracking-[0.18em] text-red-700">Danger zone</p>
            <h2 class="mt-3 text-3xl font-black text-slate-900">Delete Account</h2>

            <p class="mt-3 rounded-2xl bg-white px-4 py-3 text-sm text-slate-700">
                This permanently removes your account and reservation data.
            </p>
        </header>

        <button type="button" class="inline-flex items-center justify-center rounded-full bg-red-700 px-5 py-3 text-sm font-black uppercase tracking-[0.08em] text-white transition hover:bg-red-800" @click="confirmUserDeletion">
            Delete account
        </button>

        <Modal :show="confirmingUserDeletion" @close="closeModal">
            <div class="p-6">
                <h2 class="text-xl font-black text-slate-900">
                    Delete account?
                </h2>

                <p class="mt-3 text-sm text-slate-600">
                    Enter your password to confirm.
                </p>

                <div class="mt-6">
                    <input
                        id="password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        class="mcd-input w-full"
                        placeholder="Password"
                        @keyup.enter="deleteUser"
                    />

                    <InputError :message="form.errors.password" class="mt-2" />
                </div>

                <div class="mt-6 flex justify-end">
                    <button type="button" class="mcd-button mcd-button--ghost" @click="closeModal">Cancel</button>

                    <button type="button" class="mcd-button ml-3 bg-red-700 hover:bg-red-800" :class="{ 'opacity-25': form.processing }" :disabled="form.processing" @click="deleteUser">
                        Delete account
                    </button>
                </div>
            </div>
        </Modal>
    </section>
</template>
