<script setup>
import { reactive } from 'vue'
import { router } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import AdminQuickLinks from '@/Components/AdminQuickLinks.vue'

const props = defineProps({
  users: Array,
})

const roleState = reactive(Object.fromEntries(props.users.map((user) => [user.id, user.role])))

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
        <div class="space-y-3">
          <div v-for="user in users" :key="user.id" class="rounded-3xl bg-white p-5">
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
        </div>
      </article>
    </section>
  </AppShell>
</template>
