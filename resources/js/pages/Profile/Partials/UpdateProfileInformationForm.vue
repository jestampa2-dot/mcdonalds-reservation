<script setup>
import InputError from '@/Components/InputError.vue';
import { Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps({
    mustVerifyEmail: Boolean,
    status: String,
    profile: {
        type: Object,
        required: true,
    },
});

const photoPreview = ref(props.profile.profile_photo_url ?? '');

const form = useForm({
    _method: 'patch',
    name: props.profile.name ?? '',
    email: props.profile.email ?? '',
    phone: props.profile.phone ?? '',
    sms_notifications_enabled: props.profile.sms_notifications_enabled ?? false,
    birth_date: props.profile.birth_date ?? '',
    gender: props.profile.gender ?? 'prefer_not_to_say',
    address_line: props.profile.address_line ?? '',
    city: props.profile.city ?? '',
    province: props.profile.province ?? '',
    postal_code: props.profile.postal_code ?? '',
    profile_photo: null,
});

const selectProfilePhoto = (event) => {
    const file = event.target.files?.[0] ?? null;

    form.profile_photo = file;

    if (file) {
        photoPreview.value = URL.createObjectURL(file);
    }
};

const submitProfile = () => {
    form.post(route('profile.update'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            form.reset('profile_photo');
        },
    });
};
</script>

