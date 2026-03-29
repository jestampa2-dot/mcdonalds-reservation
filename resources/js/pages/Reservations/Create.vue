<script setup>
import axios from 'axios'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'

const props = defineProps({
  catalog: Object,
  availability: Object,
  defaults: Object,
})

const availabilityState = ref(props.availability)
const eventTypeKeys = Object.keys(props.catalog.eventTypes)
const availabilityNotice = ref('')
let availabilityTimer = null
let manilaTimer = null

const form = useForm({
  name: '',
  email: '',
  phone: '',
  event_type: eventTypeKeys[0],
  branch_code: Object.keys(props.catalog.branches)[0],
  event_date: props.defaults.event_date,
  event_time: props.defaults.event_time,
  duration_hours: props.defaults.duration_hours,
  guests: 10,
  package_code: props.catalog.packages[eventTypeKeys[0]][0].code,
  menu_bundles: ['burger-10'],
  add_ons: [],
  notes: '',
  payment_proof: null,
})

const supportedBranches = computed(() =>
  Object.values(props.catalog.branches).filter((branch) => branch.supports[form.event_type]),
)

const branch = computed(() => props.catalog.branches[form.branch_code])
const packages = computed(() => props.catalog.packages[form.event_type] ?? [])
const selectedPackage = computed(() => packages.value.find((item) => item.code === form.package_code))
const selectedBundles = computed(() => props.catalog.menuBundles.filter((item) => form.menu_bundles.includes(item.code)))
const selectedAddOns = computed(() => props.catalog.addOns.filter((item) => form.add_ons.includes(item.code)))

const branchAvailability = computed(() =>
  availabilityState.value.branches.find((item) => item.code === form.branch_code),
)

const dateCards = computed(() => branchAvailability.value?.dates ?? [])
const weekIndex = ref(0)

const selectedDateAvailability = computed(() =>
  dateCards.value.find((item) => item.date === form.event_date),
)

const formatTimeLabel = (time) => {
  const [hours, minutes] = time.split(':').map(Number)
  const meridian = hours >= 12 ? 'PM' : 'AM'
  const hour12 = hours % 12 || 12
  return `${hour12}:${String(minutes).padStart(2, '0')} ${meridian}`
}

const addHoursToTime = (time, hoursToAdd) => {
  const [hours, minutes] = time.split(':').map(Number)
  const totalMinutes = (hours * 60) + minutes + (hoursToAdd * 60)
  const nextHours = Math.floor(totalMinutes / 60) % 24
  const nextMinutes = totalMinutes % 60
  return `${String(nextHours).padStart(2, '0')}:${String(nextMinutes).padStart(2, '0')}`
}

const endTimeLabel = computed(() => formatTimeLabel(addHoursToTime(form.event_time, Number(form.duration_hours))))

const formatCurrency = (value) =>
  `\u20B1${Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`

const canStartAt = (time, dateAvailability = selectedDateAvailability.value) => {
  if (!dateAvailability) {
    return false
  }

  const duration = Number(form.duration_hours)
  const slotMap = Object.fromEntries((dateAvailability.slots ?? []).map((slot) => [slot.time, slot]))

  for (let offset = 0; offset < duration; offset += 1) {
    const slot = slotMap[addHoursToTime(time, offset)]
    if (!slot || slot.full) {
      return false
    }
  }

  return true
}

const selectableStartSlots = computed(() =>
  (selectedDateAvailability.value?.slots ?? []).map((slot) => ({
    ...slot,
    startable: canStartAt(slot.time),
    endLabel: formatTimeLabel(addHoursToTime(slot.time, Number(form.duration_hours))),
  })),
)

const dateCardsView = computed(() =>
  dateCards.value.map((item) => {
    const validStartCount = (item.slots ?? []).filter((slot) => canStartAt(slot.time, item)).length

    return {
      ...item,
      valid_start_count: validStartCount,
      computed_status: validStartCount === 0 ? 'full' : (validStartCount <= 2 ? 'limited' : 'available'),
    }
  }),
)

const selectedDateCard = computed(() =>
  dateCardsView.value.find((item) => item.date === form.event_date),
)

