<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue'
import InputError from '@/Components/InputError.vue'
import InputLabel from '@/Components/InputLabel.vue'
import PrimaryButton from '@/Components/PrimaryButton.vue'
import TextInput from '@/Components/TextInput.vue'
import { Head, Link, useForm } from '@inertiajs/vue3'

const form = useForm({
  name: '',
  email: '',
  phone: '',
  birth_date: '',
  gender: 'prefer_not_to_say',
  address_line: '',
  city: '',
  province: '',
  postal_code: '',
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
  >
    <Head title="Create Account" />

    <div class="auth-form">
      <div class="auth-form__header">
        <p class="auth-form__eyebrow">Create account</p>
        <h2>Get started fast</h2>
        <p>Set up your account to book party packages, business meetings, and table reservations.</p>
      </div>

      <form class="auth-form__body" @submit.prevent="submit">
        <section class="auth-form__section">
          <div>
            <p class="auth-form__section-label">Personal information</p>
            <h3>Tell us about yourself</h3>
          </div>

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

          <div class="auth-form__split auth-form__split--three">
            <div class="auth-form__field">
              <InputLabel for="phone" value="Phone number" />
              <TextInput
                id="phone"
                v-model="form.phone"
                type="text"
                class="auth-form__input"
                required
                autocomplete="tel"
                placeholder="+63 9XX XXX XXXX"
              />
              <InputError class="mt-2" :message="form.errors.phone" />
            </div>

            <div class="auth-form__field">
              <InputLabel for="birth_date" value="Birth date" />
              <TextInput
                id="birth_date"
                v-model="form.birth_date"
                type="date"
                class="auth-form__input"
                required
                autocomplete="bday"
              />
              <InputError class="mt-2" :message="form.errors.birth_date" />
            </div>

            <div class="auth-form__field">
              <InputLabel for="gender" value="Gender" />
              <select id="gender" v-model="form.gender" class="auth-form__input" required>
                <option value="male">Male</option>
                <option value="female">Female</option>
                <option value="non_binary">Non-binary</option>
                <option value="prefer_not_to_say">Prefer not to say</option>
              </select>
              <InputError class="mt-2" :message="form.errors.gender" />
            </div>
          </div>

          <div class="auth-form__field">
            <InputLabel for="address_line" value="Street address" />
            <textarea
              id="address_line"
              v-model="form.address_line"
              class="auth-form__input auth-form__textarea"
              rows="3"
              required
              autocomplete="street-address"
            ></textarea>
            <InputError class="mt-2" :message="form.errors.address_line" />
          </div>

          <div class="auth-form__split auth-form__split--three">
            <div class="auth-form__field">
              <InputLabel for="city" value="City / Municipality" />
              <TextInput
                id="city"
                v-model="form.city"
                type="text"
                class="auth-form__input"
                required
                autocomplete="address-level2"
              />
              <InputError class="mt-2" :message="form.errors.city" />
            </div>

            <div class="auth-form__field">
              <InputLabel for="province" value="Province" />
              <TextInput
                id="province"
                v-model="form.province"
                type="text"
                class="auth-form__input"
                required
                autocomplete="address-level1"
              />
              <InputError class="mt-2" :message="form.errors.province" />
            </div>

            <div class="auth-form__field">
              <InputLabel for="postal_code" value="Postal code" />
              <TextInput
                id="postal_code"
                v-model="form.postal_code"
                type="text"
                class="auth-form__input"
                autocomplete="postal-code"
              />
              <InputError class="mt-2" :message="form.errors.postal_code" />
            </div>
          </div>
        </section>

        <section class="auth-form__section">
          <div>
            <p class="auth-form__section-label">Account security</p>
            <h3>Finish your sign-up</h3>
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
        </section>

        <PrimaryButton class="auth-form__submit" :class="{ 'opacity-25': form.processing }" :disabled="form.processing">
          Create account
        </PrimaryButton>
      </form>

      <div class="auth-form__footer">
        <p>Already have an account?</p>
        <Link :href="route('login')" prefetch class="auth-form__secondary-link">Log in instead</Link>
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

.auth-form__section {
  display: grid;
  gap: 1rem;
  padding: 1.1rem;
  border: 1px solid rgba(31, 31, 31, 0.08);
  border-radius: 1.4rem;
  background: rgba(255, 248, 235, 0.7);
}

.auth-form__section h3 {
  margin-top: 0.25rem;
  font-size: 1.15rem;
  font-weight: 800;
  color: #1f1f1f;
}

.auth-form__section-label {
  color: #9f1914;
  font-size: 0.76rem;
  font-weight: 900;
  letter-spacing: 0.14em;
  text-transform: uppercase;
}

.auth-form__split {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 1rem;
}

.auth-form__split--three {
  grid-template-columns: repeat(3, minmax(0, 1fr));
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

.auth-form__textarea {
  min-height: 6.8rem;
  resize: vertical;
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
