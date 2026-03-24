<script setup>
import { onBeforeUnmount, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import AdminQuickLinks from '@/Components/AdminQuickLinks.vue'

defineProps({
  pricing: Object,
  report: Object,
  inventory: Array,
  staffAssignments: Array,
})
let dashboardTimer = null

onMounted(() => {
  dashboardTimer = window.setInterval(() => {
    router.reload({
      only: ['pricing', 'report', 'inventory', 'staffAssignments'],
      preserveScroll: true,
      preserveState: true,
    })
  }, 15000)
})

onBeforeUnmount(() => {
  if (dashboardTimer) {
    window.clearInterval(dashboardTimer)
  }
})

const formatCurrency = (value) =>
  `₱${Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
</script>

<template>
  <AppShell title="Admin Reports">
    <section class="mcd-section">
      <div class="mcd-panel p-8">
        <p class="mcd-chip">Reports</p>
        <h1 class="mt-4 text-4xl">Analytics, inventory, and staffing are now separated from the booking workflow.</h1>
        <div class="mt-6">
          <AdminQuickLinks current="reports" />
        </div>
      </div>
    </section>

    <section class="mcd-section">
      <div class="mcd-grid mcd-grid--2">
        <article class="mcd-panel p-6">
          <p class="mcd-chip">Pricing</p>
          <div class="mt-5 grid gap-4">
            <div class="rounded-3xl bg-red-50 p-5">
              <p class="text-sm uppercase tracking-[0.2em] text-red-700">Weekend rate</p>
              <p class="mt-2 text-3xl">{{ pricing.weekend_multiplier }}x</p>
            </div>
            <div class="rounded-3xl bg-amber-50 p-5">
              <p class="text-sm uppercase tracking-[0.2em] text-red-700">Holiday rate</p>
              <p class="mt-2 text-3xl">{{ pricing.holiday_multiplier }}x</p>
            </div>
            <div class="rounded-3xl bg-white p-5">
              <p class="text-sm uppercase tracking-[0.2em] text-red-700">Extension hourly rate</p>
              <p class="mt-2 text-3xl">{{ formatCurrency(pricing.extension_hourly_rate) }}</p>
            </div>
          </div>
        </article>

        <article class="mcd-panel p-6">
          <p class="mcd-chip">Analytics</p>
          <div class="mt-5 space-y-4">
            <div class="rounded-3xl bg-white p-5">
              <p class="text-sm uppercase tracking-[0.2em] text-red-700">Top event types</p>
              <div class="mt-3 space-y-2">
                <div v-for="item in report.top_event_types" :key="item.type" class="flex items-center justify-between">
                  <span>{{ item.type }}</span>
                  <strong>{{ item.count }}</strong>
                </div>
              </div>
            </div>
            <div class="rounded-3xl bg-white p-5">
              <p class="text-sm uppercase tracking-[0.2em] text-red-700">Branch mix</p>
              <div class="mt-3 space-y-2">
                <div v-for="item in report.branch_mix" :key="item.branch" class="flex items-center justify-between">
                  <span>{{ item.branch }}</span>
                  <strong>{{ item.count }}</strong>
                </div>
              </div>
            </div>
          </div>
        </article>
      </div>
    </section>

    <section class="mcd-section">
      <div class="mcd-grid mcd-grid--2">
        <article class="mcd-panel p-6">
          <p class="mcd-chip">Inventory</p>
          <div class="mt-5 space-y-4">
            <div v-for="branch in inventory" :key="branch.branch" class="rounded-3xl bg-white p-5">
              <p class="font-bold">{{ branch.branch }}</p>
              <div class="mt-3 space-y-2">
                <div v-for="item in branch.alerts" :key="item.item" class="flex items-center justify-between">
                  <span>{{ item.item }}</span>
                  <strong :class="item.low ? 'text-red-700' : 'text-slate-700'">{{ item.projected }} projected</strong>
                </div>
              </div>
            </div>
          </div>
        </article>

        <article class="mcd-panel p-6">
          <p class="mcd-chip">Staff assignments</p>
          <div class="mt-5 space-y-3">
            <div v-for="item in staffAssignments" :key="item.booking_reference" class="rounded-3xl bg-white p-5">
              <p class="font-bold">{{ item.booking_reference }}</p>
              <p class="mt-1 text-sm text-slate-500">{{ item.branch }} | {{ item.slot }}</p>
              <p class="mt-2 text-sm text-slate-600">{{ item.event_type }} | Host: {{ item.host }}</p>
            </div>
          </div>
        </article>
      </div>
    </section>
  </AppShell>
</template>