const weekGroups = computed(() => {
  const groups = []

  for (let index = 0; index < dateCardsView.value.length; index += 7) {
    groups.push(dateCardsView.value.slice(index, index + 7))
  }

  return groups
})

const activeWeek = computed(() => weekGroups.value[weekIndex.value] ?? [])

const activeWeekLabel = computed(() => {
  if (!activeWeek.value.length) {
    return ''
  }

  const first = new Date(`${activeWeek.value[0].date}T12:00:00`)
  const last = new Date(`${activeWeek.value[activeWeek.value.length - 1].date}T12:00:00`)

  return `${first.toLocaleDateString('en-PH', { month: 'long', day: 'numeric', year: 'numeric' })} - ${last.toLocaleDateString('en-PH', { month: 'long', day: 'numeric', year: 'numeric' })}`
})

const manilaNow = ref('')

const updateManilaNow = () => {
  manilaNow.value = new Intl.DateTimeFormat('en-PH', {
    timeZone: 'Asia/Manila',
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
    month: 'short',
    day: 'numeric',
  }).format(new Date())
}

const pricingRule = computed(() => {
  if (props.catalog.pricing.holidays.includes(form.event_date)) {
    return {
      label: 'Holiday rate',
      multiplier: props.catalog.pricing.holiday_multiplier,
    }
  }

  const day = new Date(`${form.event_date}T12:00:00`).getDay()

  if ([0, 6].includes(day)) {
    return {
      label: 'Weekend rate',
      multiplier: props.catalog.pricing.weekend_multiplier,
    }
  }

  return {
    label: 'Standard rate',
    multiplier: 1,
  }
})

const receiptPreview = computed(() => {
  const lineItems = [
    ...(selectedPackage.value ? [{ label: `${selectedPackage.value.name} (includes 4 hours)`, amount: selectedPackage.value.price }] : []),
    ...selectedBundles.value.map((item) => ({ label: item.name, amount: item.price })),
    ...selectedAddOns.value.map((item) => ({ label: item.name, amount: item.price })),
  ]
  const extensionHours = Math.max(Number(form.duration_hours) - 4, 0)

  if (extensionHours > 0) {
    lineItems.push({
      label: `Extended event time (${extensionHours} hour${extensionHours > 1 ? 's' : ''})`,
      amount: extensionHours * Number(props.catalog.pricing.extension_hourly_rate),
    })
  }

  const subtotal = lineItems.reduce((sum, item) => sum + item.amount, 0)
  const adjustment = subtotal * pricingRule.value.multiplier - subtotal
  const total = subtotal + adjustment

  return {
    lineItems,
    subtotal,
    adjustment,
    total,
  }
})

const ensureCatalogSelection = () => {
  const fallbackBranch = supportedBranches.value[0]

  if (!supportedBranches.value.some((item) => item.code === form.branch_code)) {
    form.branch_code = fallbackBranch?.code ?? form.branch_code
  }

  if (!packages.value.some((item) => item.code === form.package_code)) {
    form.package_code = packages.value[0]?.code ?? ''
  }
}

const initializeAvailabilitySelection = () => {
  ensureCatalogSelection()

  if (!dateCardsView.value.some((item) => item.date === form.event_date && item.computed_status !== 'full')) {
    form.event_date = dateCardsView.value.find((item) => item.computed_status !== 'full')?.date ?? props.defaults.event_date
  }

  if (!canStartAt(form.event_time)) {
    form.event_time = selectableStartSlots.value.find((item) => item.startable)?.time ?? props.defaults.event_time
  }

  availabilityNotice.value = ''
}

const updateAvailabilityNotice = () => {
  availabilityNotice.value = ''

  if (!selectedDateCard.value) {
    return
  }

  if (selectedDateCard.value.computed_status === 'full') {
    availabilityNotice.value = 'The chosen date is unavailable or already reserved. Please choose another morning date.'
    return
  }

  if (!canStartAt(form.event_time)) {
    availabilityNotice.value = 'The chosen date and time are unavailable or already reserved. Please choose another morning slot.'
  }
}

watch(
  () => [form.event_type, form.branch_code],
  () => {
    weekIndex.value = 0
    initializeAvailabilitySelection()
  },
)

