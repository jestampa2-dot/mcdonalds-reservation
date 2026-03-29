<script setup>
import { Link, router } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import AdminQuickLinks from '@/Components/AdminQuickLinks.vue'

defineProps({
  stats: Array,
  notifications: Array,
  history: Array,
  branchSummaries: Array,
})

const adminCards = [
  {
    title: 'Pending Bookings',
    copy: 'Review payment proof, notes, and customer details before approving new reservations.',
    href: route('admin.bookings'),
    button: 'Open pending list',
  },
  {
    title: 'Confirmed Events',
    copy: 'Edit active events, assign crew, update status, and manage extra orders or time extensions.',
    href: route('admin.confirmed'),
    button: 'Open confirmed events',
  },
  {
    title: 'Availability',
    copy: 'Monitor live branch dates and slot capacity without crowding the main dashboard.',
    href: route('admin.availability'),
    button: 'Open availability',
  },
  {
    title: 'Catalog',
    copy: 'Edit event types, package details, prices, and availability from one page.',
    href: route('admin.catalog'),
    button: 'Open catalog',
  },
  {
    title: 'Branches',
    copy: 'Add and monitor store branches or supported reservation types from one page.',
    href: route('admin.branches'),
    button: 'Open branches',
  },
  {
    title: 'Accounts',
    copy: 'Approve roles for admin, manager, staff, and customer accounts.',
    href: route('admin.accounts'),
    button: 'Open accounts',
  },
  {
    title: 'Reports',
    copy: 'See analytics, inventory pressure, staffing assignments, and rate settings.',
    href: route('admin.reports'),
    button: 'Open reports',
  },
  {
    title: 'Timeline',
    copy: 'Track upcoming event notifications and previous-event history in a clean timeline page.',
    href: route('admin.timeline'),
    button: 'Open timeline',
  },
]

const refreshDashboard = () => {
  router.reload({
    only: ['stats', 'notifications', 'history', 'branchSummaries'],
    preserveScroll: true,
    preserveState: true,
  })
}
</script>

<template>
  <AppShell title="Admin Hub">
    <section class="mcd-section">
      <div class="mcd-panel p-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <p class="mcd-chip">Admin hub</p>
            <h1 class="mt-4 max-w-4xl text-4xl">A cleaner command center for approvals, branches, reporting, and live event control.</h1>
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
    </section>

    <section class="mcd-section">
      <div class="mcd-command-grid">
        <article v-for="card in adminCards" :key="card.title" class="mcd-command-card">
          <p class="mcd-chip">{{ card.title }}</p>
          <p class="text-2xl">{{ card.title }}</p>
          <p class="text-sm leading-6 text-slate-600">{{ card.copy }}</p>
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
              <h2 class="mt-3 text-3xl">Next events to review</h2>
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
          <div v-else class="mcd-empty mt-5">No upcoming alerts.</div>
        </article>

        <article class="mcd-panel p-6">
          <p class="mcd-chip">Active branches</p>
          <h2 class="mt-3 text-3xl">Current locations</h2>
          <div class="mt-5 space-y-3">
            <div v-for="branch in branchSummaries" :key="branch.code" class="rounded-3xl bg-white p-4">
              <p class="font-bold">{{ branch.name }}</p>
              <p class="mt-1 text-sm text-slate-500">{{ branch.city }} | {{ branch.code }}</p>
            </div>
          </div>
        </article>
      </div>
    </section>
  </AppShell>
</template>
