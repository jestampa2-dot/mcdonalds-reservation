<script setup>
import { router } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import AdminQuickLinks from '@/Components/AdminQuickLinks.vue'
import CancelledEventsPanel from '@/features/timeline/components/CancelledEventsPanel.vue'
import EventHistoryPanel from '@/features/timeline/components/EventHistoryPanel.vue'
import UpcomingNotificationsPanel from '@/features/timeline/components/UpcomingNotificationsPanel.vue'

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
        <UpcomingNotificationsPanel :items="notifications" />
        <EventHistoryPanel
          :items="history"
          chip-label="Event history"
          search-placeholder="Search booking reference, package, branch, or date"
        />
        <CancelledEventsPanel :items="cancelledEvents" />
      </div>
    </section>
  </AppShell>
</template>
