<script setup>
import { onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
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
})

const searchQuery = ref('')
const isRefreshing = ref(false)
let refreshTimer = null
const formatCurrency = (value) =>
  `\u20B1${Number(value ?? 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`

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

watch(
  () => props.confirmedEvents,
  (bookings) => {
    bookings.forEach((booking) => {
      statusState[booking.id] = booking.status
      crewState[booking.id] = booking.assigned_staff_id ?? ''
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
      router.visit(`${route('admin.reports')}#completed-event-reports`, {
        preserveScroll: true,
        preserveState: false,
      })
    },
  })
}

const updateCrew = (id) => {
  router.post(route('admin.reservations.crew', id), { assigned_staff_id: crewState[id] || null }, { preserveScroll: true, preserveState: true })
}

const refreshConfirmed = () => {
  if (isRefreshing.value) return

  isRefreshing.value = true
  router.reload({
    only: ['stats', 'confirmedEvents', 'staffUsers'],
    preserveScroll: true,
    preserveState: true,
    onFinish: () => {
      isRefreshing.value = false
    },
  })
}

const refreshWhenAvailable = () => {
  const activeElement = document.activeElement
  const isEditing = activeElement?.matches('input, select, textarea')

  if (!isEditing) refreshConfirmed()
}

onMounted(() => {
  refreshTimer = window.setInterval(refreshWhenAvailable, 10000)
  window.addEventListener('focus', refreshWhenAvailable)
  document.addEventListener('visibilitychange', refreshWhenAvailable)
})

onBeforeUnmount(() => {
  if (refreshTimer) window.clearInterval(refreshTimer)
  window.removeEventListener('focus', refreshWhenAvailable)
  document.removeEventListener('visibilitychange', refreshWhenAvailable)
})
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
          <button type="button" class="mcd-button mcd-button--ghost" :disabled="isRefreshing" @click="refreshConfirmed">
            {{ isRefreshing ? 'Refreshing' : 'Refresh events' }}
          </button>
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
            <div class="grid gap-4 lg:grid-cols-[1.35fr,0.65fr]">
              <div>
                <div class="flex flex-wrap items-center gap-2">
                  <strong>{{ booking.booking_reference }}</strong>
                  <StatusBadge :value="booking.status" />
                  <StatusBadge :value="booking.service_status" />
                </div>
                <p class="mt-2 text-sm text-slate-600">{{ booking.package_name }} | {{ booking.event_date }} | {{ booking.event_time }}</p>
                <p class="mt-1 text-sm text-slate-500">Venue: {{ booking.venue_name || booking.branch }}<span v-if="booking.venue_full_address"> | {{ booking.venue_full_address }}</span></p>
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
                <div class="mt-4 flex items-center justify-between gap-4 rounded-2xl bg-red-700 px-4 py-3 text-white">
                  <div>
                    <span class="text-sm font-black uppercase tracking-[0.15em]">Customer bill total</span>
                    <p class="mt-1 text-xs text-white/75">{{ booking.payment_type_label }} · proof for {{ formatCurrency(booking.payment_amount_due) }}</p>
                  </div>
                  <div class="text-right">
                    <strong class="text-xl">{{ formatCurrency(booking.total_amount) }}</strong>
                    <p v-if="booking.payment_balance > 0" class="mt-1 text-xs text-white/75">{{ formatCurrency(booking.payment_balance) }} balance</p>
                  </div>
                </div>
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
                  <option value="checked_in">ongoing</option>
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

            </div>
          </div>
        </div>
        <div v-else class="mcd-empty">No confirmed events matched that search.</div>
      </article>
    </section>
  </AppShell>
</template>
