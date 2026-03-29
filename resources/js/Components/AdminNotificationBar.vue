<script setup>
import axios from 'axios'
import { Link } from '@inertiajs/vue3'
import { onBeforeUnmount, onMounted, ref } from 'vue'

const alerts = ref({
  count: 0,
  items: [],
})

let notificationTimer = null

const refreshAlerts = async () => {
  const { data } = await axios.get(route('admin.notifications.bar'))
  alerts.value = data
}

onMounted(() => {
  refreshAlerts()

  notificationTimer = window.setInterval(() => {
    if (document.visibilityState !== 'visible') {
      return
    }

    refreshAlerts()
  }, 12000)
})

onBeforeUnmount(() => {
  if (notificationTimer) {
    window.clearInterval(notificationTimer)
  }
})
</script>

<template>
  <div
    v-if="alerts.count"
    class="rounded-[1.6rem] border border-red-400/30 bg-gradient-to-r from-red-700 via-red-600 to-orange-500 px-5 py-4 text-white shadow-[0_18px_36px_rgba(159,25,20,0.18)]"
  >
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <p class="text-xs font-black uppercase tracking-[0.18em] text-amber-100">New reservations</p>
        <p class="mt-1 text-lg font-bold">{{ alerts.count }} booking{{ alerts.count === 1 ? '' : 's' }} waiting for review</p>
      </div>
      <Link :href="route('admin.bookings')" prefetch class="mcd-button mcd-button--secondary">View pending</Link>
    </div>

    <div class="mt-4 flex flex-wrap gap-3">
      <Link
        v-for="item in alerts.items"
        :key="item.id"
        :href="route('admin.bookings')"
        prefetch
        class="rounded-2xl bg-white/12 px-4 py-3 text-left transition hover:bg-white/18"
      >
        <p class="font-bold">{{ item.booking_reference }}</p>
        <p class="mt-1 text-sm text-white/85">{{ item.customer_name }} | {{ item.branch }}</p>
        <p class="mt-1 text-xs text-white/70">{{ item.event_date }} | {{ item.event_time }}</p>
      </Link>
    </div>
  </div>
</template>
