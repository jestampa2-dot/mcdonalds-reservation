<script setup>
import Checkbox from '@/Components/Checkbox.vue'
import GuestLayout from '@/Layouts/GuestLayout.vue'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

defineProps({
  canResetPassword: Boolean,
  status: String,
})

const form = useForm({
  email: '',
  password: '',
  remember: false,
})

const submit = () => {
  form.post(route('login'), {
    onFinish: () => form.reset('password'),
  })
}
</script>

<template>
  <GuestLayout
    title="Login"
    eyebrow="Operator-ready access"
    heading="Jump back into bookings, approvals, and check-ins from one desktop workspace."
    description="Sign in to manage customer reservations, review schedules, and keep the service floor moving."
  >
    <Head title="Login" />

    <div class="auth-form">
      <div class="auth-form__header">
        <p class="auth-form__eyebrow">Sign in</p>
        <h2>Welcome back</h2>
        <p>Use your account to access customer, admin, or staff tools.</p>
      </div>

      <div v-if="status" class="auth-form__status">
        {{ status }}
      </div>

      <form class="auth-form__body" @submit.prevent="submit">
        <div class="auth-form__field">
          <InputLabel for="email" value="Email" />
          <TextInput
            id="email"
            v-model="form.email"
            type="email"
            class="auth-form__input"
            required
            autofocus
            autocomplete="username"
          />
          <InputError class="mt-2" :message="form.errors.email" />
        </div>

        <div class="auth-form__field">
          <InputLabel for="password" value="Password" />
          <TextInput
            id="password"
            v-model="form.password"
            type="password"
            class="auth-form__input"
            required
            autocomplete="current-password"
          />
          <InputError class="mt-2" :message="form.errors.password" />
        </div>

        <div class="auth-form__meta">
          <label class="auth-form__remember">
            <Checkbox v-model:checked="form.remember" name="remember" />
            <span>Keep me signed in on this desktop</span>
          </label>

          <Link
            v-if="canResetPassword"
            :href="route('password.request')"
            class="auth-form__link"
          >
            Forgot password?
          </Link>
        </div>

        <PrimaryButton class="auth-form__submit" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
          Log in
        </PrimaryButton>
      </form>

      <div class="auth-form__footer">
        <p>New here? Build your reservation workspace in a minute.</p>
        <Link :href="route('register')" class="auth-form__secondary-link">Create account</Link>
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

.auth-form__status {
  border-radius: 1rem;
  background: rgba(47, 157, 78, 0.12);
  color: #146534;
  padding: 0.95rem 1rem;
  font-weight: 700;
}

.auth-form__body {
  display: grid;
  gap: 1.1rem;
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

.auth-form__meta {
  display: flex;
  justify-content: space-between;
  gap: 1rem;
  align-items: center;
  margin-top: 0.35rem;
}

.auth-form__remember {
  display: inline-flex;
  align-items: center;
  gap: 0.55rem;
  color: rgba(31, 31, 31, 0.72);
  font-size: 0.95rem;
}

.auth-form__link,
.auth-form__secondary-link {
  color: #b32217;
  font-weight: 800;
  text-decoration: none;
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

@media (max-width: 760px) {
  .auth-form__meta,
  .auth-form__footer {
    flex-direction: column;
    align-items: flex-start;
  }

  .auth-form__header h2 {
    font-size: 2rem;
  }
}
</style>
