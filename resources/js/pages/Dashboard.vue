<script setup>
import { reactive, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import StatusBadge from '@/Components/StatusBadge.vue'

const props = defineProps({
  bookings: Array,
  receivedInvitations: Array,
  stats: Array,
  slotOptions: Array,
})

const emptyInvitationForm = () => ({ name: '', phone: '', email: '' })
const invitationForms = reactive(Object.fromEntries(props.bookings.map((booking) => [booking.id, emptyInvitationForm()])))
const invitationOpen = reactive(Object.fromEntries(props.bookings.map((booking) => [booking.id, false])))
const invitationBusy = reactive({})
const invitationErrors = reactive({})
const invitationNotices = reactive({})
const invitationFallbacks = reactive({})
const responseBusy = reactive({})

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
      if (!invitationForms[booking.id]) invitationForms[booking.id] = emptyInvitationForm()
      if (invitationOpen[booking.id] === undefined) invitationOpen[booking.id] = false
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
    only: ['bookings', 'receivedInvitations', 'stats'],
    preserveScroll: true,
    preserveState: true,
  })
}

const invitationErrorMessage = (error) => {
  const errors = error?.response?.data?.errors
  const firstValidationMessage = errors ? Object.values(errors).flat()[0] : null

  return firstValidationMessage || error?.response?.data?.message || 'Unable to update this invitation right now.'
}

const sendInvitation = async (booking) => {
  invitationBusy[booking.id] = true
  invitationErrors[booking.id] = ''
  invitationNotices[booking.id] = ''
  invitationFallbacks[booking.id] = null

  try {
    const response = await window.axios.post(route('reservations.invitations.store', booking.id), invitationForms[booking.id])
    const invitation = response.data.invitation
    invitationForms[booking.id] = emptyInvitationForm()
    invitationNotices[booking.id] = response.data.message

    if (!response.data.sms_queued) {
      invitationFallbacks[booking.id] = {
        phone: invitation.invitee_phone,
        message: `You're invited to ${invitation.package_name || 'a McDonald\'s event'} at ${invitation.branch || 'McDonald\'s'} on ${invitation.event_date || 'the scheduled date'} at ${invitation.event_time_label || 'the scheduled time'}. Sign in to McD Reservations to reply. Ref ${invitation.booking_reference || ''}`,
      }
    }

    router.reload({ only: ['bookings', 'receivedInvitations'], preserveScroll: true, preserveState: true })
  } catch (error) {
    invitationErrors[booking.id] = invitationErrorMessage(error)
  } finally {
    invitationBusy[booking.id] = false
  }
}

const cancelInvitation = async (bookingId, invitationId) => {
  invitationBusy[bookingId] = true
  invitationErrors[bookingId] = ''

  try {
    const response = await window.axios.delete(route('reservations.invitations.destroy', [bookingId, invitationId]))
    invitationNotices[bookingId] = response.data.message
    router.reload({ only: ['bookings'], preserveScroll: true, preserveState: true })
  } catch (error) {
    invitationErrors[bookingId] = invitationErrorMessage(error)
  } finally {
    invitationBusy[bookingId] = false
  }
}

const respondToInvitation = async (invitationId, status) => {
  responseBusy[invitationId] = true

  try {
    await window.axios.post(route('invitations.respond', invitationId), { status })
    router.reload({ only: ['receivedInvitations'], preserveScroll: true, preserveState: true })
  } catch (error) {
    window.alert(invitationErrorMessage(error))
  } finally {
    responseBusy[invitationId] = false
  }
}

const fallbackSmsHref = (fallback) =>
  fallback ? `sms:${fallback.phone}?body=${encodeURIComponent(fallback.message)}` : '#'

