<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'

defineProps({
  eventTypes: Array,
  branches: Array,
  featuredPackages: Array,
  stats: Array,
})

const page = usePage()

const heroPillars = [
  'Live branch availability',
  'Manual food and drink ordering',
  'Admin approvals and staff prep flow',
]

const formatCurrency = (value) =>
  `\u20B1${Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
</script>

<template>
  <AppShell title="Home">
    <section class="mcd-hero">
      <div class="space-y-6">
        <span class="mcd-chip">Fast-food quick booking for parties, meetings, and table reservations</span>
        <div class="space-y-4">
          <h1 class="max-w-3xl text-4xl leading-tight md:text-6xl">
            Turn every McDonald&apos;s event into a polished, branch-ready experience before guests arrive.
          </h1>
          <p class="max-w-2xl text-lg text-white/90">
            Launch a polished reservation experience with live slot visibility, menu pre-orders, proof-of-payment upload,
            customer QR passes, branch operations dashboards, and staff-ready prep lists.
          </p>
        </div>

        <div class="grid gap-3 md:grid-cols-3">
          <div
            v-for="pillar in heroPillars"
            :key="pillar"
            class="rounded-[1.4rem] border border-white/15 bg-white/10 px-4 py-3 text-sm font-bold text-white/90 backdrop-blur"
          >
            {{ pillar }}
          </div>
        </div>

        <div class="flex flex-wrap gap-3">
          <Link
            :href="page.props.auth?.user ? route('reservations.create') : route('register')"
            prefetch
            class="mcd-button mcd-button--secondary"
          >
            Check Availability
          </Link>
          <Link
            :href="page.props.auth?.user ? route('dashboard') : route('login')"
            prefetch
            class="mcd-button"
          >
            Open Dashboard
          </Link>
        </div>
      </div>

      <div class="mcd-panel p-6 text-slate-900">
        <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">McDonald&apos;s-style booking stack</p>
        <div class="mt-4 grid gap-4">
          <div class="rounded-[1.8rem] bg-red-50 p-5">
            <p class="text-xs font-black uppercase tracking-[0.18em] text-red-700">Customer flow</p>
            <h2 class="mt-3 text-2xl">Fast booking with cleaner choices</h2>
            <p class="mt-2 text-sm leading-6 text-slate-700">
              Guided event selection, room rentals, live schedule checks, menu ordering, and proof-of-payment upload.
            </p>
          </div>
          <div class="grid gap-4 md:grid-cols-2">
            <div class="rounded-[1.7rem] bg-amber-50 p-5">
              <p class="text-xs font-black uppercase tracking-[0.18em] text-amber-700">Admin</p>
              <p class="mt-3 text-lg font-black text-slate-900">Database-managed catalog, approvals, inventory, and branch control.</p>
            </div>
            <div class="rounded-[1.7rem] bg-white p-5">
              <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">Crew</p>
              <p class="mt-3 text-lg font-black text-slate-900">Prep lists, check-in control, and event-floor updates in one view.</p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="mcd-section">
      <div class="mcd-metric-grid">
        <article v-for="item in stats" :key="item.label" class="mcd-metric-card">
          <p class="mcd-metric-card__label">{{ item.label }}</p>
          <p class="mcd-metric-card__value">{{ item.value }}</p>
        </article>
      </div>
    </section>

    <section class="mcd-section">
      <div>
        <p class="mcd-chip">Customer-facing pages</p>
        <h2 class="mt-3 text-3xl">Choose the event flow that fits the guest</h2>
      </div>

      <div class="mcd-command-grid">
        <article v-for="type in eventTypes" :key="type.label" class="mcd-command-card">
          <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">{{ type.icon }}</p>
          <h3 class="mt-3 text-2xl">{{ type.label }}</h3>
          <p class="mt-3 text-sm leading-6 text-slate-600">{{ type.description }}</p>
        </article>
      </div>
    </section>

    <section class="mcd-section">
      <div>
        <p class="mcd-chip">Store locator</p>
        <h2 class="mt-3 text-3xl">Branches built for events and quick-turn table service</h2>
      </div>

      <div class="mcd-grid mcd-grid--3">
        <article v-for="branch in branches" :key="branch.code" class="mcd-panel p-6">
          <div class="flex items-start justify-between gap-3">
            <div>
              <h3 class="text-xl">{{ branch.name }}</h3>
              <p class="mt-1 text-sm text-slate-500">{{ branch.city }}</p>
            </div>
            <span class="mcd-chip">Up to {{ branch.max_guests }} guests</span>
          </div>

          <div class="mt-5 flex flex-wrap gap-2">
            <span
              v-for="(supported, key) in branch.supports"
              :key="key"
              class="mcd-badge"
              :class="supported ? 'mcd-badge--success' : 'mcd-badge--danger'"
            >
              {{ key }}
            </span>
          </div>

          <a :href="branch.map_url" target="_blank" rel="noreferrer" class="mt-6 inline-flex text-sm font-bold text-red-700">
            Open in Maps
          </a>
        </article>
      </div>
    </section>

    <section class="mcd-section">
      <div>
        <p class="mcd-chip">Featured packages</p>
        <h2 class="mt-3 text-3xl">Fast picks for birthdays, meetings, and group meals</h2>
      </div>

      <div class="mcd-command-grid">
        <article v-for="item in featuredPackages" :key="item.code" class="mcd-command-card">
          <p class="text-sm uppercase tracking-[0.25em] text-slate-500">{{ item.guest_range }}</p>
          <h3 class="mt-2 text-2xl">{{ item.name }}</h3>
          <p class="mt-4 text-3xl text-red-700">{{ formatCurrency(item.price) }}</p>
          <ul class="mt-4 space-y-2 text-sm text-slate-600">
            <li v-for="feature in item.features" :key="feature">{{ feature }}</li>
          </ul>
        </article>
      </div>
    </section>
  </AppShell>
</template>
