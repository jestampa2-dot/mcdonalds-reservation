<script setup>
import { reactive, ref, watch } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import EventHistoryPanel from '@/features/timeline/components/EventHistoryPanel.vue'

const props = defineProps({
  prepList: Array,
  todayBookings: Array,
  notifications: Array,
  history: Array,
  statusOptions: Array,
  menuBundles: Array,
  addOns: Array,
  durationOptions: Array,
})

const checkInForm = useForm({
  code: '',
})

const statusState = reactive(Object.fromEntries(props.todayBookings.map((booking) => [booking.id, booking.service_status])))
const adjustmentState = reactive(Object.fromEntries(props.todayBookings.map((booking) => [booking.id, {
  duration_hours: booking.duration_hours ?? 4,
  extra_menu_bundles: booking.service_adjustments?.extra_menu_bundles ?? [],
  extra_add_ons: booking.service_adjustments?.extra_add_ons ?? [],
}])))
const notificationItems = ref(props.notifications)
const historyItems = ref(props.history)

const checkIn = () => {
  checkInForm.post(route('staff.check-in'), {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => checkInForm.reset(),
  })
}

const updateStatus = (id) => {
  router.post(route('staff.reservations.service-status', id), {
    service_status: statusState[id],
  }, {
    preserveScroll: true,
    preserveState: true,
  })
}

const updateServiceAdjustments = (id) => {
  router.post(route('staff.reservations.adjustments', id), adjustmentState[id], {
    preserveScroll: true,
    preserveState: true,
  })
}

watch(
  () => props.todayBookings,
  (bookings) => {
    bookings.forEach((booking) => {
      statusState[booking.id] = booking.service_status
      adjustmentState[booking.id] = {
        duration_hours: booking.duration_hours ?? 4,
        extra_menu_bundles: booking.service_adjustments?.extra_menu_bundles ?? [],
        extra_add_ons: booking.service_adjustments?.extra_add_ons ?? [],
      }
    })
  },
)

watch(
  () => props.notifications,
  (items) => {
    notificationItems.value = items
  },
)

watch(
  () => props.history,
  (items) => {
    historyItems.value = items
  },
)

const refreshStaffView = () => {
  router.reload({
    only: ['prepList', 'todayBookings', 'notifications', 'history'],
    preserveScroll: true,
    preserveState: true,
  })
}
</script>

