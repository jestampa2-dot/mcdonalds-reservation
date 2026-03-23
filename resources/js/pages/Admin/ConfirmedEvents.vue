<script setup>
import { onBeforeUnmount, onMounted, reactive, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import AdminQuickLinks from '@/Components/AdminQuickLinks.vue'
import StatusBadge from '@/Components/StatusBadge.vue'

const props = defineProps({
  stats: Array,
  confirmedEvents: Array,
  staffUsers: Array,
  menuBundles: Array,
  addOns: Array,
  durationOptions: Array,
})
let dashboardTimer = null

const statusState = reactive(Object.fromEntries(props.confirmedEvents.map((booking) => [booking.id, booking.status])))
const crewState = reactive(Object.fromEntries(props.confirmedEvents.map((booking) => [booking.id, booking.assigned_staff_id ?? ''])))
const adjustmentState = reactive(Object.fromEntries(props.confirmedEvents.map((booking) => [booking.id, {
  duration_hours: booking.duration_hours ?? 4,
  extra_menu_bundles: booking.service_adjustments?.extra_menu_bundles ?? [],
  extra_add_ons: booking.service_adjustments?.extra_add_ons ?? [],
}])))

onMounted(() => {
  dashboardTimer = window.setInterval(() => {
    router.reload({
      only: ['stats', 'confirmedEvents', 'staffUsers'],
      preserveScroll: true,
      preserveState: true,
    })
  }, 15000)
})

watch(
  () => props.confirmedEvents,
  (bookings) => {
    bookings.forEach((booking) => {
      statusState[booking.id] = booking.status
      crewState[booking.id] = booking.assigned_staff_id ?? ''
      adjustmentState[booking.id] = {
        duration_hours: booking.duration_hours ?? 4,
        extra_menu_bundles: booking.service_adjustments?.extra_menu_bundles ?? [],
        extra_add_ons: booking.service_adjustments?.extra_add_ons ?? [],
      }
    })
  },
)

onBeforeUnmount(() => {
  if (dashboardTimer) {
    window.clearInterval(dashboardTimer)
  }
})

const updateStatus = (id) => {
  router.post(route('admin.reservations.status', id), { status: statusState[id] }, { preserveScroll: true, preserveState: true })
}

const updateCrew = (id) => {
  router.post(route('admin.reservations.crew', id), { assigned_staff_id: crewState[id] || null }, { preserveScroll: true, preserveState: true })
}

const updateServiceAdjustments = (id) => {
  router.post(route('staff.reservations.adjustments', id), adjustmentState[id], { preserveScroll: true, preserveState: true })
}
</script>

<template>
  <AppShell title="Admin Confirmed Events">
    <section class="mcd-section">
      <div class="mcd-panel p-8">
        <p class="mcd-chip">Confirmed events</p>
        <h1 class="mt-4 text-4xl">Edit confirmed bookings without mixing them into pending approvals.</h1>
        <div class="mt-6">
          <AdminQuickLinks current="confirmed" />
        </div>
      </div>
    </section>

    <section class="mcd-section">
      <article class="mcd-panel p-6">
        <div v-if="confirmedEvents.length" class="space-y-4">
          <div v-for="booking in confirmedEvents" :key="booking.id" class="rounded-2xl bg-amber-50 p-4">
            <div class="grid gap-4 md:grid-cols-[1.1fr,0.8fr,0.8fr]">
              <div>
                <div class="flex flex-wrap items-center gap-2">
                  <strong>{{ booking.booking_reference }}</strong>
                  <StatusBadge :value="booking.status" />
                  <StatusBadge :value="booking.service_status" />
                </div>
                <p class="mt-2 text-sm text-slate-600">{{ booking.package_name }} | {{ booking.event_date }} | {{ booking.event_time }}</p>
                <p class="mt-1 text-sm text-slate-500">Customer: {{ booking.customer_name }} | {{ booking.customer_phone }}</p>
                <p class="mt-1 text-sm text-slate-500">Assigned crew: {{ booking.assigned_staff_name || 'Unassigned' }}</p>
                <p class="mt-1 text-sm text-slate-500">Duration: {{ booking.duration_hours }} hours</p>
                <p class="mt-3 text-sm text-slate-600">{{ booking.notes || 'No special notes provided.' }}</p>
              </div>

              <div class="space-y-3">
                <p class="text-sm font-black uppercase tracking-[0.15em] text-red-700">Update confirmed event</p>
                <select v-model="statusState[booking.id]" class="mcd-select">
                  <option value="confirmed">confirmed</option>
                  <option value="rescheduled">rescheduled</option>
                  <option value="checked_in">checked in</option>
                  <option value="completed">completed</option>
                  <option value="cancelled">cancelled</option>
                </select>
                <button type="button" class="mcd-button" @click="updateStatus(booking.id)">Save status</button>

                <select v-model="crewState[booking.id]" class="mcd-select">
                  <option value="">Unassigned</option>
                  <option v-for="staff in staffUsers" :key="staff.id" :value="staff.id">{{ staff.name }} ({{ staff.role }})</option>
                </select>
                <button type="button" class="mcd-button mcd-button--ghost" @click="updateCrew(booking.id)">Update crew</button>
              </div>

              <div class="rounded-2xl bg-white p-4">
                <p class="text-sm font-black uppercase tracking-[0.15em] text-red-700">Event edits</p>
                <div class="mt-3 space-y-3">
                  <select v-model="adjustmentState[booking.id].duration_hours" class="mcd-select">
                    <option v-for="hours in durationOptions" :key="hours" :value="hours">{{ hours }} hours total</option>
                  </select>

                  <div class="rounded-2xl bg-amber-50 p-3">
                    <p class="text-xs font-black uppercase tracking-[0.15em] text-red-700">Extra food</p>
                    <label v-for="bundle in menuBundles" :key="`${booking.id}-${bundle.code}`" class="mt-2 flex items-center gap-2 text-sm">
                      <input v-model="adjustmentState[booking.id].extra_menu_bundles" :value="bundle.code" type="checkbox" />
                      <span>{{ bundle.name }}</span>
                    </label>
                  </div>

                  <div class="rounded-2xl bg-amber-50 p-3">
                    <p class="text-xs font-black uppercase tracking-[0.15em] text-red-700">Extra services</p>
                    <label v-for="item in addOns" :key="`${booking.id}-${item.code}`" class="mt-2 flex items-center gap-2 text-sm">
                      <input v-model="adjustmentState[booking.id].extra_add_ons" :value="item.code" type="checkbox" />
                      <span>{{ item.name }}</span>
                    </label>
                  </div>

                  <button type="button" class="mcd-button mcd-button--ghost" @click="updateServiceAdjustments(booking.id)">Save event edits</button>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="mcd-empty">No confirmed events yet.</div>
      </article>
    </section>
  </AppShell>
</template>
