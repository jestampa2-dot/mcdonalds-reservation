<script setup>
import { computed } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import AdminQuickLinks from '@/Components/AdminQuickLinks.vue'

const props = defineProps({
  stats: Array,
  notifications: Array,
  history: Array,
  branchSummaries: Array,
  isMasterAdmin: Boolean,
  masterOverview: Array,
  recentActivities: Array,
})

const adminCards = computed(() => [
  {
    title: 'Pending Bookings',
    href: route('admin.bookings'),
    button: 'Open',
  },
  {
    title: 'Confirmed Events',
    href: route('admin.confirmed'),
    button: 'Open',
  },
  {
    title: 'Availability',
    href: route('admin.availability'),
    button: 'Open',
  },
  {
    title: 'Catalog',
    href: route('admin.catalog'),
    button: 'Open',
  },
  {
    title: 'Branches',
    href: route('admin.branches'),
    button: 'Open',
  },
  {
    title: 'Accounts',
    href: route('admin.accounts'),
    button: 'Open',
  },
  {
    title: 'Reports',
    href: route('admin.reports'),
    button: 'Open',
  },
  {
    title: 'Timeline',
    href: route('admin.timeline'),
    button: 'Open',
  },
  ...(props.isMasterAdmin ? [{
    title: 'Activity Log',
    href: route('admin.activity'),
    button: 'Monitor',
  }] : []),
])

const formatCurrency = (value) =>
  `\u20B1${Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`

const refreshDashboard = () => {
  router.reload({
    only: ['stats', 'notifications', 'history', 'branchSummaries'],
    preserveScroll: true,
    preserveState: true,
  })
}
</script>

<template>
  <AppShell :title="isMasterAdmin ? 'Master Admin Command Center' : 'Admin Hub'">
    <section class="mcd-section">
      <div class="mcd-panel p-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <p class="mcd-chip">{{ isMasterAdmin ? 'Master Admin' : 'Admin hub' }}</p>
            <h1 class="mt-4 max-w-4xl text-4xl">{{ isMasterAdmin ? 'All-branch command center' : 'Admin hub' }}</h1>
            <p v-if="isMasterAdmin" class="mt-3 max-w-3xl text-slate-600">
              Govern branch accounts, reservations, employees, schedules, reports, and system activity from one workspace.
            </p>
          </div>
          <button type="button" class="mcd-button mcd-button--ghost" @click="refreshDashboard">Refresh hub</button>
        </div>
        <div class="mt-6">
          <AdminQuickLinks current="hub" />
        </div>
      </div>

      <div class="mcd-metric-grid">
        <article v-for="item in stats" :key="item.label" class="mcd-metric-card">
          <p class="mcd-metric-card__label">{{ item.label }}</p>
          <p class="mcd-metric-card__value">{{ item.value }}</p>
        </article>
      </div>

      <div v-if="isMasterAdmin" class="mt-6">
        <p class="mcd-chip">Network overview</p>
        <div class="mcd-metric-grid mt-4">
          <article v-for="item in masterOverview" :key="item.label" class="mcd-metric-card">
            <p class="mcd-metric-card__label">{{ item.label }}</p>
            <p class="mcd-metric-card__value">{{ item.value }}</p>
          </article>
        </div>
      </div>
    </section>

    <section class="mcd-section">
      <div class="mcd-command-grid">
        <article v-for="card in adminCards" :key="card.title" class="mcd-command-card">
          <p class="mcd-chip">{{ card.title }}</p>
          <p class="text-2xl">{{ card.title }}</p>
          <Link :href="card.href" prefetch class="mcd-button mt-6">{{ card.button }}</Link>
        </article>
      </div>
    </section>

    <section class="mcd-section">
      <div class="mcd-grid mcd-grid--2">
        <article class="mcd-panel p-6">
          <div class="flex items-center justify-between gap-3">
            <div>
              <p class="mcd-chip">Upcoming alerts</p>
              <h2 class="mt-3 text-3xl">Alerts</h2>
            </div>
            <Link :href="route('admin.timeline')" prefetch class="mcd-button mcd-button--ghost">Open timeline</Link>
          </div>
          <div v-if="notifications.length" class="mt-5 space-y-3">
            <div v-for="item in notifications.slice(0, 4)" :key="item.id" class="rounded-3xl bg-white p-4">
              <strong>{{ item.booking_reference }}</strong>
              <p class="mt-2 text-sm text-slate-600">{{ item.package_name }} | {{ item.branch }}</p>
              <p class="mt-1 text-sm text-slate-500">{{ item.event_date }} | {{ item.event_time }}</p>
            </div>
          </div>
          <div v-else class="mcd-empty mt-5">No alerts.</div>
        </article>

        <article class="mcd-panel p-6">
          <p class="mcd-chip">Active branches</p>
          <h2 class="mt-3 text-3xl">Branches</h2>
          <div class="mt-5 space-y-3">
            <div v-for="branch in branchSummaries" :key="branch.code" class="rounded-3xl bg-white p-4">
              <p class="font-bold">{{ branch.name }}</p>
              <p class="mt-1 text-sm text-slate-500">{{ branch.city }} | {{ branch.code }}</p>
              <div class="mt-3 grid grid-cols-2 gap-2 text-xs font-bold text-slate-600">
                <span>{{ branch.pending_count }} pending</span>
                <span>{{ branch.active_count }} active</span>
                <span>{{ branch.admin_count }} admins</span>
                <span>{{ branch.employee_count }} employees</span>
              </div>
              <p class="mt-3 text-sm font-black text-red-700">{{ formatCurrency(branch.revenue) }} tracked revenue</p>
            </div>
          </div>
        </article>
      </div>
    </section>

    <section v-if="isMasterAdmin" class="mcd-section">
      <article class="mcd-panel p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p class="mcd-chip">Cross-branch monitoring</p>
            <h2 class="mt-3 text-3xl">Recent activity</h2>
          </div>
          <Link :href="route('admin.activity')" prefetch class="mcd-button mcd-button--ghost">View complete log</Link>
        </div>
        <div v-if="recentActivities.length" class="mt-5 grid gap-3">
          <div v-for="activity in recentActivities" :key="activity.id" class="rounded-3xl bg-white p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <p class="font-bold">{{ activity.description }}</p>
                <p class="mt-1 text-sm text-slate-500">
                  {{ activity.actor_name }} · {{ activity.actor_role }} · {{ activity.branch_code || 'All branches' }}
                </p>
              </div>
              <span class="mcd-badge">{{ activity.action }}</span>
            </div>
            <p class="mt-2 text-xs font-bold uppercase tracking-[0.12em] text-slate-400">{{ activity.created_at }}</p>
          </div>
        </div>
        <div v-else class="mcd-empty mt-5">No activity has been recorded yet.</div>
      </article>
    </section>
  </AppShell>
</template>
