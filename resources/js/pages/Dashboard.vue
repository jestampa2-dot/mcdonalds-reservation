<script setup>
import { reactive, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import StatusBadge from '@/Components/StatusBadge.vue'

const props = defineProps({
  bookings: Array,
  stats: Array,
  slotOptions: Array,
})

const timeOptions = props.slotOptions.map((value) => {
  const [hours, minutes] = value.split(':').map(Number)
  const meridian = hours >= 12 ? 'PM' : 'AM'
  const hour12 = hours % 12 || 12

  return {
    value,
    label: `${hour12}:${String(minutes).padStart(2, '0')} ${meridian}`,
  }
})

const rescheduleState = reactive(
  Object.fromEntries(
    props.bookings.map((booking) => [
      booking.id,
      { event_date: booking.event_date, event_time: booking.event_start_time },
    ]),
  ),
)

watch(
  () => props.bookings,
  (bookings) => {
    bookings.forEach((booking) => {
      rescheduleState[booking.id] = {
        event_date: booking.event_date,
        event_time: booking.event_start_time,
      }
    })
  },
)

const cancelBooking = (id) => {
  router.post(route('reservations.cancel', id), {}, { preserveScroll: true, preserveState: true })
}

const rescheduleBooking = (id) => {
  router.post(route('reservations.reschedule', id), rescheduleState[id], { preserveScroll: true, preserveState: true })
}

const formatCurrency = (value) =>
  `\u20B1${Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`

const refreshDashboard = () => {
  router.reload({
    only: ['bookings', 'stats'],
    preserveScroll: true,
    preserveState: true,
  })
}
</script>

<template>
  <AppShell title="My Dashboard">
    <section class="mcd-section">
      <div class="mcd-panel p-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
          <div>
            <p class="mcd-chip">Customer dashboard</p>
            <h1 class="mt-4 text-4xl">Track your upcoming bookings, payment review, and check-in pass.</h1>
          </div>
          <div class="flex flex-wrap gap-3">
            <button type="button" class="mcd-button mcd-button--ghost" @click="refreshDashboard">Refresh dashboard</button>
            <Link :href="route('reservations.create')" prefetch class="mcd-button">Create another booking</Link>
          </div>
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
      <div>
        <p class="mcd-chip">Upcoming bookings</p>
        <h2 class="mt-3 text-3xl">Everything you need before arrival</h2>
      </div>

      <div v-if="bookings.length" class="space-y-5">
        <article v-for="booking in bookings" :key="booking.id" class="mcd-panel p-6">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
              <div class="flex flex-wrap items-center gap-3">
                <h3 class="text-2xl">{{ booking.package_name }}</h3>
                <StatusBadge :value="booking.status" />
                <StatusBadge :value="booking.service_status" />
              </div>
              <p class="mt-2 text-sm text-slate-500">
                {{ booking.booking_reference }} | {{ booking.branch }} | {{ booking.event_date }} at {{ booking.event_time }}
              </p>
            </div>
            <div class="text-right">
              <p class="text-sm uppercase tracking-[0.25em] text-slate-500">Total</p>
              <p class="mt-2 text-3xl text-red-700">{{ formatCurrency(booking.total_amount) }}</p>
            </div>
          </div>

          <div class="mt-5 grid gap-4 md:grid-cols-[1.4fr,1fr]">
            <div class="rounded-3xl bg-white/80 p-5">
              <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">Reservation details</p>
              <p class="mt-3 text-sm leading-6 text-slate-600">Guest count: {{ booking.guests }}</p>
              <p class="mt-1 text-sm leading-6 text-slate-600">Check-in code: {{ booking.check_in_code }}</p>
              <p v-if="booking.notes" class="mt-1 text-sm leading-6 text-slate-600">Notes: {{ booking.notes }}</p>

              <div class="mt-5 flex flex-wrap gap-3">
                <a :href="booking.pass_url" class="mcd-button">Download QR Pass</a>
                <a :href="booking.payment_proof_url" class="mcd-button mcd-button--ghost">Payment Proof</a>
              </div>
            </div>

            <div class="rounded-3xl bg-amber-50 p-5">
              <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">Reschedule</p>
              <p class="mt-2 text-xs text-slate-500">Choose a new reservation time between 7:00 AM and 11:00 PM.</p>
              <div class="mt-4 grid gap-3">
                <input v-model="rescheduleState[booking.id].event_date" type="date" class="mcd-input" />
                <select v-model="rescheduleState[booking.id].event_time" class="mcd-select">
                  <option v-for="option in timeOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
                <button type="button" class="mcd-button" @click="rescheduleBooking(booking.id)">Save new slot</button>
                <button type="button" class="mcd-button mcd-button--ghost" @click="cancelBooking(booking.id)">Cancel booking</button>
              </div>
            </div>
          </div>
        </article>
      </div>

      <div v-else class="mcd-empty">
        No bookings yet. Start with a birthday bash, business huddle, or quick table reservation.
      </div>
    </section>
  </AppShell>
</template>
