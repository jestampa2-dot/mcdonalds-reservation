<script setup>
import { reactive, ref, watch } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import QrCameraScanner from '@/Components/QrCameraScanner.vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import EventHistoryPanel from '@/features/timeline/components/EventHistoryPanel.vue'

const props = defineProps({
  prepList: Array,
  todayBookings: Array,
  notifications: Array,
  history: Array,
  statusOptions: Array,
  paymentBranches: Array,
})

const checkInForm = useForm({
  code: '',
})

const statusState = reactive(Object.fromEntries(props.todayBookings.map((booking) => [booking.id, booking.service_status])))
const notificationItems = ref(props.notifications)
const historyItems = ref(props.history)
const cameraScannerOpen = ref(false)
const paymentEdits = reactive({})
const paymentSaving = reactive({})

const checkIn = () => {
  checkInForm.code = checkInForm.code.trim()

  if (!checkInForm.code) {
    checkInForm.setError('code', 'Enter a booking code or scan the customer QR pass.')
    return
  }

  checkInForm.clearErrors()
  checkInForm.post(route('staff.check-in'), {
    preserveScroll: true,
    preserveState: true,
    onSuccess: (page) => {
      if (!page.props.flash?.error) {
        checkInForm.reset()
      }
    },
  })
}

const openCameraScanner = () => {
  checkInForm.clearErrors()
  cameraScannerOpen.value = true
}

const closeCameraScanner = () => {
  cameraScannerOpen.value = false
}

const checkInFromCamera = (code) => {
  cameraScannerOpen.value = false
  checkInForm.code = code
  checkIn()
}

const updateStatus = (id) => {
  router.post(route('staff.reservations.service-status', id), {
    service_status: statusState[id],
  }, {
    preserveScroll: true,
    preserveState: true,
  })
}

const selectPaymentQr = (id, event) => {
  paymentEdits[id].payment_qr = event.target.files?.[0] ?? null
  paymentEdits[id].remove_payment_qr = false
}

const updatePaymentDetails = (id) => {
  const data = new FormData()
  data.append('payment_number', paymentEdits[id].payment_number ?? '')
  data.append('remove_payment_qr', paymentEdits[id].remove_payment_qr ? '1' : '0')

  if (paymentEdits[id].payment_qr) {
    data.append('payment_qr', paymentEdits[id].payment_qr)
  }

  paymentSaving[id] = true
  router.post(route('staff.branches.payment-details', id), data, {
    forceFormData: true,
    preserveScroll: true,
    preserveState: true,
    onFinish: () => {
      paymentSaving[id] = false
    },
  })
}

watch(
  () => props.paymentBranches,
  (branches) => {
    Object.keys(paymentEdits).forEach((key) => delete paymentEdits[key])

    branches.forEach((branch) => {
      paymentEdits[branch.id] = {
        payment_number: branch.payment_number ?? '',
        payment_qr: null,
        remove_payment_qr: false,
      }
    })
  },
  { immediate: true },
)

watch(
  () => props.todayBookings,
  (bookings) => {
    bookings.forEach((booking) => {
      statusState[booking.id] = booking.service_status
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
    only: ['prepList', 'todayBookings', 'notifications', 'history', 'paymentBranches'],
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
          <button type="button" class="mcd-button mt-5 w-full" :disabled="checkInForm.processing" @click="openCameraScanner">
            Scan customer QR
          </button>
          <div class="my-4 flex items-center gap-3 text-xs font-black uppercase text-white/50">
            <span class="h-px flex-1 bg-white/20" />
            <span>or enter code</span>
            <span class="h-px flex-1 bg-white/20" />
          </div>
          <form class="flex flex-col gap-3" @submit.prevent="checkIn">
            <input v-model="checkInForm.code" type="text" class="mcd-input" autocomplete="off" placeholder="Booking reference or check-in code" />
            <p v-if="checkInForm.errors.code" class="text-sm font-bold text-red-300">{{ checkInForm.errors.code }}</p>
            <button type="submit" class="mcd-button mcd-button--ghost" :disabled="checkInForm.processing">
              {{ checkInForm.processing ? 'Checking in...' : 'Check in guest' }}
            </button>
          </form>
          <p class="mt-3 text-sm text-white/70">Only admin-confirmed bookings can start.</p>
        </article>
      </div>
    </section>

    <section v-if="paymentBranches.length" class="mcd-section">
      <div class="mcd-panel p-6">
        <p class="mcd-chip">Branch payments</p>
        <h2 class="mt-4 text-3xl">Payment QR and account number</h2>
        <p class="mt-2 text-sm text-slate-600">Only the branches assigned to your account are shown. Customers see these details when paying for that branch.</p>
        <div class="mt-6 grid gap-5 lg:grid-cols-2">
          <article v-for="branch in paymentBranches" :key="branch.id" class="rounded-3xl border border-amber-200 bg-amber-50 p-5">
            <p class="text-xl font-black text-slate-900">{{ branch.name }}</p>
            <p class="mt-1 text-sm font-bold uppercase tracking-[0.15em] text-red-700">{{ branch.code }}</p>
            <div class="mt-4 grid gap-4 sm:grid-cols-[160px,1fr]">
              <div class="rounded-2xl bg-white p-3 text-center">
                <img
                  v-if="branch.payment_qr_url && !paymentEdits[branch.id].remove_payment_qr"
                  :src="branch.payment_qr_url"
                  :alt="`${branch.name} payment QR code`"
                  class="mx-auto aspect-square w-full rounded-xl object-contain"
                />
                <p v-else class="grid aspect-square place-items-center text-sm font-bold text-slate-400">No QR uploaded</p>
              </div>
              <div class="grid gap-3">
                <label class="mcd-field">
                  <span>Payment account or mobile number</span>
                  <input v-model="paymentEdits[branch.id].payment_number" type="text" class="mcd-input" maxlength="100" placeholder="Example: GCash 0917 123 4567" />
                </label>
                <label class="mcd-field">
                  <span>Upload payment QR</span>
                  <input type="file" accept="image/*" class="mcd-input" @change="selectPaymentQr(branch.id, $event)" />
                </label>
                <label v-if="branch.payment_qr_url" class="flex items-center gap-2 text-sm font-semibold text-slate-600">
                  <input v-model="paymentEdits[branch.id].remove_payment_qr" type="checkbox" />
                  <span>Remove current QR</span>
                </label>
                <button type="button" class="mcd-button" :disabled="paymentSaving[branch.id]" @click="updatePaymentDetails(branch.id)">
                  {{ paymentSaving[branch.id] ? 'Saving...' : 'Save payment details' }}
                </button>
              </div>
            </div>
          </article>
        </div>
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
            <div class="grid gap-4 xl:grid-cols-[1.3fr,0.7fr]">
              <div>
                <div class="flex flex-wrap items-center gap-2">
                  <strong>{{ booking.booking_reference }}</strong>
                  <StatusBadge :value="booking.status" />
                  <StatusBadge :value="booking.service_status" />
                </div>
                <p class="mt-2 text-sm text-slate-600">{{ booking.package_name }} | {{ booking.branch }}</p>
                <p class="mt-1 text-sm text-slate-500">Venue: {{ booking.venue_name || booking.branch }}<span v-if="booking.venue_full_address"> | {{ booking.venue_full_address }}</span></p>
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

            </div>
          </div>
        </div>
        <div v-else class="mcd-empty mt-5">No active events.</div>
      </article>
    </section>

    <QrCameraScanner
      :show="cameraScannerOpen"
      @close="closeCameraScanner"
      @decoded="checkInFromCamera"
    />
  </AppShell>
</template>
