<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

const stats = [
  { label: 'Booking types', value: '3' },
  { label: 'Dashboard views', value: '3' },
  { label: 'Proof uploads', value: 'Yes' },
]

const form = useForm({
  name: '',
  email: '',
  password: '',
  password_confirmation: '',
})

const submit = () => {
  form.post(route('register'), {
    onFinish: () => form.reset('password', 'password_confirmation'),
  })
}
</script>

<template>
  <GuestLayout
    title="Create Account"
    eyebrow="Launch your account"
    heading="Create a modern reservation workspace for guests, managers, and service crew."
    description="Register once, then start booking events, tracking approvals, and managing check-ins from a richer desktop experience."
    :stats="stats"
  >
    <Head title="Create Account" />

    <div class="auth-form">
      <div class="auth-form__header">
        <p class="auth-form__eyebrow">Create account</p>
        <h2>Get started fast</h2>
        <p>Set up your account to book party packages, business meetings, and table reservations.</p>
      </div>

      <form class="auth-form__body" @submit.prevent="submit">
        <div class="auth-form__split">
          <div class="auth-form__field">
            <InputLabel for="name" value="Full name" />
            <TextInput
              id="name"
              v-model="form.name"
              type="text"
              class="auth-form__input"
              required
              autofocus
              autocomplete="name"
            />
            <InputError class="mt-2" :message="form.errors.name" />
          </div>

          <div class="auth-form__field">
            <InputLabel for="email" value="Email" />
            <TextInput
              id="email"
              v-model="form.email"
              type="email"
              class="auth-form__input"
              required
              autocomplete="username"
            />
            <InputError class="mt-2" :message="form.errors.email" />
          </div>
        </div>

        <div class="auth-form__split">
          <div class="auth-form__field">
            <InputLabel for="password" value="Password" />
            <TextInput
              id="password"
              v-model="form.password"
              type="password"
              class="auth-form__input"
              required
              autocomplete="new-password"
            />
            <InputError class="mt-2" :message="form.errors.password" />
          </div>

          <div class="auth-form__field">
            <InputLabel for="password_confirmation" value="Confirm password" />
            <TextInput
              id="password_confirmation"
              v-model="form.password_confirmation"
              type="password"
              class="auth-form__input"
              required
              autocomplete="new-password"
            />
            <InputError class="mt-2" :message="form.errors.password_confirmation" />
          </div>
        </div>

        <div class="auth-form__highlights">
          <span>Book birthdays with menu bundles</span>
          <span>Upload proof of payment</span>
          <span>Track bookings from your dashboard</span>
        </div>

        <PrimaryButton class="auth-form__submit" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
          Create account
        </PrimaryButton>
      </form>

      <div class="auth-form__footer">
        <p>Already have an account?</p>
        <Link :href="route('login')" class="auth-form__secondary-link">Log in instead</Link>
      </div>
    </div>
  </GuestLayout>
</template>

<style scoped>
.auth-form {
  display: grid;
  gap: 1.5rem;
}

.auth-form__header h2 {
  margin-top: 0.4rem;
  font-size: 2.4rem;
  color: #1f1f1f;
}

.auth-form__header p:last-child {
  margin-top: 0.75rem;
  color: rgba(31, 31, 31, 0.7);
  line-height: 1.7;
}

.auth-form__eyebrow {
  display: inline-flex;
  padding: 0.35rem 0.7rem;
  border-radius: 999px;
  background: rgba(255, 199, 44, 0.22);
  color: #9f1914;
  font-size: 0.78rem;
  font-weight: 800;
  text-transform: uppercase;
  letter-spacing: 0.12em;
}

.auth-form__body {
  display: grid;
  gap: 1.15rem;
}

.auth-form__split {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1rem;
}

.auth-form__field {
  display: grid;
  gap: 0.4rem;
}

.auth-form__input {
  width: 100%;
  border-radius: 1rem;
  border: 1px solid rgba(31, 31, 31, 0.12);
  background: rgba(255, 255, 255, 0.96);
  padding: 0.95rem 1rem;
}

.auth-form__highlights {
  display: flex;
  flex-wrap: wrap;
  gap: 0.7rem;
  padding-top: 0.35rem;
}

.auth-form__highlights span {
  display: inline-flex;
  align-items: center;
  padding: 0.5rem 0.8rem;
  border-radius: 999px;
  background: rgba(255, 199, 44, 0.16);
  color: #822014;
  font-size: 0.82rem;
  font-weight: 800;
}

.auth-form__submit {
  justify-content: center;
  border-radius: 999px !important;
  background: linear-gradient(135deg, #da291c, #f26b21) !important;
  padding: 1rem 1.2rem !important;
  font-size: 1rem !important;
}

.auth-form__footer {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 1rem;
  border-top: 1px solid rgba(31, 31, 31, 0.08);
  padding-top: 1.25rem;
  color: rgba(31, 31, 31, 0.68);
}

.auth-form__secondary-link {
  color: #b32217;
  font-weight: 800;
  text-decoration: none;
}

@media (max-width: 760px) {
  .auth-form__split {
    grid-template-columns: 1fr;
  }

  .auth-form__footer {
    flex-direction: column;
    align-items: flex-start;
  }

  .auth-form__header h2 {
    font-size: 2rem;
  }
}
</style>
