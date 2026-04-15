<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import AdminQuickLinks from '@/Components/AdminQuickLinks.vue'
import Modal from '@/Components/Modal.vue'

const props = defineProps({
  users: Array,
  canManageAccounts: Boolean,
})

const search = ref('')
const accountPendingDeletion = ref(null)
const deletingAccountId = ref(null)

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

const deleteModalOpen = computed(() => Boolean(accountPendingDeletion.value))

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

const askToDeleteAccount = (user) => {
  accountPendingDeletion.value = user
}

const closeDeleteModal = () => {
  if (deletingAccountId.value) {
    return
  }

  accountPendingDeletion.value = null
}

const deleteAccount = () => {
  if (!accountPendingDeletion.value) {
    return
  }

  const id = accountPendingDeletion.value.id
  deletingAccountId.value = id

  router.delete(route('admin.users.destroy', id), {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      accountPendingDeletion.value = null
    },
    onFinish: () => {
      deletingAccountId.value = null
    },
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
                  @click="askToDeleteAccount(user)"
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

    <Modal :show="deleteModalOpen" max-width="md" @close="closeDeleteModal">
      <div class="account-delete-dialog">
        <div class="account-delete-dialog__brand" aria-hidden="true">M</div>
        <p class="mcd-chip">Account control</p>
        <h2>Delete account?</h2>
        <p class="account-delete-dialog__copy">
          This permanently removes the account and cannot be undone.
        </p>

        <div class="account-delete-dialog__target">
          <span>Selected account</span>
          <strong>{{ accountPendingDeletion?.name || 'Unnamed account' }}</strong>
          <small>{{ accountPendingDeletion?.email }}</small>
        </div>

        <div class="account-delete-dialog__actions">
          <button type="button" class="mcd-button mcd-button--ghost" @click="closeDeleteModal">
            Cancel
          </button>
          <button
            type="button"
            class="mcd-button account-delete-dialog__danger"
            :disabled="Boolean(deletingAccountId)"
            @click="deleteAccount"
          >
            {{ deletingAccountId ? 'Deleting...' : 'Delete account' }}
          </button>
        </div>
      </div>
    </Modal>
  </AppShell>
</template>

<style scoped>
.account-delete-dialog {
  position: relative;
  display: grid;
  gap: 1rem;
  padding: 2rem;
  overflow: hidden;
  background:
    radial-gradient(circle at top right, rgba(255, 199, 44, 0.42), transparent 32%),
    linear-gradient(145deg, #fff9ec 0%, #fff0cc 48%, #fff7e7 100%);
  color: var(--mcd-ink);
}

.account-delete-dialog::before {
  content: '';
  position: absolute;
  inset: 0 0 auto 0;
  height: 0.55rem;
  background: linear-gradient(90deg, var(--mcd-red), var(--mcd-gold), var(--mcd-red));
}

.account-delete-dialog__brand {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 4rem;
  height: 4rem;
  border-radius: 50%;
  background: var(--mcd-gold);
  color: var(--mcd-red);
  font-family: "Arial Black", "Franklin Gothic Heavy", sans-serif;
  font-size: 2.35rem;
  line-height: 1;
  box-shadow: 0 16px 30px rgba(216, 154, 0, 0.22);
}

.account-delete-dialog h2 {
  font-size: clamp(2rem, 4vw, 2.75rem);
  line-height: 1;
}

.account-delete-dialog__copy {
  color: rgba(36, 23, 20, 0.72);
  font-weight: 700;
  line-height: 1.7;
}

.account-delete-dialog__target {
  display: grid;
  gap: 0.35rem;
  padding: 1rem;
  border-radius: 1.25rem;
  background: rgba(255, 255, 255, 0.74);
  border: 1px solid rgba(218, 41, 28, 0.12);
  box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.76);
}

.account-delete-dialog__target span {
  color: var(--mcd-red-deep);
  font-size: 0.74rem;
  font-weight: 900;
  letter-spacing: 0.16em;
  text-transform: uppercase;
}

.account-delete-dialog__target strong {
  color: var(--mcd-ink);
  font-size: 1.08rem;
}

.account-delete-dialog__target small {
  color: rgba(36, 23, 20, 0.58);
  overflow-wrap: anywhere;
}

.account-delete-dialog__actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: flex-end;
  gap: 0.75rem;
  margin-top: 0.5rem;
}

.account-delete-dialog__danger {
  background: linear-gradient(135deg, #9f1914, #da291c);
}

.account-delete-dialog__danger:disabled {
  cursor: wait;
  opacity: 0.65;
}

@media (max-width: 640px) {
  .account-delete-dialog {
    padding: 1.4rem;
  }

  .account-delete-dialog__actions {
    display: grid;
  }
}
</style>
