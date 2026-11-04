<script setup>
import { computed, ref } from 'vue'
import { router } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import AdminQuickLinks from '@/Components/AdminQuickLinks.vue'

const props = defineProps({
  activities: Array,
  branches: Array,
  filters: Object,
  actionOptions: Array,
})

const search = ref('')
const branch = ref(props.filters?.branch ?? '')
const action = ref(props.filters?.action ?? '')

const filteredActivities = computed(() => {
  const term = search.value.trim().toLowerCase()

  if (!term) {
    return props.activities
  }

  return props.activities.filter((activity) =>
    [activity.description, activity.actor_name, activity.actor_role, activity.action, activity.branch_code]
      .some((value) => String(value ?? '').toLowerCase().includes(term)),
  )
})

const applyFilters = () => {
  router.get(route('admin.activity'), {
    branch: branch.value || undefined,
    action: action.value || undefined,
  }, {
    preserveState: true,
    preserveScroll: true,
    replace: true,
  })
}

const resetFilters = () => {
  branch.value = ''
  action.value = ''
  search.value = ''
  router.get(route('admin.activity'), {}, { replace: true })
}

const actionLabel = (value) => String(value ?? '')
  .replaceAll('.', ' ')
  .replaceAll('_', ' ')
</script>

<template>
  <AppShell title="Master Admin Activity Log">
    <section class="mcd-section">
      <div class="mcd-panel p-8">
        <p class="mcd-chip">Master Admin</p>
        <h1 class="mt-4 text-4xl">Cross-branch activity log</h1>
        <p class="mt-3 max-w-3xl text-slate-600">
          Review booking decisions, schedule changes, employee assignments, account updates, and branch changes across the entire system.
        </p>
        <div class="mt-6">
          <AdminQuickLinks current="activity" />
        </div>
      </div>
    </section>

    <section class="mcd-section">
      <article class="mcd-panel p-6">
        <div class="grid gap-4 xl:grid-cols-[1fr,0.8fr,0.8fr,auto] xl:items-end">
          <div class="mcd-field">
            <label>Search current results</label>
            <input v-model="search" type="search" class="mcd-input" placeholder="Actor, action, booking, or branch" />
          </div>
          <div class="mcd-field">
            <label>Branch</label>
            <select v-model="branch" class="mcd-select">
              <option value="">All branches</option>
              <option v-for="item in branches" :key="item.code" :value="item.code">{{ item.name }}</option>
            </select>
          </div>
          <div class="mcd-field">
            <label>Activity type</label>
            <select v-model="action" class="mcd-select">
              <option value="">All activity types</option>
              <option v-for="item in actionOptions" :key="item" :value="item">{{ actionLabel(item) }}</option>
            </select>
          </div>
          <div class="flex flex-wrap gap-2">
            <button type="button" class="mcd-button" @click="applyFilters">Apply</button>
            <button type="button" class="mcd-button mcd-button--ghost" @click="resetFilters">Reset</button>
          </div>
        </div>

        <div class="mt-5 rounded-2xl bg-amber-50 px-4 py-3 text-sm font-bold text-amber-800">
          Showing {{ filteredActivities.length }} of {{ activities.length }} recorded activities
        </div>

        <div v-if="filteredActivities.length" class="mt-5 grid gap-3">
          <article v-for="activity in filteredActivities" :key="activity.id" class="rounded-3xl bg-white p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
              <div>
                <p class="font-bold text-slate-900">{{ activity.description }}</p>
                <p class="mt-2 text-sm text-slate-500">
                  {{ activity.actor_name }} · {{ actionLabel(activity.actor_role) }} · {{ activity.branch_code || 'System-wide' }}
                </p>
              </div>
              <span class="mcd-badge">{{ actionLabel(activity.action) }}</span>
            </div>
            <p class="mt-3 text-xs font-black uppercase tracking-[0.14em] text-slate-400">{{ activity.created_at }}</p>
          </article>
        </div>

        <div v-else class="mcd-empty mt-5">No activity matches the selected filters.</div>
      </article>
    </section>
  </AppShell>
</template>
