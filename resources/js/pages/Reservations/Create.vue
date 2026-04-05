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
const plannerMode = ref('date')
const activeTimeField = ref('start')
const flexibleDurationOptions = Array.from({ length: 16 }, (_, index) => index + 1)
let availabilityTimer = null

const form = useForm({
  event_type: eventTypeKeys[0],
  branch_code: Object.keys(props.catalog.branches)[0],
  event_date: props.defaults.event_date,
  event_time: props.defaults.event_time,
  event_end_time: '',
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
  if (!time) {
    return '--'
  }

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

const timeToMinutes = (time) => {
  if (!time) {
    return 0
  }

  const [hours, minutes] = time.split(':').map(Number)
  return (hours * 60) + minutes
}

const durationBetweenTimes = (startTime, endTime) => {
  if (!startTime || !endTime) {
    return 0
  }

  return (timeToMinutes(endTime) - timeToMinutes(startTime)) / 60
}

const endTimeLabel = computed(() => formatTimeLabel(form.event_end_time))
const startTimeLabel = computed(() => formatTimeLabel(form.event_time))
const durationLabel = computed(() => `${form.duration_hours} hour${Number(form.duration_hours) === 1 ? '' : 's'}`)
const startDateLabel = computed(() => {
  if (!form.event_date) {
    return 'Select date'
  }

  return new Date(`${form.event_date}T12:00:00`).toLocaleDateString('en-PH', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  })
})
const additionalHours = computed(() => Math.max(Number(form.duration_hours) - 4, 0))
const bookingWindowLabel = computed(() => {
  const openingHour = Number(props.catalog.bookingWindow?.opening_hour ?? 7)
  const closingHour = Number(props.catalog.bookingWindow?.closing_hour ?? 23)

  return `${formatTimeLabel(`${String(openingHour).padStart(2, '0')}:00`)} to ${formatTimeLabel(`${String(closingHour).padStart(2, '0')}:00`)}`
})

const formatCurrency = (value) =>
  `\u20B1${Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`

const defaultRoomChoiceForEventType = (eventType) => {
  return props.roomChoices.find((item) => item.preferred_event_type === eventType)?.code
    ?? props.roomChoices[0]?.code
    ?? ''
}

const scrollToSection = (id) => {
  document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

const canStartAt = (time, dateAvailability = selectedDateAvailability.value, durationHours = Number(form.duration_hours)) => {
  if (!dateAvailability) {
    return false
  }

  const duration = Number(durationHours)
  const slotMap = Object.fromEntries((dateAvailability.slots ?? []).map((slot) => [slot.time, slot]))

  for (let offset = 0; offset < duration; offset += 1) {
    const slot = slotMap[addHoursToTime(time, offset)]
    if (!slot || slot.full) {
      return false
    }
  }

  return true
}

const availableDurationsForStart = (time, dateAvailability = selectedDateAvailability.value) =>
  flexibleDurationOptions.filter((duration) => canStartAt(time, dateAvailability, duration))

const selectableStartSlots = computed(() =>
  (selectedDateAvailability.value?.slots ?? []).map((slot) => ({
    ...slot,
    validDurations: availableDurationsForStart(slot.time),
    startable: availableDurationsForStart(slot.time).length > 0,
  })),
)

const selectableStartSlotOptions = computed(() =>
  selectableStartSlots.value
    .filter((slot) => slot.startable)
    .map((slot) => ({
      value: slot.time,
      label: slot.label,
    })),
)

const selectableEndSlotOptions = computed(() =>
  availableDurationsForStart(form.event_time).map((duration) => ({
    value: addHoursToTime(form.event_time, duration),
    label: `${formatTimeLabel(addHoursToTime(form.event_time, duration))} (${duration} hour${duration > 1 ? 's' : ''})`,
    duration,
  })),
)

const selectedTimeRangeIsAvailable = computed(() =>
  selectableStartSlotOptions.value.some((slot) => slot.value === form.event_time)
  && selectableEndSlotOptions.value.some((slot) => slot.value === form.event_end_time),
)

const plannerTimeOptions = computed(() =>
  activeTimeField.value === 'start' ? selectableStartSlotOptions.value : selectableEndSlotOptions.value,
)

const dateCardsView = computed(() =>
  dateCards.value.map((item) => {
    const validStartCount = (item.slots ?? []).filter((slot) => availableDurationsForStart(slot.time, item).length > 0).length

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

const monthCursor = ref(form.event_date.slice(0, 7))

const availableMonthKeys = computed(() =>
  [...new Set(dateCardsView.value.map((item) => item.date.slice(0, 7)))],
)

const currentMonthIndex = computed(() =>
  Math.max(availableMonthKeys.value.indexOf(monthCursor.value), 0),
)

const calendarMonthLabel = computed(() => {
  if (!monthCursor.value) {
    return ''
  }

  const monthDate = new Date(`${monthCursor.value}-01T12:00:00`)
  return monthDate.toLocaleDateString('en-PH', { month: 'long', year: 'numeric' })
})

const calendarCells = computed(() => {
  if (!monthCursor.value) {
    return []
  }

  const monthStart = new Date(`${monthCursor.value}-01T12:00:00`)
  const daysInMonth = new Date(monthStart.getFullYear(), monthStart.getMonth() + 1, 0).getDate()
  const leadingBlanks = monthStart.getDay()
  const cardsByDate = Object.fromEntries(dateCardsView.value.map((item) => [item.date, item]))
  const cells = []

  for (let index = 0; index < leadingBlanks; index += 1) {
    cells.push({ empty: true, key: `blank-${index}` })
  }

  for (let day = 1; day <= daysInMonth; day += 1) {
    const date = `${monthCursor.value}-${String(day).padStart(2, '0')}`
    const card = cardsByDate[date]
    const isUnavailable = !card || card.computed_status === 'full'

    cells.push({
      key: date,
      empty: false,
      day,
      date,
      selected: form.event_date === date,
      unavailable: isUnavailable,
      limited: card?.computed_status === 'limited',
    })
  }

  return cells
})

const showPreviousMonth = computed(() => currentMonthIndex.value > 0)
const showNextMonth = computed(() => currentMonthIndex.value < availableMonthKeys.value.length - 1)

const goToPreviousMonth = () => {
  if (!showPreviousMonth.value) {
    return
  }

  monthCursor.value = availableMonthKeys.value[currentMonthIndex.value - 1]
}

const goToNextMonth = () => {
  if (!showNextMonth.value) {
    return
  }

  monthCursor.value = availableMonthKeys.value[currentMonthIndex.value + 1]
}

const selectCalendarDate = (cell) => {
  if (cell.unavailable) {
    return
  }

  form.event_date = cell.date
  plannerMode.value = 'time'
  activeTimeField.value = 'start'
}

const setPlannerMode = (mode, field = activeTimeField.value) => {
  plannerMode.value = mode
  activeTimeField.value = field
}

const selectPlannerTime = (value) => {
  if (!value) {
    return
  }

  if (activeTimeField.value === 'start') {
    form.event_time = value
    plannerMode.value = 'time'
    activeTimeField.value = 'end'
    return
  }

  form.event_end_time = value
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

const syncDurationFromRange = () => {
  const nextDuration = durationBetweenTimes(form.event_time, form.event_end_time)

  if (!Number.isInteger(nextDuration) || nextDuration < 1) {
    return false
  }

  form.duration_hours = nextDuration
  return true
}

const syncEndTimeSelection = () => {
  if (!form.event_time) {
    return
  }

  const currentSelectionIsValid = selectableEndSlotOptions.value.some((option) => option.value === form.event_end_time)

  if (currentSelectionIsValid) {
    syncDurationFromRange()
    return
  }

  const fallbackOption = selectableEndSlotOptions.value[0]

  if (fallbackOption) {
    form.event_end_time = fallbackOption.value
    form.duration_hours = fallbackOption.duration
    return
  }

  form.event_end_time = addHoursToTime(form.event_time, 1)
  form.duration_hours = 1
}

const initializeAvailabilitySelection = () => {
  ensureCatalogSelection()

  if (!dateCardsView.value.some((item) => item.date === form.event_date && item.computed_status !== 'full')) {
    form.event_date = dateCardsView.value.find((item) => item.computed_status !== 'full')?.date ?? props.defaults.event_date
  }

  if (!selectableStartSlotOptions.value.some((item) => item.value === form.event_time)) {
    form.event_time = selectableStartSlotOptions.value[0]?.value ?? props.defaults.event_time
  }

  syncEndTimeSelection()
  updateAvailabilityNotice()
}

const updateAvailabilityNotice = () => {
  availabilityNotice.value = ''

  if (!selectedDateCard.value) {
    return
  }

  if (selectedDateCard.value.computed_status === 'full') {
    availabilityNotice.value = 'The chosen date is unavailable or already reserved. Please choose another reservation date.'
    return
  }

  if (!selectableStartSlotOptions.value.length) {
    availabilityNotice.value = 'There are no available start times for this date. Please choose another reservation date.'
    return
  }

  if (!selectableStartSlotOptions.value.some((option) => option.value === form.event_time)) {
    availabilityNotice.value = 'The selected start time is unavailable or already reserved. Please choose another start time.'
    return
  }

  if (!selectableEndSlotOptions.value.length) {
    availabilityNotice.value = 'There are no available end times for the chosen start time. Please select a different start time.'
    return
  }

  if (!selectableEndSlotOptions.value.some((option) => option.value === form.event_end_time)) {
    availabilityNotice.value = 'Select an end time that stays after your start time and within the available booking window.'
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
    monthCursor.value = form.event_date.slice(0, 7)
    if (!selectableStartSlotOptions.value.some((option) => option.value === form.event_time)) {
      form.event_time = selectableStartSlotOptions.value[0]?.value ?? props.defaults.event_time
    }

    syncEndTimeSelection()
    updateAvailabilityNotice()
  },
)

watch(
  () => form.event_time,
  () => {
    syncEndTimeSelection()
    updateAvailabilityNotice()
  },
)

watch(
  () => form.event_end_time,
  () => {
    if (!syncDurationFromRange()) {
      syncEndTimeSelection()
    }

    updateAvailabilityNotice()
  },
)

const refreshAvailability = async () => {
  const { data } = await axios.get(route('availability.index'))
  availabilityState.value = data
  initializeAvailabilitySelection()
}

onMounted(() => {
  availabilityTimer = window.setInterval(() => {
    if (document.visibilityState !== 'visible') {
      return
    }

    refreshAvailability()
  }, 45000)
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
            <h1 class="mt-4 text-4xl">Book your event</h1>
          </div>
          <div class="rounded-3xl bg-red-50 px-5 py-4 text-sm text-red-800">
            Hours: {{ bookingWindowLabel }}
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
                      <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-400">Selected duration</p>
                      <p class="mt-2 text-3xl font-black text-slate-800">{{ durationLabel }}</p>
                      <p class="mt-1 text-sm text-slate-500">Total updates automatically.</p>
                    </div>
                    <div class="grid gap-2 text-right">
                      <span class="rounded-full bg-amber-50 px-4 py-2 text-sm font-black uppercase tracking-[0.14em] text-amber-700">Flexible timing</span>
                      <span class="rounded-full bg-red-50 px-4 py-2 text-sm font-black uppercase tracking-[0.14em] text-red-700">Schedule</span>
                    </div>
                  </div>
                </div>
                <p class="mt-2 text-xs text-slate-500">Extra time is charged at {{ formatCurrency(catalog.pricing.extension_hourly_rate) }}/hour.</p>
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
                  <p class="text-sm font-black uppercase tracking-[0.18em] text-red-700">Manual food and drinks</p>
                </div>
                <button type="button" class="mcd-button" @click="scrollToSection('manual-menu-board')">Go to manual food and drinks</button>
              </div>

              <div class="rounded-3xl bg-white p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                  <div>
                    <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">Reservation schedule</p>
                  </div>
                  <button type="button" class="mcd-button mcd-button--ghost" @click="refreshAvailability">Refresh now</button>
                </div>

                <div class="mcd-schedule-planner mt-6">
                  <div class="mcd-schedule-planner__surface">
                    <div class="mcd-schedule-planner__orb">
                      <div class="flex items-center justify-between gap-3">
                        <button type="button" class="mcd-schedule-planner__month-button" :disabled="!showPreviousMonth" @click="goToPreviousMonth">
                          &lt;
                        </button>
                        <p class="text-base font-black tracking-[0.03em] text-slate-700">{{ calendarMonthLabel }}</p>
                        <button type="button" class="mcd-schedule-planner__month-button" :disabled="!showNextMonth" @click="goToNextMonth">
                          &gt;
                        </button>
                      </div>

                      <div v-if="plannerMode === 'date'">
                        <div class="mt-5 grid grid-cols-7 gap-2 text-center text-[11px] font-black uppercase tracking-[0.12em] text-slate-400">
                          <span v-for="day in ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa']" :key="day">{{ day }}</span>
                        </div>

                        <div class="mt-3 grid grid-cols-7 gap-2">
                          <button
                            v-for="cell in calendarCells"
                            :key="cell.key"
                            type="button"
                            class="mcd-schedule-planner__day"
                            :class="{
                              'is-empty': cell.empty,
                              'is-selected': cell.selected,
                              'is-unavailable': cell.unavailable,
                              'is-limited': cell.limited,
                            }"
                            :disabled="cell.empty || cell.unavailable"
                            @click="selectCalendarDate(cell)"
                          >
                            <span v-if="!cell.empty">{{ cell.day }}</span>
                          </button>
                        </div>
                      </div>

                      <div v-else class="mcd-schedule-planner__time-panel">
                        <p class="mcd-schedule-planner__time-title">
                          {{ activeTimeField === 'start' ? 'Start Time' : 'End Time' }}
                        </p>

                        <div class="mcd-schedule-planner__time-grid">
                          <button
                            v-for="option in plannerTimeOptions"
                            :key="option.value"
                            type="button"
                            class="mcd-schedule-planner__time-option"
                            :class="{
                              'is-selected': activeTimeField === 'start' ? form.event_time === option.value : form.event_end_time === option.value,
                            }"
                            @click="selectPlannerTime(option.value)"
                          >
                            <strong>{{ activeTimeField === 'start' ? formatTimeLabel(option.value) : formatTimeLabel(option.value) }}</strong>
                            <small>{{ activeTimeField === 'start' ? 'Start of event' : option.duration ? `${option.duration} hours total` : 'End of event' }}</small>
                          </button>
                        </div>

                        <p v-if="!plannerTimeOptions.length" class="rounded-2xl bg-slate-100 px-4 py-3 text-sm font-semibold text-slate-500">
                          No available {{ activeTimeField === 'start' ? 'start' : 'end' }} times for this selection.
                        </p>
                      </div>

                      <div class="mcd-schedule-planner__switches">
                        <button
                          type="button"
                          class="mcd-schedule-planner__switch"
                          :class="{ 'is-active': plannerMode === 'date' }"
                          @click="setPlannerMode('date', 'start')"
                        >
                          <span class="mcd-schedule-planner__switch-box"></span>
                          Start Date
                        </button>
                        <button
                          type="button"
                          class="mcd-schedule-planner__switch"
                          :class="{ 'is-active': plannerMode === 'time' }"
                          @click="setPlannerMode('time', activeTimeField)"
                        >
                          <span class="mcd-schedule-planner__switch-box"></span>
                          Time
                        </button>
                      </div>

                      <div class="mcd-schedule-planner__time-cards">
                        <button type="button" class="mcd-schedule-planner__time-card" :class="{ 'is-active': plannerMode === 'time' && activeTimeField === 'start' }" @click="setPlannerMode('time', 'start')">
                          <span>Start Time</span>
                          <strong>{{ startTimeLabel }}</strong>
                          <small>{{ startDateLabel }}</small>
                        </button>
                        <button type="button" class="mcd-schedule-planner__time-card" :class="{ 'is-active': plannerMode === 'time' && activeTimeField === 'end' }" @click="setPlannerMode('time', 'end')">
                          <span>Due Time</span>
                          <strong>{{ endTimeLabel }}</strong>
                          <small>{{ durationLabel }}</small>
                        </button>
                      </div>
                    </div>

                    <div class="mcd-schedule-planner__details">
                      <div v-if="availabilityNotice" class="rounded-2xl bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                        {{ availabilityNotice }}
                      </div>

                      <div class="mcd-schedule-planner__fields">
                        <div class="grid gap-2">
                          <label class="text-base font-bold text-slate-700">Event Date <span class="text-red-500">*</span></label>
                          <input v-model="form.event_date" type="date" class="mcd-schedule-planner__input" />
                          <p v-if="form.errors.event_date" class="text-sm text-red-700">{{ form.errors.event_date }}</p>
                        </div>

                        <div class="grid gap-2">
                          <label class="text-base font-bold text-slate-700">Start Time <span class="text-red-500">*</span></label>
                          <select v-model="form.event_time" class="mcd-schedule-planner__input">
                            <option v-if="!selectableStartSlotOptions.length" value="" disabled>No start times available</option>
                            <option v-for="slot in selectableStartSlotOptions" :key="slot.value" :value="slot.value">
                              {{ slot.label }}
                            </option>
                          </select>
                          <p v-if="form.errors.event_time" class="text-sm text-red-700">{{ form.errors.event_time }}</p>
                        </div>

                        <div class="grid gap-2">
                          <label class="text-base font-bold text-slate-700">End Time <span class="text-red-500">*</span></label>
                          <select v-model="form.event_end_time" class="mcd-schedule-planner__input">
                            <option v-if="!selectableEndSlotOptions.length" value="" disabled>No end times available</option>
                            <option v-for="slot in selectableEndSlotOptions" :key="slot.value" :value="slot.value">
                              {{ slot.label }}
                            </option>
                          </select>
                          <p v-if="form.errors.event_end_time" class="text-sm text-red-700">{{ form.errors.event_end_time }}</p>
                        </div>

                        <div class="grid gap-2">
                          <label class="text-base font-bold text-slate-700">Duration</label>
                          <div class="mcd-schedule-planner__input flex items-center justify-between">
                            <span class="font-semibold text-slate-500">Reserved span</span>
                            <strong class="text-base font-black text-slate-800">{{ durationLabel }}</strong>
                          </div>
                        </div>
                      </div>

                    </div>
                  </div>
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
            <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">3. Special notes</p>
            <div class="mt-5 grid gap-4">
              <div class="mcd-field">
                <label>Special notes</label>
                <textarea v-model="form.notes" rows="4" class="mcd-textarea"></textarea>
                <p class="mt-2 text-xs text-slate-500">Your profile details will be attached to this reservation automatically.</p>
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
          <p class="mt-3 text-sm text-slate-500">Menu unavailable.</p>
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
                <p v-else class="mt-3 text-sm text-white/65">No items added.</p>
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
              </div>
              <button type="submit" class="mcd-button mt-2 w-full" :disabled="form.processing || !selectedDateCard || selectedDateCard.computed_status === 'full' || !selectedTimeRangeIsAvailable">
                {{ form.processing ? 'Submitting...' : 'Confirm reservation' }}
              </button>
            </div>
          </div>
        </section>
      </form>
    </section>
  </AppShell>
</template>
