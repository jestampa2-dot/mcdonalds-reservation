<script setup>
import { router } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import AdminQuickLinks from '@/Components/AdminQuickLinks.vue'
import StatusBadge from '@/Components/StatusBadge.vue'

defineProps({
  notifications: Array,
  history: Array,
  cancelledEvents: Array,
})

const refreshTimeline = () => {
  router.reload({
    only: ['notifications', 'history', 'cancelledEvents'],
    preserveScroll: true,
    preserveState: true,
  })
}
</script>

<template>
  <AppShell title="Admin Timeline">
    <section class="mcd-section">
      <div class="mcd-panel p-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <p class="mcd-chip">Timeline</p>
            <h1 class="mt-4 text-4xl">Timeline</h1>
          </div>
          <button type="button" class="mcd-button mcd-button--ghost" @click="refreshTimeline">Refresh timeline</button>
        </div>
        <div class="mt-6">
          <AdminQuickLinks current="timeline" />
        </div>
      </div>
    </section>

    <section class="mcd-section">
      <div class="grid gap-6 xl:grid-cols-[0.95fr,0.95fr,0.95fr]">
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
          <div v-else class="mcd-empty mt-5">No notifications.</div>
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
          <div v-else class="mcd-empty mt-5">No history.</div>
        </article>

        <article class="mcd-panel p-6">
          <p class="mcd-chip">Cancelled events</p>
          <div v-if="cancelledEvents.length" class="mt-5 space-y-4">
            <div v-for="item in cancelledEvents" :key="item.id" class="rounded-3xl bg-white p-5">
              <div class="flex flex-wrap items-center gap-2">
                <strong>{{ item.booking_reference }}</strong>
                <StatusBadge :value="item.status" />
              </div>
              <p class="mt-2 text-sm text-slate-600">{{ item.package_name }} | {{ item.event_type }}</p>
              <p class="mt-1 text-sm text-slate-500">{{ item.branch }} | {{ item.event_date }} | {{ item.event_time }}</p>
              <p class="mt-3 text-sm text-slate-600">Customer: {{ item.customer_name }}</p>
              <p class="mt-1 text-sm text-slate-600">Notes: {{ item.cancelled_note || 'No cancellation note.' }}</p>
            </div>
          </div>
          <div v-else class="mcd-empty mt-5">No cancelled events.</div>
        </article>
      </div>
    </section>
  </AppShell>
</template>
