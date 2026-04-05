<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import AppShell from '@/Components/AppShell.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';

const props = defineProps({
    mustVerifyEmail: Boolean,
    status: String,
    profile: Object,
});

const page = usePage();

const roleLabel = computed(() => {
    const role = String(page.props.auth?.user?.role ?? 'customer');

    return `${role.charAt(0).toUpperCase()}${role.slice(1)}`;
});

const accountStatus = computed(() => (
    props.profile?.email_verified_at ? 'Verified' : 'Verification pending'
));

const locationLabel = computed(() => {
    const location = [props.profile?.city, props.profile?.province].filter(Boolean).join(', ');

    return location || 'No location yet';
});
</script>

<template>
    <Head title="My Account" />

    <AppShell title="My Account">
        <section class="mcd-section">
            <div class="mcd-panel overflow-hidden">
                <div class="grid gap-6 bg-gradient-to-br from-red-800 via-red-700 to-orange-500 p-8 text-white lg:grid-cols-[1.15fr,0.85fr]">
                    <div class="space-y-4">
                        <p class="mcd-chip bg-white/15 text-white">My Account</p>
                        <h1 class="max-w-2xl text-4xl md:text-5xl">{{ profile.name }}</h1>
                        <p class="text-base text-white/85">{{ profile.email }}</p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-3 lg:grid-cols-1">
                        <div class="rounded-[1.5rem] border border-white/10 bg-white/10 p-5 backdrop-blur">
                            <p class="text-xs font-black uppercase tracking-[0.18em] text-amber-100">Role</p>
                            <p class="mt-3 text-2xl font-black">{{ roleLabel }}</p>
                        </div>
                        <div class="rounded-[1.5rem] border border-white/10 bg-white/10 p-5 backdrop-blur">
                            <p class="text-xs font-black uppercase tracking-[0.18em] text-amber-100">Status</p>
                            <p class="mt-3 text-2xl font-black">{{ accountStatus }}</p>
                        </div>
                        <div class="rounded-[1.5rem] border border-white/10 bg-white/10 p-5 backdrop-blur">
                            <p class="text-xs font-black uppercase tracking-[0.18em] text-amber-100">Location</p>
                            <p class="mt-3 text-xl font-black">{{ locationLabel }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="mcd-section">
            <div class="grid gap-5 xl:grid-cols-[1.2fr,0.8fr]">
                <article class="mcd-panel p-6 sm:p-8">
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                        :profile="profile"
                    />
                </article>

                <div class="space-y-5">
                    <article class="mcd-panel p-6 sm:p-8">
                        <UpdatePasswordForm />
                    </article>

                    <article class="mcd-panel border border-red-200 bg-red-50/90 p-6 sm:p-8">
                        <DeleteUserForm />
                    </article>
                </div>
            </div>
        </section>
    </AppShell>
</template>