<template>
  <AppShell title="Staff Dashboard">
    <section class="mcd-section">
      <div class="grid gap-5 xl:grid-cols-[1.1fr,1fr,1fr]">
        <article class="mcd-panel p-8">
          <p class="mcd-chip">Staff</p>
          <h1 class="mt-4 text-4xl">Staff dashboard</h1>
          <button type="button" class="mcd-button mcd-button--ghost mt-6" @click="refreshStaffView">Refresh staff view</button>
        </article>

        <EventHistoryPanel
          :items="historyItems"
          chip-label="History"
          title="Previous events"
          search-placeholder="Search history by event, branch, or reference"
          :show-header-badge="true"
          header-badge-class="mcd-badge--success"
          list-class="space-y-3"
          card-class="rounded-3xl bg-white p-4"
        />

        <article class="mcd-panel mcd-panel--dark p-8">
          <p class="text-sm uppercase tracking-[0.2em] text-amber-300">Check-in scanner</p>
          <div class="mt-5 flex flex-col gap-3">
            <input v-model="checkInForm.code" type="text" class="mcd-input" placeholder="Enter booking reference or check-in code" />
            <button type="button" class="mcd-button" @click="checkIn">Check in guest</button>
          </div>
          <p class="mt-3 text-sm text-white/70">Admin confirmation required.</p>
        </article>
      </div>
    </section>

    <section class="mcd-section">
      <div class="mcd-grid mcd-grid--2">
        <article class="mcd-panel p-6">
          <div class="flex items-center justify-between gap-3">
            <div>
              <p class="mcd-chip">Notifications</p>
              <h2 class="mt-3 text-3xl">Upcoming events</h2>
            </div>
            <span class="mcd-badge mcd-badge--pending">{{ notificationItems.length }} alerts</span>
          </div>
          <div v-if="notificationItems.length" class="mt-5 space-y-4">
            <div v-for="item in notificationItems" :key="item.id" class="rounded-3xl bg-white p-5">
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <div class="flex flex-wrap items-center gap-2">
                    <strong>{{ item.booking_reference }}</strong>
                    <StatusBadge :value="item.status" />
                  </div>
                  <p class="mt-2 text-sm text-slate-600">{{ item.package_name }} | {{ item.event_type }}</p>
                  <p class="mt-1 text-sm text-slate-500">{{ item.branch }} | {{ item.event_date }} | {{ item.event_time }}</p>
                </div>
                <span class="mcd-badge" :class="item.assigned_staff_name ? 'mcd-badge--success' : 'mcd-badge--danger'">
                  {{ item.assigned_staff_name || 'Open crew slot' }}
                </span>
              </div>
              <p class="mt-4 rounded-2xl bg-amber-50 px-4 py-3 text-sm text-slate-700">{{ item.message }}</p>
            </div>
          </div>
          <div v-else class="mcd-empty mt-5">No alerts.</div>
        </article>

        <article class="mcd-panel p-6">
          <p class="mcd-chip">Prep</p>
          <div v-if="prepList.length" class="mt-5 space-y-4">
            <div v-for="item in prepList" :key="item.booking_reference" class="rounded-3xl bg-white p-5">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="font-bold">{{ item.booking_reference }}</p>
                  <p class="mt-1 text-sm text-slate-500">{{ item.time }} | {{ item.branch }}</p>
                </div>
                <span class="mcd-badge mcd-badge--success">{{ item.guest_name }}</span>
              </div>
              <p class="mt-4 text-sm font-bold text-slate-700">{{ item.package_name }}</p>
              <ul class="mt-3 space-y-1 text-sm text-slate-600">
                <li v-for="prep in item.items" :key="prep">{{ prep }}</li>
              </ul>
              <div class="mt-4 rounded-2xl bg-amber-50 p-4 text-sm text-slate-700">
                <p class="font-black uppercase tracking-[0.15em] text-red-700">Prep reminder</p>
                <p class="mt-2">{{ item.reminder }}</p>
                <p class="mt-1 font-bold">Target prep time: {{ item.prep_deadline }}</p>
              </div>
            </div>
          </div>
          <div v-else class="mcd-empty mt-5">No prep items.</div>
        </article>
      </div>
    </section>

    <section class="mcd-section">
      <article class="mcd-panel p-6">
        <p class="mcd-chip">Live floor management</p>
        <div v-if="todayBookings.length" class="mt-5 space-y-4">
          <div v-for="booking in todayBookings" :key="booking.id" class="rounded-3xl bg-white p-5">
            <div class="grid gap-4 xl:grid-cols-[1fr,0.8fr,1fr]">
              <div>
                <div class="flex flex-wrap items-center gap-2">
                  <strong>{{ booking.booking_reference }}</strong>
                  <StatusBadge :value="booking.status" />
                  <StatusBadge :value="booking.service_status" />
                </div>
                <p class="mt-2 text-sm text-slate-600">{{ booking.package_name }} | {{ booking.branch }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ booking.event_date }} | {{ booking.event_time }} | {{ booking.duration_hours }} hours</p>
                <p class="mt-1 text-sm text-slate-500">Customer: {{ booking.customer_name }}</p>
                <p v-if="booking.manual_menu_items?.length" class="mt-1 text-sm text-slate-500">
                  Manual tray: {{ booking.manual_menu_items.slice(0, 3).map((item) => `${item.quantity} x ${item.item_name}`).join(', ') }}<span v-if="booking.manual_menu_items.length > 3"> and {{ booking.manual_menu_items.length - 3 }} more</span>
                </p>
                <p class="mt-3 text-sm text-slate-600">{{ booking.notes || 'None' }}</p>
              </div>

              <div class="space-y-3">
                <p class="text-sm font-black uppercase tracking-[0.15em] text-red-700">Floor status</p>
                <select v-model="statusState[booking.id]" class="mcd-select">
                  <option v-for="option in statusOptions" :key="option" :value="option">{{ option }}</option>
                </select>
                <button type="button" class="mcd-button" @click="updateStatus(booking.id)">Update status</button>
              </div>

              <div class="rounded-2xl bg-amber-50 p-4">
                <p class="text-sm font-black uppercase tracking-[0.15em] text-red-700">Ongoing event edits</p>
                <div class="mt-3 space-y-3">
                  <select v-model="adjustmentState[booking.id].duration_hours" class="mcd-select">
                    <option v-for="hours in durationOptions" :key="hours" :value="hours">{{ hours }} hours total</option>
                  </select>

                  <div class="rounded-2xl bg-white p-3">
                    <p class="text-xs font-black uppercase tracking-[0.15em] text-red-700">Add extra food</p>
                    <label v-for="bundle in menuBundles" :key="`${booking.id}-${bundle.code}`" class="mt-2 flex items-center gap-2 text-sm">
                      <input v-model="adjustmentState[booking.id].extra_menu_bundles" :value="bundle.code" type="checkbox" />
                      <span>{{ bundle.name }}</span>
                    </label>
                  </div>

                  <div class="rounded-2xl bg-white p-3">
                    <p class="text-xs font-black uppercase tracking-[0.15em] text-red-700">Add extra services</p>
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
        <div v-else class="mcd-empty mt-5">No active events.</div>
      </article>
    </section>
  </AppShell>
</template>
