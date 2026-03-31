<script setup>
import InputError from '@/Components/InputError.vue';
import InputLabel from '@/Components/InputLabel.vue';
import PrimaryButton from '@/Components/PrimaryButton.vue';
import TextInput from '@/Components/TextInput.vue';
import { Link, useForm } from '@inertiajs/vue3';

const props = defineProps({
    mustVerifyEmail: Boolean,
    status: String,
    profile: {
        type: Object,
        required: true,
    },
});

const form = useForm({
    name: props.profile.name ?? '',
    email: props.profile.email ?? '',
    phone: props.profile.phone ?? '',
    birth_date: props.profile.birth_date ?? '',
    gender: props.profile.gender ?? 'prefer_not_to_say',
    address_line: props.profile.address_line ?? '',
    city: props.profile.city ?? '',
    province: props.profile.province ?? '',
    postal_code: props.profile.postal_code ?? '',
});
</script>

<template>
    <section>
        <header>
            <h2 class="text-lg font-medium text-gray-900">Personal Information</h2>

            <p class="mt-1 text-sm text-gray-600">
                Review and update the personal details connected to your reservation account.
            </p>
        </header>

        <form @submit.prevent="form.patch(route('profile.update'), { preserveScroll: true })" class="mt-6 space-y-6">
            <div class="grid gap-6 md:grid-cols-2">
                <div>
                    <InputLabel for="name" value="Full name" />

                    <TextInput
                        id="name"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.name"
                        required
                        autofocus
                        autocomplete="name"
                    />

                    <InputError class="mt-2" :message="form.errors.name" />
                </div>

                <div>
                    <InputLabel for="email" value="Email" />

                    <TextInput
                        id="email"
                        type="email"
                        class="mt-1 block w-full"
                        v-model="form.email"
                        required
                        autocomplete="username"
                    />

                    <InputError class="mt-2" :message="form.errors.email" />
                </div>
            </div>

            <div class="grid gap-6 md:grid-cols-3">
                <div>
                    <InputLabel for="phone" value="Phone number" />

                    <TextInput
                        id="phone"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.phone"
                        required
                        autocomplete="tel"
                    />

                    <InputError class="mt-2" :message="form.errors.phone" />
                </div>

                <div>
                    <InputLabel for="birth_date" value="Birth date" />

                    <TextInput
                        id="birth_date"
                        type="date"
                        class="mt-1 block w-full"
                        v-model="form.birth_date"
                        required
                        autocomplete="bday"
                    />

                    <InputError class="mt-2" :message="form.errors.birth_date" />
                </div>

                <div>
                    <InputLabel for="gender" value="Gender" />

                    <select
                        id="gender"
                        v-model="form.gender"
                        class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500"
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

            <div>
                <InputLabel for="address_line" value="Street address" />

                <textarea
                    id="address_line"
                    v-model="form.address_line"
                    rows="3"
                    class="mt-1 block w-full rounded-xl border-gray-300 shadow-sm focus:border-red-500 focus:ring-red-500"
                    required
                    autocomplete="street-address"
                ></textarea>

                <InputError class="mt-2" :message="form.errors.address_line" />
            </div>

            <div class="grid gap-6 md:grid-cols-3">
                <div>
                    <InputLabel for="city" value="City / Municipality" />

                    <TextInput
                        id="city"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.city"
                        required
                        autocomplete="address-level2"
                    />

                    <InputError class="mt-2" :message="form.errors.city" />
                </div>

                <div>
                    <InputLabel for="province" value="Province" />

                    <TextInput
                        id="province"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.province"
                        required
                        autocomplete="address-level1"
                    />

                    <InputError class="mt-2" :message="form.errors.province" />
                </div>

                <div>
                    <InputLabel for="postal_code" value="Postal code" />

                    <TextInput
                        id="postal_code"
                        type="text"
                        class="mt-1 block w-full"
                        v-model="form.postal_code"
                        autocomplete="postal-code"
                    />

                    <InputError class="mt-2" :message="form.errors.postal_code" />
                </div>
            </div>

            <div v-if="props.mustVerifyEmail && props.profile.email_verified_at === null">
                <p class="text-sm mt-2 text-gray-800">
                    Your email address is unverified.
                    <Link
                        :href="route('verification.send')"
                        method="post"
                        as="button"
                        class="underline text-sm text-gray-600 hover:text-gray-900 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                    >
                        Click here to re-send the verification email.
                    </Link>
                </p>

                <div
                    v-show="props.status === 'verification-link-sent'"
                    class="mt-2 font-medium text-sm text-green-600"
                >
                    A new verification link has been sent to your email address.
                </div>
            </div>

            <div class="flex items-center gap-4">
                <PrimaryButton :disabled="form.processing">Save personal information</PrimaryButton>

                <Transition enter-from-class="opacity-0" leave-to-class="opacity-0" class="transition ease-in-out">
                    <p v-if="form.recentlySuccessful" class="text-sm text-gray-600">Saved.</p>
                </Transition>
            </div>
        </form>
    </section>
</template>
