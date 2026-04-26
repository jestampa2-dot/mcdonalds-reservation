<script setup>
import { reactive, ref, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import AdminQuickLinks from '@/Components/AdminQuickLinks.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import SearchToolbar from '@/features/search/components/SearchToolbar.vue'
import { useSearchCollection } from '@/features/search/composables/useSearchCollection'

const props = defineProps({
  stats: Array,
  confirmedEvents: Array,
  staffUsers: Array,
  menuBundles: Array,
  addOns: Array,
  durationOptions: Array,
})

const searchQuery = ref('')

const filteredConfirmedEvents = useSearchCollection(
  () => props.confirmedEvents,
  searchQuery,
  [
    'booking_reference',
    'customer_name',
    'customer_email',
    'customer_phone',
    'package_name',
    'branch',
    'room_choice',
    'event_date',
    'event_time',
    'notes',
    'assigned_staff_name',
  ],
)

const statusState = reactive(Object.fromEntries(props.confirmedEvents.map((booking) => [booking.id, booking.status])))
const crewState = reactive(Object.fromEntries(props.confirmedEvents.map((booking) => [booking.id, booking.assigned_staff_id ?? ''])))
const adjustmentState = reactive(Object.fromEntries(props.confirmedEvents.map((booking) => [booking.id, {
  duration_hours: booking.duration_hours ?? 4,
  extra_menu_bundles: booking.service_adjustments?.extra_menu_bundles ?? [],
  extra_add_ons: booking.service_adjustments?.extra_add_ons ?? [],
}])))

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

const updateStatus = (id) => {
  router.post(route('admin.reservations.status', id), { status: statusState[id] }, { preserveScroll: true, preserveState: true })
}

const markAsDone = (id) => {
  statusState[id] = 'completed'

  router.post(route('admin.reservations.status', id), {
    status: 'completed',
  }, {
    preserveScroll: true,
    preserveState: false,
    onSuccess: () => {
      router.visit(route('admin.timeline'), {
        preserveScroll: true,
        preserveState: false,
      })
    },
  })
}

const updateCrew = (id) => {
  router.post(route('admin.reservations.crew', id), { assigned_staff_id: crewState[id] || null }, { preserveScroll: true, preserveState: true })
}

const updateServiceAdjustments = (id) => {
  router.post(route('staff.reservations.adjustments', id), adjustmentState[id], { preserveScroll: true, preserveState: true })
}

const refreshConfirmed = () => {
  router.reload({
    only: ['stats', 'confirmedEvents', 'staffUsers'],
    preserveScroll: true,
    preserveState: true,
  })
}
</script>

<template>
  <AppShell title="Admin Confirmed Events">
    <section class="mcd-section">
      <div class="mcd-panel p-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <p class="mcd-chip">Confirmed events</p>
            <h1 class="mt-4 text-4xl">Confirmed events</h1>
          </div>
          <button type="button" class="mcd-button mcd-button--ghost" @click="refreshConfirmed">Refresh events</button>
        </div>
        <div class="mt-6">
          <AdminQuickLinks current="confirmed" />
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
      <article class="mcd-panel p-6">
        <SearchToolbar
          v-model="searchQuery"
          :count="filteredConfirmedEvents.length"
          singular-label="confirmed event"
          placeholder="Search event, customer, branch, or booking reference"
          max-width-class="max-w-sm"
        />
        <div v-if="filteredConfirmedEvents.length" class="space-y-4">
          <div v-for="booking in filteredConfirmedEvents" :key="booking.id" class="rounded-2xl bg-amber-50 p-4">
            <div class="grid gap-4 md:grid-cols-[1.1fr,0.8fr,0.8fr]">
              <div>
                <div class="flex flex-wrap items-center gap-2">
                  <strong>{{ booking.booking_reference }}</strong>
                  <StatusBadge :value="booking.status" />
                  <StatusBadge :value="booking.service_status" />
                </div>
                <p class="mt-2 text-sm text-slate-600">{{ booking.package_name }} | {{ booking.event_date }} | {{ booking.event_time }}</p>
                <p class="mt-1 text-sm text-slate-500">Customer: {{ booking.customer_name }}</p>
                <p class="mt-1 text-sm text-slate-500">Contact: {{ booking.customer_email }} | {{ booking.customer_phone }}</p>
                <p class="mt-1 text-sm text-slate-500">Assigned crew: {{ booking.assigned_staff_name || 'Unassigned' }}</p>
                <p class="mt-1 text-sm text-slate-500">Duration: {{ booking.duration_hours }} hours</p>
                <p v-if="booking.customer_profile?.full_address" class="mt-1 text-sm text-slate-500">Address: {{ booking.customer_profile.full_address }}</p>
                <p v-if="booking.customer_profile?.gender || booking.customer_profile?.birth_date_label" class="mt-1 text-sm text-slate-500">
                  <span v-if="booking.customer_profile?.gender">Gender: {{ booking.customer_profile.gender }}</span>
                  <span v-if="booking.customer_profile?.gender && booking.customer_profile?.birth_date_label"> | </span>
                  <span v-if="booking.customer_profile?.birth_date_label">Birth date: {{ booking.customer_profile.birth_date_label }}</span>
                </p>
                <p v-if="booking.manual_menu_items?.length" class="mt-1 text-sm text-slate-500">
                  Manual tray: {{ booking.manual_menu_items.slice(0, 3).map((item) => `${item.quantity} x ${item.item_name}`).join(', ') }}<span v-if="booking.manual_menu_items.length > 3"> and {{ booking.manual_menu_items.length - 3 }} more</span>
                </p>
                <div class="mt-3 rounded-2xl bg-white p-4 text-sm">
                  <p class="font-black uppercase tracking-[0.15em] text-red-700">Special notes</p>
                  <p class="mt-2 text-slate-600">{{ booking.notes || 'None' }}</p>
                </div>
              </div>

              <div class="space-y-3">
                <p class="text-sm font-black uppercase tracking-[0.15em] text-red-700">Status</p>
                <select v-model="statusState[booking.id]" class="mcd-select">
                  <option value="confirmed">confirmed</option>
                  <option value="rescheduled">rescheduled</option>
                  <option value="checked_in">checked in</option>
                  <option value="completed">completed</option>
                  <option value="cancelled">cancelled</option>
                </select>
                <button type="button" class="mcd-button" @click="updateStatus(booking.id)">Save status</button>
                <button type="button" class="mcd-button mcd-button--ghost" @click="markAsDone(booking.id)">Mark as done</button>

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
        <div v-else class="mcd-empty">No confirmed events matched that search.</div>
      </article>
    </section>
  </AppShell>
</template>