watch(
  () => form.event_date,
  () => {
    updateAvailabilityNotice()
  },
)

watch(
  () => form.duration_hours,
  () => {
    updateAvailabilityNotice()
  },
)

watch(
  () => form.event_time,
  () => {
    updateAvailabilityNotice()
  },
)

const refreshAvailability = async () => {
  const { data } = await axios.get(route('availability.index'))
  availabilityState.value = data
  ensureCatalogSelection()
  updateAvailabilityNotice()
}

onMounted(() => {
  availabilityTimer = window.setInterval(() => {
    if (document.visibilityState !== 'visible') {
      return
    }

    refreshAvailability()
  }, 30000)
  manilaTimer = window.setInterval(updateManilaNow, 60000)
  updateManilaNow()
  initializeAvailabilitySelection()
})

onBeforeUnmount(() => {
  if (availabilityTimer) {
    window.clearInterval(availabilityTimer)
  }
  if (manilaTimer) {
    window.clearInterval(manilaTimer)
  }
})

const submit = () => {
  form.post(route('reservations.store'), {
    preserveScroll: true,
    preserveState: true,
  })
}
</script>

<template>
  <AppShell title="Book Event">
    <section class="mcd-section">
      <div class="mcd-panel p-8">
        <div class="flex flex-wrap items-end justify-between gap-4">
          <div>
            <p class="mcd-chip">Booking wizard</p>
            <h1 class="mt-4 text-4xl">Plan your party, meeting, or reserved table in one fast flow.</h1>
          </div>
          <div class="rounded-3xl bg-red-50 px-5 py-4 text-sm text-red-800">
            Morning booking window only: 7:00 AM to 12:00 PM.
          </div>
        </div>
      </div>

      <form class="mcd-grid mcd-grid--2" @submit.prevent="submit">
        <div class="space-y-5">
          <section class="mcd-panel p-6">
            <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">1. Event type</p>
            <div class="mt-5 grid gap-3">
              <label
                v-for="(type, key) in catalog.eventTypes"
                :key="key"
                class="rounded-3xl border p-4 transition"
                :class="form.event_type === key ? 'border-red-500 bg-red-50' : 'border-slate-200 bg-white'"
              >
                <input v-model="form.event_type" type="radio" :value="key" class="hidden" />
                <p class="text-xl">{{ type.label }}</p>
                <p class="mt-2 text-sm text-slate-600">{{ type.description }}</p>
              </label>
            </div>
          </section>

          <section class="mcd-panel p-6">
            <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">2. Date, time, and branch</p>

            <div class="mt-5 grid gap-4">
              <div class="mcd-grid mcd-grid--2">
                <div class="mcd-field">
                  <label>Event date</label>
                  <input v-model="form.event_date" type="date" class="mcd-input" />
                  <p v-if="form.errors.event_date" class="text-sm text-red-700">{{ form.errors.event_date }}</p>
                </div>
                <div class="mcd-field">
                  <label>Guest count</label>
                  <input v-model="form.guests" type="number" min="2" max="60" class="mcd-input" />
                </div>
              </div>

              <div class="mcd-field">
                <label>Event duration</label>
                <select v-model="form.duration_hours" class="mcd-select">
                  <option v-for="hours in [4, 5, 6, 7, 8]" :key="hours" :value="hours">
                    {{ hours }} hours
                  </option>
                </select>
                <p class="mt-2 text-xs text-slate-500">All packages include 4 hours. Extra hours up to 8 total are charged at {{ formatCurrency(catalog.pricing.extension_hourly_rate) }}/hour.</p>
                <p class="mt-1 text-xs text-slate-500">Only schedules that fit between 7:00 AM and 12:00 PM will be available.</p>
                <p v-if="form.errors.duration_hours" class="text-sm text-red-700">{{ form.errors.duration_hours }}</p>
              </div>

              <div class="mcd-field">
                <label>Branch</label>
                <select v-model="form.branch_code" class="mcd-select">
                  <option v-for="item in supportedBranches" :key="item.code" :value="item.code">
                    {{ item.name }} | {{ item.city }}
                  </option>
                </select>
                <p v-if="form.errors.branch_code" class="text-sm text-red-700">{{ form.errors.branch_code }}</p>
              </div>

              <div class="rounded-3xl bg-white p-5">
                <div class="h-2 rounded-full bg-slate-100">
                  <div class="h-2 w-2/3 rounded-full bg-gradient-to-r from-emerald-400 via-teal-300 to-slate-200"></div>
                </div>

                <div class="mt-6 flex flex-wrap items-start justify-between gap-4">
                  <div>
                    <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">Select date and time</p>
                    <p class="mt-2 text-sm text-slate-500">Pick a Philippine-time booking window using the weekly calendar below.</p>
                  </div>
                  <div class="min-w-[220px] rounded-3xl border border-slate-200 bg-white px-4 py-3 shadow-sm">
                    <p class="text-xs font-black uppercase tracking-[0.2em] text-slate-400">Timezone</p>
                    <p class="mt-2 text-base font-bold text-slate-700">Philippines - {{ manilaNow }}</p>
                  </div>
                </div>

                <div class="mt-6 flex items-center justify-between gap-3">
                  <button type="button" class="mcd-button mcd-button--ghost px-4 py-2" :disabled="weekIndex === 0" @click="weekIndex = Math.max(weekIndex - 1, 0)">
                    &lt;
                  </button>
                  <p class="text-center text-lg font-black">{{ activeWeekLabel }}</p>
                  <button
                    type="button"
                    class="mcd-button mcd-button--ghost px-4 py-2"
                    :disabled="weekIndex >= weekGroups.length - 1"
                    @click="weekIndex = Math.min(weekIndex + 1, weekGroups.length - 1)"
                  >
                    &gt;
                  </button>
                </div>

                <div class="mt-5 grid grid-cols-7 gap-2 text-center text-xs font-black uppercase tracking-[0.14em] text-slate-400">
                  <span v-for="item in activeWeek" :key="`${item.date}-label`">
                    {{ new Date(`${item.date}T12:00:00`).toLocaleDateString('en-PH', { weekday: 'short' }) }}
                  </span>
                </div>

                <div class="mt-3 grid grid-cols-7 gap-2">
                  <button
                    v-for="item in activeWeek"
                    :key="item.date"
                    type="button"
                    class="rounded-3xl border px-3 py-4 text-center transition"
                    :class="item.computed_status === 'full'
                      ? 'border-slate-200 bg-slate-100 text-slate-400'
                      : form.event_date === item.date
                        ? 'border-emerald-400 bg-emerald-50 text-emerald-700'
                        : 'border-slate-200 bg-white text-slate-700'"
                    :disabled="item.computed_status === 'full'"
                    @click="form.event_date = item.date"
                  >
                    <p class="text-2xl font-bold">{{ new Date(`${item.date}T12:00:00`).getDate() }}</p>
                    <p class="mt-1 text-[11px] capitalize">{{ item.computed_status }}</p>
                  </button>
                </div>

                <div class="mt-6 rounded-3xl bg-emerald-50/60 p-5">
                  <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                      <p class="text-sm font-black uppercase tracking-[0.2em] text-emerald-700">Available booking times</p>
                      <p class="mt-1 text-sm text-slate-500">
                        {{ selectedDateCard ? `${selectedDateCard.valid_start_count} start windows open on ${selectedDateCard.date}` : 'Select a date to view booking times.' }}
                      </p>
                    </div>
                    <button type="button" class="mcd-button mcd-button--ghost" @click="refreshAvailability">Refresh now</button>
                  </div>

                  <div v-if="availabilityNotice" class="mt-4 rounded-2xl bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                    {{ availabilityNotice }}
                  </div>

                  <div class="mt-5 grid gap-3 md:grid-cols-2">
                  <button
                    v-for="slot in selectableStartSlots"
                    :key="slot.time"
                    type="button"
                    class="rounded-3xl border px-4 py-4 text-left text-sm font-bold shadow-sm transition"
                    :class="!slot.startable
                      ? 'cursor-not-allowed border-slate-200 bg-slate-100 text-slate-400'
                      : form.event_time === slot.time
                        ? 'border-emerald-400 bg-white text-emerald-700 ring-2 ring-emerald-200'
                        : 'border-slate-200 bg-white text-slate-700'"
                    :disabled="!slot.startable"
                    @click="form.event_time = slot.time"
                  >
                    <p class="text-lg">{{ slot.label }} - {{ slot.endLabel }}</p>
                    <p class="mt-2 text-xs font-semibold" :class="slot.startable ? 'text-slate-500' : 'text-slate-400'">
                      {{ slot.startable ? `Available (${slot.remaining} branch slot${slot.remaining !== 1 ? 's' : ''} left at start)` : 'Unavailable for selected duration' }}
                    </p>
                  </button>
                </div>
                  <p v-if="form.errors.event_time" class="mt-3 text-sm text-red-700">{{ form.errors.event_time }}</p>
                  <p class="mt-3 text-xs text-slate-500">Choose a start time, and the system automatically calculates the end time based on your selected duration.</p>
                </div>
              </div>

              <div v-if="branch" class="rounded-3xl bg-white p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <h3 class="text-xl">{{ branch.name }}</h3>
                    <p class="mt-1 text-sm text-slate-500">{{ branch.city }} | Up to {{ branch.max_guests }} guests</p>
                  </div>
                  <a :href="branch.map_url" target="_blank" rel="noreferrer" class="text-sm font-bold text-red-700">Open map</a>
                </div>
              </div>
            </div>
          </section>

          <section class="mcd-panel p-6">
            <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">3. Your details</p>
            <div class="mt-5 grid gap-4">
              <div class="mcd-field">
                <label>Full name</label>
                <input v-model="form.name" type="text" class="mcd-input" />
                <p v-if="form.errors.name" class="text-sm text-red-700">{{ form.errors.name }}</p>
              </div>
              <div class="mcd-grid mcd-grid--2">
                <div class="mcd-field">
                  <label>Email</label>
                  <input v-model="form.email" type="email" class="mcd-input" />
                  <p v-if="form.errors.email" class="text-sm text-red-700">{{ form.errors.email }}</p>
                </div>
                <div class="mcd-field">
                  <label>Phone</label>
                  <input v-model="form.phone" type="text" class="mcd-input" />
                  <p v-if="form.errors.phone" class="text-sm text-red-700">{{ form.errors.phone }}</p>
                </div>
              </div>
              <div class="mcd-field">
                <label>Special notes</label>
                <textarea v-model="form.notes" rows="4" class="mcd-textarea"></textarea>
              </div>
            </div>
          </section>
        </div>

        <div class="space-y-5">
          <section class="mcd-panel p-6">
            <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">4. Package and customization</p>
            <div class="mt-5 grid gap-3">
              <label
                v-for="item in packages"
                :key="item.code"
                class="rounded-3xl border p-4 transition"
                :class="form.package_code === item.code ? 'border-red-500 bg-red-50' : 'border-slate-200 bg-white'"
              >
                <input v-model="form.package_code" type="radio" :value="item.code" class="hidden" />
                <div class="flex items-start justify-between gap-4">
                  <div>
                    <p class="text-lg">{{ item.name }}</p>
                    <p class="mt-1 text-sm text-slate-500">{{ item.guest_range }}</p>
                    <p class="mt-1 text-xs font-bold uppercase tracking-[0.12em] text-red-700">Includes 4 hours | extendable up to 8 hours</p>
                  </div>
                  <strong class="text-red-700">{{ formatCurrency(item.price) }}</strong>
                </div>
                <ul class="mt-3 space-y-1 text-sm text-slate-600">
                  <li v-for="feature in item.features" :key="feature">{{ feature }}</li>
                </ul>
              </label>
            </div>
          </section>

          <section class="mcd-panel p-6">
            <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">Menu bundles</p>
            <div class="mt-5 grid gap-3">
              <label
                v-for="item in catalog.menuBundles"
                :key="item.code"
                class="flex items-center justify-between rounded-3xl border border-slate-200 bg-white p-4"
              >
                <div>
                  <p class="text-lg">{{ item.name }}</p>
                  <p class="mt-1 text-sm text-slate-500">{{ item.prep_label }}</p>
                </div>
                <div class="flex items-center gap-3">
                  <span class="font-bold text-red-700">{{ formatCurrency(item.price) }}</span>
                  <input v-model="form.menu_bundles" :value="item.code" type="checkbox" class="h-5 w-5 rounded border-slate-300 text-red-600" />
                </div>
              </label>
            </div>
          </section>

          <section class="mcd-panel p-6">
            <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">Add-ons and payment</p>
            <div class="mt-5 grid gap-3">
              <label
                v-for="item in catalog.addOns"
                :key="item.code"
                class="flex items-center justify-between rounded-3xl border border-slate-200 bg-white p-4"
              >
                <div>
                  <p class="text-lg">{{ item.name }}</p>
                </div>
                <div class="flex items-center gap-3">
                  <span class="font-bold text-red-700">{{ formatCurrency(item.price) }}</span>
                  <input v-model="form.add_ons" :value="item.code" type="checkbox" class="h-5 w-5 rounded border-slate-300 text-red-600" />
                </div>
              </label>

              <div class="mcd-field mt-2">
                <label>Upload proof of payment</label>
                <input type="file" accept="image/*" class="mcd-input" @input="form.payment_proof = $event.target.files[0]" />
                <p v-if="form.errors.payment_proof" class="text-sm text-red-700">{{ form.errors.payment_proof }}</p>
              </div>
            </div>
          </section>

          <section class="mcd-panel mcd-panel--dark p-6">
            <p class="text-sm font-black uppercase tracking-[0.2em] text-amber-300">Booking receipt</p>
            <div class="mt-5 space-y-3 text-sm text-white/80">
              <div class="flex items-center justify-between">
                <span>Event type</span>
                <strong class="text-white">{{ catalog.eventTypes[form.event_type].label }}</strong>
              </div>
              <div class="flex items-center justify-between">
                <span>Branch</span>
                <strong class="text-white">{{ branch?.name }}</strong>
              </div>
              <div class="flex items-center justify-between">
                <span>Event time</span>
                <strong class="text-white">{{ formatTimeLabel(form.event_time) }} to {{ endTimeLabel }}</strong>
              </div>
              <div class="flex items-center justify-between">
                <span>Duration</span>
                <strong class="text-white">{{ form.duration_hours }} hours</strong>
              </div>
              <div class="mt-4 rounded-3xl bg-white/5 p-4">
                <p class="text-sm uppercase tracking-[0.2em] text-amber-200">Receipt preview</p>
                <div class="mt-4 space-y-3">
                  <div v-for="item in receiptPreview.lineItems" :key="item.label" class="flex items-center justify-between gap-4">
                    <span>{{ item.label }}</span>
                    <strong class="text-white">{{ formatCurrency(item.amount) }}</strong>
                  </div>
                </div>
                <div class="mt-4 space-y-2 border-t border-white/10 pt-4">
                  <div class="flex items-center justify-between">
                    <span>Subtotal</span>
                    <strong class="text-white">{{ formatCurrency(receiptPreview.subtotal) }}</strong>
                  </div>
                  <div class="flex items-center justify-between">
                    <span>{{ pricingRule.label }} ({{ pricingRule.multiplier }}x)</span>
                    <strong class="text-white">{{ formatCurrency(receiptPreview.adjustment) }}</strong>
                  </div>
                </div>
              </div>
              <div class="mt-4 border-t border-white/10 pt-4">
                <p class="text-sm uppercase tracking-[0.2em] text-amber-200">Total before confirmation</p>
                <p class="mt-2 text-4xl text-white">{{ formatCurrency(receiptPreview.total) }}</p>
                <p class="mt-2 text-xs text-white/65">This receipt updates in real time as dates and slots become unavailable.</p>
              </div>
              <button type="submit" class="mcd-button mt-6 w-full" :disabled="form.processing || !selectedDateCard || selectedDateCard.computed_status === 'full' || !canStartAt(form.event_time)">
                {{ form.processing ? 'Submitting...' : 'Confirm reservation' }}
              </button>
            </div>
          </section>
        </div>
      </form>
    </section>
  </AppShell>
</template>
