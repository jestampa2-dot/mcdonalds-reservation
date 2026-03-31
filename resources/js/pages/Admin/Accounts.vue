<script setup>
import { computed, reactive, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import AdminQuickLinks from '@/Components/AdminQuickLinks.vue'

const props = defineProps({
  users: Array,
})

const search = ref('')
const roleState = reactive(Object.fromEntries(props.users.map((user) => [user.id, user.role])))

const filteredUsers = computed(() => {
  const term = search.value.trim().toLowerCase()

  if (!term) {
    return props.users
  }

  return props.users.filter((user) =>
    [user.name, user.email, user.role].some((value) => String(value).toLowerCase().includes(term)),
  )
})

const updateRole = (id) => {
  router.post(route('admin.users.role', id), { role: roleState[id] }, { preserveScroll: true, preserveState: true })
}
</script>

<template>
  <AppShell title="Admin Accounts">
    <section class="mcd-section">
      <div class="mcd-panel p-8">
        <p class="mcd-chip">Accounts</p>
        <h1 class="mt-4 text-4xl">Approve and manage user roles on a dedicated account page.</h1>
        <div class="mt-6">
          <AdminQuickLinks current="accounts" />
        </div>
      </div>
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
              placeholder="Search by name, email, or role"
            />
          </div>
          <div class="rounded-2xl bg-amber-50 px-4 py-3 text-sm font-bold text-amber-800">
            {{ filteredUsers.length }} account{{ filteredUsers.length === 1 ? '' : 's' }}
          </div>
        </div>

        <div class="space-y-3">
          <div v-for="user in filteredUsers" :key="user.id" class="rounded-3xl bg-white p-5">
            <div class="grid gap-3 md:grid-cols-[1fr,0.8fr,0.6fr] md:items-center">
              <div>
                <p class="font-bold">{{ user.name }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ user.email }}</p>
              </div>
              <select v-model="roleState[user.id]" class="mcd-select">
                <option value="customer">customer</option>
                <option value="staff">staff</option>
                <option value="manager">manager</option>
                <option value="admin">admin</option>
              </select>
              <button type="button" class="mcd-button" @click="updateRole(user.id)">Approve role</button>
            </div>
          </div>

          <div v-if="!filteredUsers.length" class="rounded-3xl bg-white p-6 text-center text-slate-500">
            No accounts matched your search.
          </div>
        </div>
      </article>
    </section>
  </AppShell>
</template>