const copyFallbackInvitation = async (bookingId) => {
  const fallback = invitationFallbacks[bookingId]
  if (!fallback) return

  try {
    await navigator.clipboard.writeText(`${fallback.phone}\n${fallback.message}`)
    invitationNotices[bookingId] = 'Invitation text copied. Send it using your preferred messaging app.'
  } catch {
    invitationErrors[bookingId] = 'Copying was blocked by the browser. Select the message and copy it manually.'
  }
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
      <div v-if="receivedInvitations.length" class="mcd-panel p-6">
        <p class="mcd-chip">Guest invitations</p>
        <h2 class="mt-3 text-3xl">Invitations for you</h2>
        <div class="mt-5 grid gap-4">
          <article v-for="invitation in receivedInvitations" :key="invitation.id" class="rounded-3xl bg-rose-50 p-5">
            <div class="flex flex-wrap items-start justify-between gap-4">
              <div>
                <p class="text-lg font-black text-slate-900">{{ invitation.inviter_name || 'A customer' }} invited you</p>
                <p class="mt-1 text-sm text-slate-600">{{ invitation.package_name }} | {{ invitation.branch }}</p>
                <p class="mt-1 text-sm text-slate-600">{{ invitation.event_date }} at {{ invitation.event_time_label }}</p>
                <p class="mt-1 text-xs font-bold text-red-700">{{ invitation.booking_reference }}</p>
              </div>
              <StatusBadge :value="invitation.status" />
            </div>
            <div v-if="invitation.status === 'pending'" class="mt-4 flex flex-wrap gap-3">
              <button type="button" class="mcd-button" :disabled="responseBusy[invitation.id]" @click="respondToInvitation(invitation.id, 'accepted')">Accept invitation</button>
              <button type="button" class="mcd-button mcd-button--ghost" :disabled="responseBusy[invitation.id]" @click="respondToInvitation(invitation.id, 'declined')">Decline</button>
            </div>
          </article>
        </div>
      </div>

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

              <section class="guest-invitations">
                <div class="flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">Guest invitations</p>
                    <p class="mt-1 text-sm text-slate-600">Invite a customer by SMS and let them RSVP from the web or mobile app.</p>
                  </div>
                  <button type="button" class="mcd-button mcd-button--ghost" @click="invitationOpen[booking.id] = !invitationOpen[booking.id]">
                    {{ invitationOpen[booking.id] ? 'Close invitation form' : 'Invite guest' }}
                  </button>
                </div>

                <form v-if="invitationOpen[booking.id]" class="mt-4 grid gap-3 md:grid-cols-2" @submit.prevent="sendInvitation(booking)">
                  <label class="grid gap-2 text-sm font-bold text-slate-700">
                    Guest name
                    <input v-model="invitationForms[booking.id].name" required maxlength="100" class="mcd-input" autocomplete="name" />
                  </label>
                  <label class="grid gap-2 text-sm font-bold text-slate-700">
                    Philippine mobile number
                    <input v-model="invitationForms[booking.id].phone" required maxlength="30" class="mcd-input" inputmode="tel" autocomplete="tel" placeholder="0917 123 4567" />
                  </label>
                  <label class="grid gap-2 text-sm font-bold text-slate-700 md:col-span-2">
                    Email address (optional)
                    <input v-model="invitationForms[booking.id].email" type="email" maxlength="191" class="mcd-input" autocomplete="email" />
                  </label>
                  <div class="md:col-span-2">
                    <button type="submit" class="mcd-button" :disabled="invitationBusy[booking.id]">
                      {{ invitationBusy[booking.id] ? 'Sending invitation...' : 'Send invitation' }}
                    </button>
                  </div>
                </form>

                <p v-if="invitationNotices[booking.id]" class="mt-3 rounded-2xl bg-emerald-50 p-3 text-sm font-bold text-emerald-800">{{ invitationNotices[booking.id] }}</p>
                <p v-if="invitationErrors[booking.id]" class="mt-3 rounded-2xl bg-red-50 p-3 text-sm font-bold text-red-800">{{ invitationErrors[booking.id] }}</p>

                <div v-if="invitationFallbacks[booking.id]" class="mt-3 rounded-2xl bg-amber-100 p-4">
                  <p class="text-sm font-bold text-amber-950">Automatic SMS is unavailable. The invitation is saved in the app, and you can send this prepared text manually.</p>
                  <p class="mt-2 break-words text-sm text-amber-900">{{ invitationFallbacks[booking.id].message }}</p>
                  <div class="mt-3 flex flex-wrap gap-3">
                    <a :href="fallbackSmsHref(invitationFallbacks[booking.id])" class="mcd-button mcd-button--ghost">Open SMS app</a>
                    <button type="button" class="mcd-button mcd-button--ghost" @click="copyFallbackInvitation(booking.id)">Copy message</button>
                  </div>
                </div>

                <div v-if="booking.invitations?.length" class="mt-4 grid gap-3">
                  <article v-for="invitation in booking.invitations" :key="invitation.id" class="flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-slate-50 p-4">
                    <div>
                      <p class="font-black text-slate-900">{{ invitation.invitee_name }}</p>
                      <p class="text-sm text-slate-600">{{ invitation.invitee_phone }}<span v-if="invitation.invitee_email"> | {{ invitation.invitee_email }}</span></p>
                    </div>
                    <div class="flex items-center gap-3">
                      <StatusBadge :value="invitation.status" />
                      <button v-if="invitation.status === 'pending'" type="button" class="text-sm font-black text-red-700" :disabled="invitationBusy[booking.id]" @click="cancelInvitation(booking.id, invitation.id)">Cancel</button>
                    </div>
                  </article>
                </div>
              </section>
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

.guest-invitations {
  margin-top: 1.5rem;
  padding-top: 1.5rem;
  border-top: 1px solid rgba(36, 23, 20, 0.1);
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
