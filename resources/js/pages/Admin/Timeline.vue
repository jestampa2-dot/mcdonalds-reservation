<script setup>
import { onBeforeUnmount, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import AdminQuickLinks from '@/Components/AdminQuickLinks.vue'
import StatusBadge from '@/Components/StatusBadge.vue'

defineProps({
  notifications: Array,
  history: Array,
})
let dashboardTimer = null

onMounted(() => {
  dashboardTimer = window.setInterval(() => {
    router.reload({
      only: ['notifications', 'history'],
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
</script>

<template>
  <AppShell title="Admin Timeline">
    <section class="mcd-section">
      <div class="mcd-panel p-8">
        <p class="mcd-chip">Timeline</p>
        <h1 class="mt-4 text-4xl">Upcoming events and previous-event history are grouped on their own page.</h1>
        <div class="mt-6">
          <AdminQuickLinks current="timeline" />
        </div>
      </div>
    </section>

    <section class="mcd-section">
      <div class="mcd-grid mcd-grid--2">
        <article class="mcd-panel p-6">
          <p class="mcd-chip">Upcoming notifications</p>
          <div v-if="notifications.length" class="mt-5 space-y-4">
            <div v-for="item in notifications" :key="item.id" class="rounded-3xl bg-white p-5">
              <div class="flex flex-wrap items-center gap-2">
                <strong>{{ item.booking_reference }}</strong>
                <StatusBadge :value="item.status" />
              </div>
              <p class="mt-2 text-sm text-slate-600">{{ item.package_name }} | {{ item.event_type }}</p>
              <p class="mt-1 text-sm text-slate-500">{{ item.branch }} | {{ item.event_date }} | {{ item.event_time }}</p>
              <p class="mt-3 text-sm text-slate-600">{{ item.message }}</p>
            </div>
          </div>
          <div v-else class="mcd-empty mt-5">No upcoming notifications.</div>
        </article>

        <article class="mcd-panel p-6">
          <p class="mcd-chip">Event history</p>
          <div v-if="history.length" class="mt-5 space-y-4">
            <div v-for="item in history" :key="item.id" class="rounded-3xl bg-white p-5">
              <div class="flex flex-wrap items-center gap-2">
                <strong>{{ item.booking_reference }}</strong>
                <StatusBadge :value="item.status" />
                <StatusBadge :value="item.service_status" />
              </div>
              <p class="mt-2 text-sm text-slate-600">{{ item.package_name }} | {{ item.event_type }}</p>
              <p class="mt-1 text-sm text-slate-500">{{ item.branch }} | {{ item.event_date }} | {{ item.event_time }}</p>
              <p class="mt-3 text-sm text-slate-600">Checked in by: {{ item.checked_in_by || 'No check-in recorded' }}</p>
            </div>
          </div>
          <div v-else class="mcd-empty mt-5">No historical event records yet.</div>
        </article>
      </div>
    </section>
  </AppShell>
</template>