<template>
    <section class="space-y-6">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-xs font-black uppercase tracking-[0.18em] text-red-700">Profile</p>
                <h2 class="mt-3 text-3xl font-black text-slate-900">Personal Information</h2>
            </div>
            <span class="rounded-full bg-amber-50 px-4 py-2 text-xs font-black uppercase tracking-[0.14em] text-amber-700">
                Account details
            </span>
        </header>

        <form @submit.prevent="submitProfile" class="space-y-6">
            <div class="rounded-[1.5rem] border border-amber-100 bg-amber-50/70 p-4">
                <div class="grid gap-4 sm:grid-cols-[auto,1fr] sm:items-center">
                    <div class="flex h-24 w-24 items-center justify-center overflow-hidden rounded-full bg-red-700 text-2xl font-black text-white">
                        <img
                            v-if="photoPreview"
                            :src="photoPreview"
                            alt="Profile photo preview"
                            class="h-full w-full object-cover"
                        />
                        <span v-else>{{ String(form.name || 'U').charAt(0).toUpperCase() }}</span>
                    </div>
                    <div class="grid gap-2">
                        <label for="profile_photo" class="text-sm font-black uppercase tracking-[0.08em] text-slate-600">Profile picture</label>
                        <input
                            id="profile_photo"
                            type="file"
                            accept="image/*"
                            class="mcd-input"
                            @change="selectProfilePhoto"
                        />
                        <p class="text-xs font-semibold text-slate-500">Use a square image if possible. Maximum size: 2 MB.</p>
                        <InputError class="mt-1" :message="form.errors.profile_photo" />
                    </div>
                </div>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <div class="grid gap-2">
                    <label for="name" class="text-sm font-black uppercase tracking-[0.08em] text-slate-600">Full name</label>

                    <input
                        id="name"
                        type="text"
                        v-model="form.name"
                        class="mcd-input"
                        required
                        autofocus
                        autocomplete="name"
                    />

                    <InputError class="mt-2" :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <label for="email" class="text-sm font-black uppercase tracking-[0.08em] text-slate-600">Email</label>

                    <input
                        id="email"
                        type="email"
                        v-model="form.email"
                        class="mcd-input"
                        required
                        autocomplete="username"
                    />

                    <InputError class="mt-2" :message="form.errors.email" />
                </div>
            </div>

            <div class="grid gap-6 md:grid-cols-3">
                <div class="grid gap-2">
                    <label for="phone" class="text-sm font-black uppercase tracking-[0.08em] text-slate-600">Phone number</label>

                    <input
                        id="phone"
                        type="text"
                        v-model="form.phone"
                        class="mcd-input"
                        required
                        autocomplete="tel"
                    />

                    <InputError class="mt-2" :message="form.errors.phone" />
                </div>

                <div class="grid gap-2">
                    <label for="birth_date" class="text-sm font-black uppercase tracking-[0.08em] text-slate-600">Birth date</label>

                    <input
                        id="birth_date"
                        type="date"
                        v-model="form.birth_date"
                        class="mcd-input"
                        required
                        autocomplete="bday"
                    />

                    <InputError class="mt-2" :message="form.errors.birth_date" />
                </div>

                <div class="grid gap-2">
                    <label for="gender" class="text-sm font-black uppercase tracking-[0.08em] text-slate-600">Gender</label>

                    <select
                        id="gender"
                        v-model="form.gender"
                        class="mcd-select"
                        required
                    >
                        <option value="male">Male</option>
                        <option value="female">Female</option>
                        <option value="non_binary">Non-binary</option>
                        <option value="prefer_not_to_say">Prefer not to say</option>
                    </select>

                    <InputError class="mt-2" :message="form.errors.gender" />
                </div>
            </div>

            <div v-if="props.profile.role === 'customer'" class="rounded-2xl border border-amber-100 bg-amber-50/70 p-4">
                <label for="sms_notifications_enabled" class="flex items-center gap-3 text-sm font-bold text-slate-900">
                    <input id="sms_notifications_enabled" v-model="form.sms_notifications_enabled" type="checkbox" class="rounded border-slate-300 text-red-700 focus:ring-red-700" />
                    Receive booking updates by SMS
                </label>
                <p class="mt-2 text-sm text-slate-600">Text me when my booking is received, confirmed, rescheduled, cancelled, or completed. I can turn this off anytime.</p>
                <InputError class="mt-2" :message="form.errors.sms_notifications_enabled" />
            </div>

            <div class="grid gap-2">
                <label for="address_line" class="text-sm font-black uppercase tracking-[0.08em] text-slate-600">Street address</label>

                <textarea
                    id="address_line"
                    v-model="form.address_line"
                    rows="3"
                    class="mcd-textarea"
                    autocomplete="street-address"
                ></textarea>

                <InputError class="mt-2" :message="form.errors.address_line" />
            </div>

            <div class="grid gap-6 md:grid-cols-3">
                <div class="grid gap-2">
                    <label for="city" class="text-sm font-black uppercase tracking-[0.08em] text-slate-600">City / Municipality</label>

                    <input
                        id="city"
                        type="text"
                        v-model="form.city"
                        class="mcd-input"
                        autocomplete="address-level2"
                    />

                    <InputError class="mt-2" :message="form.errors.city" />
                </div>

                <div class="grid gap-2">
                    <label for="province" class="text-sm font-black uppercase tracking-[0.08em] text-slate-600">Province</label>

                    <input
                        id="province"
                        type="text"
                        v-model="form.province"
                        class="mcd-input"
                        autocomplete="address-level1"
                    />

                    <InputError class="mt-2" :message="form.errors.province" />
                </div>

                <div class="grid gap-2">
                    <label for="postal_code" class="text-sm font-black uppercase tracking-[0.08em] text-slate-600">Postal code</label>

                    <input
                        id="postal_code"
                        type="text"
                        v-model="form.postal_code"
                        class="mcd-input"
                        autocomplete="postal-code"
                    />

                    <InputError class="mt-2" :message="form.errors.postal_code" />
                </div>
            </div>

            <div v-if="props.mustVerifyEmail && props.profile.email_verified_at === null">
                <p class="rounded-2xl bg-amber-50 px-4 py-3 text-sm text-slate-700">
                    Email not verified.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="ml-1 font-bold text-red-700"
                    >
                        Send again.
                    </Link>
                </p>

                <div
                    v-show="props.status === 'verification-link-sent'"
                    class="mt-2 rounded-2xl bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700"
                >
                    Verification link sent.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <button type="submit" class="mcd-button" :disabled="form.processing">Save personal information</button>
            </div>
        </form>
    </section>
</template>
