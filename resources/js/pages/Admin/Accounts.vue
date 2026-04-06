<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import AdminQuickLinks from '@/Components/AdminQuickLinks.vue'

const props = defineProps({
  users: Array,
  canManageAccounts: Boolean,
})

const search = ref('')

const createForm = useForm({
  name: '',
  email: '',
  phone: '',
  role: 'customer',
  password: '',
  password_confirmation: '',
})

const emptyEditState = (user) => ({
  name: user.name ?? '',
  email: user.email ?? '',
  phone: user.phone ?? '',
  role: user.role ?? 'customer',
  password: '',
  password_confirmation: '',
})

const accountEdits = reactive({})

watch(
  () => props.users,
  (users) => {
    Object.keys(accountEdits).forEach((key) => {
      delete accountEdits[key]
    })

    users.forEach((user) => {
      accountEdits[user.id] = emptyEditState(user)
    })
  },
  { immediate: true },
)

const filteredUsers = computed(() => {
  const term = search.value.trim().toLowerCase()

  if (! term) {
    return props.users
  }

  return props.users.filter((user) =>
    [user.name, user.email, user.phone, user.role].some((value) => String(value ?? '').toLowerCase().includes(term)),
  )
})

const createAccount = () => {
  createForm.post(route('admin.users.store'), {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      createForm.reset('name', 'email', 'phone', 'role', 'password', 'password_confirmation')
      createForm.role = 'customer'
    },
  })
}

const updateAccount = (id) => {
  router.post(route('admin.users.update', id), accountEdits[id], {
    preserveScroll: true,
    preserveState: true,
  })
}

const deleteAccount = (id) => {
  if (! window.confirm('Delete this account? This cannot be undone.')) {
    return
  }

  router.delete(route('admin.users.destroy', id), {
    preserveScroll: true,
    preserveState: true,
  })
}
</script>

<template>
  <AppShell title="Admin Accounts">
    <section class="mcd-section">
      <div class="mcd-panel p-8">
        <p class="mcd-chip">Accounts</p>
        <h1 class="mt-4 text-4xl">Accounts</h1>
        <div class="mt-6">
          <AdminQuickLinks current="accounts" />
        </div>
      </div>
    </section>

    <section v-if="canManageAccounts" class="mcd-section">
      <article class="mcd-panel p-6">
        <p class="mcd-chip">Create account</p>
        <form class="mt-5 grid gap-4" @submit.prevent="createAccount">
          <div class="mcd-grid mcd-grid--2">
            <input v-model="createForm.name" type="text" class="mcd-input" placeholder="Full name" />
            <input v-model="createForm.email" type="email" class="mcd-input" placeholder="Email address" />
          </div>
          <div class="mcd-grid mcd-grid--2">
            <input v-model="createForm.phone" type="text" class="mcd-input" placeholder="Phone number" />
            <select v-model="createForm.role" class="mcd-select">
              <option value="customer">customer</option>
              <option value="staff">staff</option>
              <option value="manager">manager</option>
              <option value="admin">admin</option>
            </select>
          </div>
          <div class="mcd-grid mcd-grid--2">
            <input v-model="createForm.password" type="password" class="mcd-input" placeholder="Password" />
            <input v-model="createForm.password_confirmation" type="password" class="mcd-input" placeholder="Confirm password" />
          </div>
          <button type="submit" class="mcd-button" :disabled="createForm.processing">
            {{ createForm.processing ? 'Creating...' : 'Create account' }}
          </button>
        </form>
      </article>
    </section>

    <section class="mcd-section">
      <article class="mcd-panel p-6">
        <div class="mb-5 grid gap-3 md:grid-cols-[1fr,auto] md:items-center">
          <div class="mcd-field">
            <label>Search accounts</label>
            <input
              v-model="search"
              type="text"
              class="mcd-input"
              placeholder="Search by name, email, phone, or role"
            />
          </div>
          <div class="rounded-2xl bg-amber-50 px-4 py-3 text-sm font-bold text-amber-800">
            {{ filteredUsers.length }} account{{ filteredUsers.length === 1 ? '' : 's' }}
          </div>
        </div>

        <div class="space-y-3">
          <form
            v-for="user in filteredUsers"
            :key="user.id"
            class="rounded-3xl bg-white p-5"
            @submit.prevent="updateAccount(user.id)"
          >
            <div class="grid gap-4 xl:grid-cols-[1.15fr,0.9fr,auto] xl:items-start">
              <div class="grid gap-3">
                <div class="mcd-grid mcd-grid--2">
                  <input
                    v-model="accountEdits[user.id].name"
                    type="text"
                    class="mcd-input"
                    placeholder="Full name"
                    :disabled="!canManageAccounts"
                  />
                  <input
                    v-model="accountEdits[user.id].email"
                    type="email"
                    class="mcd-input"
                    placeholder="Email address"
                    :disabled="!canManageAccounts"
                  />
                </div>
                <div class="mcd-grid mcd-grid--2">
                  <input
                    v-model="accountEdits[user.id].phone"
                    type="text"
                    class="mcd-input"
                    placeholder="Phone number"
                    :disabled="!canManageAccounts"
                  />
                  <select v-model="accountEdits[user.id].role" class="mcd-select" :disabled="!canManageAccounts">
                    <option value="customer">customer</option>
                    <option value="staff">staff</option>
                    <option value="manager">manager</option>
                    <option value="admin">admin</option>
                  </select>
                </div>
                <div v-if="canManageAccounts" class="mcd-grid mcd-grid--2">
                  <input
                    v-model="accountEdits[user.id].password"
                    type="password"
                    class="mcd-input"
                    placeholder="New password"
                  />
                  <input
                    v-model="accountEdits[user.id].password_confirmation"
                    type="password"
                    class="mcd-input"
                    placeholder="Confirm new password"
                  />
                </div>
              </div>

              <div class="rounded-2xl bg-amber-50 px-4 py-4 text-sm text-slate-600">
                <p class="font-black uppercase tracking-[0.12em] text-red-700">Account details</p>
                <p class="mt-3"><strong>Current role:</strong> {{ user.role }}</p>
                <p class="mt-1"><strong>Phone:</strong> {{ user.phone || 'Not set' }}</p>
                <p class="mt-1"><strong>Created:</strong> {{ user.created_at || 'Unknown' }}</p>
              </div>

              <div class="flex flex-wrap gap-3 xl:flex-col">
                <button
                  v-if="canManageAccounts"
                  type="submit"
                  class="mcd-button"
                >
                  Save account
                </button>
                <button
                  v-if="canManageAccounts"
                  type="button"
                  class="mcd-button mcd-button--ghost"
                  @click="deleteAccount(user.id)"
                >
                  Delete account
                </button>
              </div>
            </div>
          </form>

          <div v-if="!filteredUsers.length" class="rounded-3xl bg-white p-6 text-center text-slate-500">
            No matches.
          </div>
        </div>
      </article>
    </section>
  </AppShell>
</template>
