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
            Make every McDonald&apos;s event feel organized before guests even arrive.
          </h1>
          <p class="max-w-2xl text-lg text-white/90">
            Launch a polished reservation experience with live slot visibility, menu pre-orders, proof-of-payment upload,
            customer QR passes, branch operations dashboards, and staff-ready prep lists.
          </p>
        </div>

        <div class="flex flex-wrap gap-3">
          <Link
            :href="page.props.auth?.user ? route('reservations.create') : route('register')"
            class="mcd-button mcd-button--secondary"
          >
            Check Availability
          </Link>
          <Link
            :href="page.props.auth?.user ? route('dashboard') : route('login')"
            class="mcd-button"
          >
            Open Dashboard
          </Link>
        </div>
      </div>

      <div class="mcd-panel p-6 text-slate-900">
        <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">What&apos;s included</p>
        <div class="mt-4 space-y-4">
          <div class="rounded-3xl bg-red-50 p-4">
            <h2 class="text-xl">Customer-Facing Booking Wizard</h2>
            <p class="mt-2 text-sm leading-6 text-slate-700">
              Guided event selection, branch finder, menu and add-on customization, live slot checks, and proof-of-payment upload.
            </p>
          </div>
          <div class="rounded-3xl bg-amber-50 p-4">
            <h2 class="text-xl">Admin Command Center</h2>
            <p class="mt-2 text-sm leading-6 text-slate-700">
              Weekly booking visibility, inventory alerts, host assignments, weekend pricing rules, and quick analytics.
            </p>
          </div>
          <div class="rounded-3xl bg-white p-4">
            <h2 class="text-xl">Crew Floor View</h2>
            <p class="mt-2 text-sm leading-6 text-slate-700">
              Daily prep queue, check-in lookup, and real-time room/table status toggles for active events.
            </p>
          </div>
        </div>
      </div>
    </section>

    <section class="mcd-section">
      <div class="mcd-grid mcd-grid--3">
        <article v-for="item in stats" :key="item.label" class="mcd-panel p-6">
          <p class="text-sm uppercase tracking-[0.25em] text-slate-500">{{ item.label }}</p>
          <p class="mt-3 text-4xl text-red-700">{{ item.value }}</p>
        </article>
      </div>
    </section>

    <section class="mcd-section">
      <div>
        <p class="mcd-chip">Customer-facing pages</p>
        <h2 class="mt-3 text-3xl">Choose the event flow that fits the guest</h2>
      </div>

      <div class="mcd-grid mcd-grid--3">
        <article v-for="type in eventTypes" :key="type.label" class="mcd-panel p-6">
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

      <div class="mcd-grid mcd-grid--3">
        <article v-for="item in featuredPackages" :key="item.code" class="mcd-panel p-6">
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
