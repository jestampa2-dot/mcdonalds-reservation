<script setup>
import axios from 'axios'
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import ManualMenuBoard from '@/Components/ManualMenuBoard.vue'

const props = defineProps({
  catalog: Object,
  roomChoices: Array,
  availability: Object,
  defaults: Object,
})

const availabilityState = ref(props.availability)
const eventTypeKeys = Object.keys(props.catalog.eventTypes)
const availabilityNotice = ref('')
let availabilityTimer = null

const form = useForm({
  name: '',
  email: '',
  phone: '',
  event_type: eventTypeKeys[0],
  branch_code: Object.keys(props.catalog.branches)[0],
  event_date: props.defaults.event_date,
  event_time: props.defaults.event_time,
  duration_hours: props.defaults.duration_hours,
  room_choice: props.defaults.room_choice,
  guests: 10,
  package_code: props.catalog.packages[eventTypeKeys[0]][0].code,
  menu_bundles: ['burger-10'],
  manual_menu_items: [],
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
const menuOptionIndex = computed(() =>
  (props.catalog.menuCategories ?? []).flatMap((category) =>
    (category.items ?? []).flatMap((item) =>
      (item.options ?? []).map((option) => ({
        ...option,
        item_name: item.name,
        item_code: item.code,
        category_code: category.code,
      })),
    ),
  ).reduce((carry, option) => {
    carry[option.code] = option
    return carry
  }, {}),
)
const selectedManualMenuItems = computed(() =>
  (form.manual_menu_items ?? [])
    .map((selection) => {
      const option = menuOptionIndex.value[selection.option_code]

      if (!option) {
        return null
      }

      const quantity = Number(selection.quantity)

      return {
        ...selection,
        item_name: option.item_name,
        item_code: option.item_code,
        option_label: option.label,
        price: Number(option.price),
        quantity,
        line_total: Number(option.price) * quantity,
        category_code: option.category_code,
      }
    })
    .filter(Boolean),
)
const selectedAddOns = computed(() => props.catalog.addOns.filter((item) => form.add_ons.includes(item.code)))

const branchAvailability = computed(() =>
  availabilityState.value.branches.find((item) => item.code === form.branch_code),
)

const dateCards = computed(() => branchAvailability.value?.dates ?? [])

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
const checkOutDate = computed(() => {
  if (!form.event_date || !form.event_time) {
    return ''
  }

  const [hours, minutes] = form.event_time.split(':').map(Number)
  const baseDate = new Date(`${form.event_date}T00:00:00`)
  baseDate.setHours(hours, minutes, 0, 0)
  baseDate.setHours(baseDate.getHours() + Number(form.duration_hours))

  return baseDate.toISOString().slice(0, 10)
})
const additionalHours = computed(() => Math.max(Number(form.duration_hours) - 4, 0))
const canDecreaseDuration = computed(() => Number(form.duration_hours) > 4)
const canIncreaseDuration = computed(() => Number(form.duration_hours) < 8)

const formatCurrency = (value) =>
  `\u20B1${Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`

const defaultRoomChoiceForEventType = (eventType) => {
  if (eventType === 'business' || eventType === 'table') {
    return 'function-room'
  }

  return 'birthday-party-room'
}

const increaseDuration = () => {
  form.duration_hours = Math.min(Number(form.duration_hours) + 1, 8)
}

const decreaseDuration = () => {
  form.duration_hours = Math.max(Number(form.duration_hours) - 1, 4)
}

const scrollToSection = (id) => {
  document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

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

const selectableStartSlotOptions = computed(() =>
  selectableStartSlots.value.map((slot) => ({
    value: slot.time,
    label: `${slot.label} - ${slot.endLabel}`,
    disabled: !slot.startable,
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
    ...selectedManualMenuItems.value.map((item) => ({ label: `${item.quantity} x ${item.item_name} (${item.option_label})`, amount: item.line_total })),
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
  () => form.event_type,
  () => {
    form.room_choice = defaultRoomChoiceForEventType(form.event_type)

    initializeAvailabilitySelection()
  },
)

watch(
  () => form.branch_code,
  () => {
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
  initializeAvailabilitySelection()
})

onBeforeUnmount(() => {
  if (availabilityTimer) {
    window.clearInterval(availabilityTimer)
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

      <form class="space-y-5" @submit.prevent="submit">
        <div class="mcd-grid mcd-grid--2">
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
              <div class="mcd-field">
                <label>Guest count</label>
                <input v-model="form.guests" type="number" min="2" max="60" class="mcd-input" />
              </div>

              <div class="mcd-field">
                <label>Event duration</label>
                <div class="rounded-[1.75rem] border border-slate-200 bg-white p-4 shadow-sm">
                  <div class="flex flex-wrap items-center justify-between gap-4">
                    <div>
                      <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-400">Adjust time</p>
                      <p class="mt-2 text-3xl font-black text-slate-800">{{ form.duration_hours }} hours</p>
                      <p class="mt-1 text-sm text-slate-500">
                        {{ additionalHours > 0 ? `Includes 4 hours + ${additionalHours} additional hour${additionalHours > 1 ? 's' : ''}.` : 'Includes the 4-hour package duration.' }}
                      </p>
                    </div>
                    <div class="inline-flex items-center gap-3 rounded-full bg-amber-50 px-3 py-2">
                      <button
                        type="button"
                        class="flex h-11 w-11 items-center justify-center rounded-full bg-white text-2xl font-black text-red-700 shadow-sm disabled:cursor-not-allowed disabled:opacity-40"
                        :disabled="!canDecreaseDuration"
                        @click="decreaseDuration"
                      >
                        -
                      </button>
                      <span class="min-w-[6rem] text-center text-sm font-black uppercase tracking-[0.14em] text-slate-600">Event duration</span>
                      <button
                        type="button"
                        class="flex h-11 w-11 items-center justify-center rounded-full bg-white text-2xl font-black text-red-700 shadow-sm disabled:cursor-not-allowed disabled:opacity-40"
                        :disabled="!canIncreaseDuration"
                        @click="increaseDuration"
                      >
                        +
                      </button>
                    </div>
                  </div>
                </div>
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

              <div class="mcd-field">
                <label>Room rental</label>
                <div class="grid gap-3 md:grid-cols-3">
                  <label
                    v-for="item in roomChoices"
                    :key="item.code"
                    class="rounded-3xl border p-4 transition"
                    :class="form.room_choice === item.code ? 'border-red-500 bg-red-50' : 'border-slate-200 bg-white'"
                  >
                    <input v-model="form.room_choice" type="radio" :value="item.code" class="hidden" />
                    <p class="text-base font-black text-slate-800">{{ item.label }}</p>
                    <p class="mt-2 text-sm text-slate-500">{{ item.description }}</p>
                  </label>
                </div>
                <p v-if="form.errors.room_choice" class="text-sm text-red-700">{{ form.errors.room_choice }}</p>
              </div>

              <div class="flex flex-wrap items-center justify-between gap-3 rounded-3xl bg-amber-50 px-5 py-4">
                <div>
                  <p class="text-sm font-black uppercase tracking-[0.18em] text-red-700">Need custom food and drinks?</p>
                  <p class="mt-1 text-sm text-slate-600">Jump to the manual ordering board and add items one by one.</p>
                </div>
                <button type="button" class="mcd-button" @click="scrollToSection('manual-menu-board')">Go to manual food and drinks</button>
              </div>

              <div class="rounded-3xl bg-white p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                  <div>
                    <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">Select date and time</p>
                    <p class="mt-2 text-sm text-slate-500">Use the scheduler below to choose the event start and view the automatic check-out details.</p>
                  </div>
                  <button type="button" class="mcd-button mcd-button--ghost" @click="refreshAvailability">Refresh now</button>
                </div>

                <div class="mt-6 rounded-3xl bg-emerald-50/60 p-5">
                  <div v-if="availabilityNotice" class="mt-4 rounded-2xl bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                    {{ availabilityNotice }}
                  </div>

                  <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div class="mcd-field">
                      <label>Check-in Date *</label>
                      <input v-model="form.event_date" type="date" class="mcd-input bg-white" />
                      <p v-if="form.errors.event_date" class="text-sm text-red-700">{{ form.errors.event_date }}</p>
                    </div>

                    <div class="mcd-field">
                      <label>Check-in Time *</label>
                      <select v-model="form.event_time" class="mcd-select bg-white">
                        <option v-for="slot in selectableStartSlotOptions" :key="slot.value" :value="slot.value" :disabled="slot.disabled">
                          {{ slot.label }}
                        </option>
                      </select>
                      <p v-if="form.errors.event_time" class="text-sm text-red-700">{{ form.errors.event_time }}</p>
                    </div>

                    <div class="mcd-field">
                      <label>Check-out Date *</label>
                      <input :value="checkOutDate" type="date" class="mcd-input bg-slate-50" readonly />
                    </div>

                    <div class="mcd-field">
                      <label>Check-out Time *</label>
                      <input :value="endTimeLabel" type="text" class="mcd-input bg-slate-50" readonly />
                    </div>
                  </div>

                  <div class="mt-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-white px-4 py-4">
                    <div>
                      <p class="text-sm font-black uppercase tracking-[0.15em] text-emerald-700">Live availability</p>
                      <p class="mt-1 text-sm text-slate-500">
                        {{ selectedDateCard ? `${selectedDateCard.valid_start_count} start window${selectedDateCard.valid_start_count === 1 ? '' : 's'} open on ${selectedDateCard.date}` : 'Select a date to see available morning slots.' }}
                      </p>
                    </div>
                    <div class="rounded-full bg-emerald-50 px-4 py-2 text-sm font-bold text-emerald-700">
                      {{ formatTimeLabel(form.event_time) }} to {{ endTimeLabel }}
                    </div>
                  </div>

                  <p class="mt-3 text-xs text-slate-500">Check-out date and time update automatically based on the chosen start time and event duration.</p>
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
              <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">4. Package and bundle plan</p>
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
              <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">Bundle add-ons</p>
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
              <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">6. Add-ons and payment</p>
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
          </div>
        </div>

        <ManualMenuBoard
          v-if="catalog.menuCategories?.length"
          v-model="form.manual_menu_items"
          :categories="catalog.menuCategories"
        />

        <section v-else class="mcd-panel p-6">
          <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">5. Manual foods and drinks</p>
          <p class="mt-3 text-sm text-slate-500">The manual menu board will appear here after the menu catalog is loaded into the database.</p>
        </section>

        <section class="mcd-panel mcd-panel--dark p-6">
          <p class="text-sm font-black uppercase tracking-[0.2em] text-amber-300">Booking receipt</p>
          <div class="mt-5 grid gap-6 xl:grid-cols-[0.85fr,1.15fr]">
            <div class="space-y-3 text-sm text-white/80">
              <div class="flex items-center justify-between">
                <span>Event type</span>
                <strong class="text-white">{{ catalog.eventTypes[form.event_type].label }}</strong>
              </div>
              <div class="flex items-center justify-between">
                <span>Branch</span>
                <strong class="text-white">{{ branch?.name }}</strong>
              </div>
              <div class="flex items-center justify-between">
                <span>Room rental</span>
                <strong class="text-white">{{ roomChoices.find((item) => item.code === form.room_choice)?.label }}</strong>
              </div>
              <div class="flex items-center justify-between">
                <span>Event time</span>
                <strong class="text-white">{{ formatTimeLabel(form.event_time) }} to {{ endTimeLabel }}</strong>
              </div>
              <div class="flex items-center justify-between">
                <span>Duration</span>
                <strong class="text-white">{{ form.duration_hours }} hours</strong>
              </div>
              <div class="rounded-3xl bg-white/5 p-4">
                <p class="text-sm uppercase tracking-[0.2em] text-amber-200">Manual tray</p>
                <div v-if="selectedManualMenuItems.length" class="mt-4 space-y-3">
                  <div v-for="item in selectedManualMenuItems" :key="item.option_code" class="flex items-center justify-between gap-4">
                    <span>{{ item.quantity }} x {{ item.item_name }} ({{ item.option_label }})</span>
                    <strong class="text-white">{{ formatCurrency(item.line_total) }}</strong>
                  </div>
                </div>
                <p v-else class="mt-3 text-sm text-white/65">No manual foods or drinks added yet.</p>
              </div>
            </div>

            <div class="space-y-3 text-sm text-white/80">
              <div class="rounded-3xl bg-white/5 p-4">
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
              <div class="border-t border-white/10 pt-4">
                <p class="text-sm uppercase tracking-[0.2em] text-amber-200">Total before confirmation</p>
                <p class="mt-2 text-4xl text-white">{{ formatCurrency(receiptPreview.total) }}</p>
                <p class="mt-2 text-xs text-white/65">This receipt updates in real time as dates and slots become unavailable.</p>
              </div>
              <button type="submit" class="mcd-button mt-2 w-full" :disabled="form.processing || !selectedDateCard || selectedDateCard.computed_status === 'full' || !canStartAt(form.event_time)">
                {{ form.processing ? 'Submitting...' : 'Confirm reservation' }}
              </button>
            </div>
          </div>
        </section>
      </form>
    </section>
  </AppShell>
</template>
