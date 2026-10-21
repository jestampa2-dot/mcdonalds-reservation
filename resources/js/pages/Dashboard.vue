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
            <p class="mcd-chip">Dashboard</p>
            <h1 class="mt-4 text-4xl">Your bookings</h1>
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
        <p class="mcd-chip">Bookings</p>
        <h2 class="mt-3 text-3xl">Upcoming</h2>
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

              <section v-if="booking.can_show_qr_pass" class="customer-event-pass">
                <div class="customer-event-pass__image-wrap">
                  <img
                    :src="booking.pass_preview_url"
                    :alt="`Scannable event pass for ${booking.booking_reference}`"
                    class="customer-event-pass__image"
                    loading="lazy"
                  />
                </div>
                <div class="customer-event-pass__copy">
                  <span class="customer-event-pass__status" :class="{ 'is-ongoing': booking.status === 'checked_in' }">
                    {{ booking.status === 'checked_in' ? 'Event ongoing' : 'Ready to scan' }}
                  </span>
                  <h4>Event start QR pass</h4>
                  <p>
                    Present this pass when the party is ready to start. A successful scan automatically marks the event as ongoing for admin and service staff.
                  </p>
                  <div class="customer-event-pass__actions">
                    <a :href="booking.pass_url" class="mcd-button">Download QR pass</a>
                    <a :href="booking.qr_check_in_url" class="mcd-button mcd-button--ghost">Start on this device</a>
                  </div>
                </div>
              </section>

              <div v-else-if="booking.status === 'pending_review'" class="customer-event-pass__pending">
                The event QR pass will appear here after the booking is confirmed.
              </div>

              <div v-if="booking.payment_proof_preview_url" class="mt-5 rounded-2xl bg-white p-3 shadow-sm">
                <div class="flex items-center justify-between gap-3">
                  <p class="text-xs font-black uppercase tracking-[0.18em] text-red-700">Proof of payment</p>
                  <a :href="booking.payment_proof_url" class="text-xs font-bold text-red-700">Download</a>
                </div>
                <a :href="booking.payment_proof_preview_url" target="_blank" rel="noreferrer">
                  <img
                    :src="booking.payment_proof_preview_url"
                    :alt="`Payment proof ${booking.booking_reference}`"
                    class="mt-3 h-52 w-full rounded-2xl object-cover"
                    loading="lazy"
                  />
                </a>
              </div>

              <div class="mt-5 flex flex-wrap gap-3">
                <a :href="booking.payment_proof_preview_url || booking.payment_proof_url" target="_blank" rel="noreferrer" class="mcd-button mcd-button--ghost">View Payment Proof</a>
              </div>
            </div>

            <div class="rounded-3xl bg-amber-50 p-5">
              <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">Reschedule</p>
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
        No bookings yet.
      </div>
    </section>
  </AppShell>
</template>

<style scoped>
.customer-event-pass {
  display: grid;
  grid-template-columns: minmax(12rem, 15rem) minmax(0, 1fr);
  align-items: center;
  gap: 1.25rem;
  margin-top: 1.5rem;
  padding-top: 1.5rem;
  border-top: 1px solid rgba(36, 23, 20, 0.1);
}

.customer-event-pass__image-wrap {
  min-width: 0;
  overflow: hidden;
  border: 1px solid #e2e8f0;
  border-radius: 0.75rem;
  background: #ffffff;
}

.customer-event-pass__image {
  display: block;
  width: 100%;
  aspect-ratio: 1;
  object-fit: contain;
}

.customer-event-pass__copy {
  display: grid;
  min-width: 0;
  justify-items: start;
  gap: 0.55rem;
}

.customer-event-pass__copy h4 {
  color: #241714;
  font-size: 1.15rem;
}

.customer-event-pass__copy p,
.customer-event-pass__pending {
  color: #64748b;
  font-size: 0.82rem;
  line-height: 1.6;
}

.customer-event-pass__status {
  padding: 0.3rem 0.55rem;
  border-radius: 999px;
  background: #fff4c7;
  color: #854d0e;
  font-size: 0.65rem;
  font-weight: 900;
  text-transform: uppercase;
}

.customer-event-pass__status.is-ongoing {
  background: #e9f8ee;
  color: #12642f;
}

.customer-event-pass__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.65rem;
}

.customer-event-pass__pending {
  margin-top: 1.25rem;
  padding: 0.85rem 1rem;
  border-left: 4px solid #f2b900;
  background: #fff9e6;
}

@media (max-width: 640px) {
  .customer-event-pass {
    grid-template-columns: minmax(0, 1fr);
  }
}
</style>
