<script setup>
import axios from 'axios'
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import BookingStepAddOns from '@/Components/BookingWizard/BookingStepAddOns.vue'
import BookingStepEventType from '@/Components/BookingWizard/BookingStepEventType.vue'
import BookingStepManualMenu from '@/Components/BookingWizard/BookingStepManualMenu.vue'
import BookingStepNotes from '@/Components/BookingWizard/BookingStepNotes.vue'
import BookingStepPackagePlan from '@/Components/BookingWizard/BookingStepPackagePlan.vue'
import BookingStepPayment from '@/Components/BookingWizard/BookingStepPayment.vue'
import BookingStepSchedule from '@/Components/BookingWizard/BookingStepSchedule.vue'
import BookingWizardProgress from '@/Components/BookingWizard/BookingWizardProgress.vue'

const props = defineProps({
  catalog: Object,
  roomChoices: Array,
  availability: Object,
  defaults: Object,
})

const catalogEventTypes = props.catalog?.eventTypes ?? {}
const catalogBranches = props.catalog?.branches ?? {}
const catalogPackages = props.catalog?.packages ?? {}
const catalogMenuBundles = props.catalog?.menuBundles ?? []
const catalogPricing = props.catalog?.pricing ?? {
  weekend_multiplier: 1.15,
  holiday_multiplier: 1.25,
  extension_hourly_rate: 450,
  holidays: [],
}
const defaultEventDate = props.defaults?.event_date ?? new Date().toISOString().slice(0, 10)
const defaultEventTime = props.defaults?.event_time ?? '10:00'
const defaultDurationHours = Number(props.defaults?.duration_hours ?? props.catalog?.bookingWindow?.default_duration_hours ?? 4)
const eventTypeKeys = Object.keys(catalogEventTypes)
const branchKeys = Object.keys(catalogBranches)
const initialEventType = eventTypeKeys[0] ?? ''
const initialBranchCode = branchKeys[0] ?? ''
const initialPackageCode = catalogPackages[initialEventType]?.[0]?.code ?? ''
const initialMenuBundleCode = catalogMenuBundles[0]?.code
const availabilityState = ref(props.availability ?? { branches: [] })
const availabilityNotice = ref('')
const plannerMode = ref('date')
const activeTimeField = ref('start')
const flexibleDurationOptions = Array.from({ length: 16 }, (_, index) => index + 1)
let availabilityTimer = null

const form = useForm({
  event_type: initialEventType,
  branch_code: initialBranchCode,
  event_date: defaultEventDate,
  event_time: defaultEventTime,
  event_end_time: '',
  duration_hours: defaultDurationHours,
  room_choice: props.defaults?.room_choice ?? '',
  guests: 10,
  package_code: initialPackageCode,
  menu_bundles: initialMenuBundleCode ? [initialMenuBundleCode] : [],
  manual_menu_items: [],
  add_ons: [],
  notes: '',
  payment_proof: null,
})

const supportedBranches = computed(() =>
  Object.values(catalogBranches).filter((branch) => branch.supports?.[form.event_type]),
)

const branch = computed(() => catalogBranches[form.branch_code])
const packages = computed(() => catalogPackages[form.event_type] ?? [])
const selectedPackage = computed(() => packages.value.find((item) => item.code === form.package_code))
const selectedBundles = computed(() => (props.catalog?.menuBundles ?? []).filter((item) => form.menu_bundles.includes(item.code)))
const menuOptionIndex = computed(() =>
  (props.catalog?.menuCategories ?? []).flatMap((category) =>
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
const selectedAddOns = computed(() => (props.catalog?.addOns ?? []).filter((item) => form.add_ons.includes(item.code)))

const branchAvailability = computed(() =>
  (availabilityState.value?.branches ?? []).find((item) => item.code === form.branch_code),
)

const dateCards = computed(() => branchAvailability.value?.dates ?? [])

const selectedDateAvailability = computed(() =>
  dateCards.value.find((item) => item.date === form.event_date),
)

const formatTimeLabel = (time) => {
  if (!time || !String(time).includes(':')) {
    return '--'
  }

  const [hours, minutes] = String(time).split(':').map(Number)

  if (!Number.isFinite(hours) || !Number.isFinite(minutes)) {
    return '--'
  }

  const meridian = hours >= 12 ? 'PM' : 'AM'
  const hour12 = hours % 12 || 12
  return `${hour12}:${String(minutes).padStart(2, '0')} ${meridian}`
}

const addHoursToTime = (time, hoursToAdd) => {
  if (!time || !String(time).includes(':')) {
    return ''
  }

  const [hours, minutes] = String(time).split(':').map(Number)

  if (!Number.isFinite(hours) || !Number.isFinite(minutes)) {
    return ''
  }

  const totalMinutes = (hours * 60) + minutes + (Number(hoursToAdd) * 60)
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
  const openingHour = Number(props.catalog?.bookingWindow?.opening_hour ?? 7)
  const closingHour = Number(props.catalog?.bookingWindow?.closing_hour ?? 23)

  return `${formatTimeLabel(`${String(openingHour).padStart(2, '0')}:00`)} to ${formatTimeLabel(`${String(closingHour).padStart(2, '0')}:00`)}`
})

const formatCurrency = (value) =>
  `\u20B1${Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`

const defaultRoomChoiceForEventType = (eventType) => {
  return (props.roomChoices ?? []).find((item) => item.preferred_event_type === eventType)?.code
    ?? (props.roomChoices ?? [])[0]?.code
    ?? ''
}

const scrollToSection = (id) => {
  document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}

const slotMapsByDate = computed(() =>
  Object.fromEntries(dateCards.value.map((item) => [
    item.date,
    Object.fromEntries((item.slots ?? []).map((slot) => [slot.time, slot])),
  ])),
)

const slotMapForDateAvailability = (dateAvailability) => {
  if (!dateAvailability) {
    return {}
  }

  return slotMapsByDate.value[dateAvailability.date]
    ?? Object.fromEntries((dateAvailability.slots ?? []).map((slot) => [slot.time, slot]))
}

const canStartAt = (time, dateAvailability = selectedDateAvailability.value, durationHours = Number(form.duration_hours)) => {
  const duration = Number(durationHours)

  if (!time || !dateAvailability || !Number.isInteger(duration) || duration < 1) {
    return false
  }

  const slotMap = slotMapForDateAvailability(dateAvailability)

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
  (selectedDateAvailability.value?.slots ?? []).map((slot) => {
    const validDurations = availableDurationsForStart(slot.time)

    return {
      ...slot,
      validDurations,
      startable: validDurations.length > 0,
    }
  }),
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

const monthCursor = ref((form.event_date || defaultEventDate).slice(0, 7))

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
  if ((catalogPricing.holidays ?? []).includes(form.event_date)) {
    return {
      label: 'Holiday rate',
      multiplier: catalogPricing.holiday_multiplier,
    }
  }

  const day = new Date(`${form.event_date}T12:00:00`).getDay()

  if ([0, 6].includes(day)) {
    return {
      label: 'Weekend rate',
      multiplier: catalogPricing.weekend_multiplier,
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
      amount: extensionHours * Number(catalogPricing.extension_hourly_rate),
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

  const preferredDuration = Number(form.duration_hours || defaultDurationHours)
  const fallbackOption = selectableEndSlotOptions.value.find((option) => option.duration === preferredDuration)
    ?? selectableEndSlotOptions.value.find((option) => option.duration === defaultDurationHours)
    ?? selectableEndSlotOptions.value[0]

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
  const { data } = await axios.get(route('availability.index'), { params: { days: 60 } })
  availabilityState.value = data
  initializeAvailabilitySelection()
}

const wizardSteps = [
  { order: 1, label: 'Event', hint: 'Choose the occasion.' },
  { order: 2, label: 'Schedule', hint: 'Set the branch, room, date, and time.' },
  { order: 3, label: 'Package', hint: 'Choose guest count and package.' },
  { order: 4, label: 'Menu', hint: 'Add exact food and drink items.' },
  { order: 5, label: 'Theme & extras', hint: 'Choose decor and optional character services.' },
  { order: 6, label: 'Notes', hint: 'Share requests with the branch.' },
  { order: 7, label: 'Payment', hint: 'Upload proof and confirm.' },
]
const currentStep = ref(1)
const highestStepVisited = ref(1)
const isWizardOpen = ref(true)
const stepError = ref('')
const stepContent = ref(null)
const closeButton = ref(null)
const paymentProofPreviewUrl = ref('')
const currentStepDetails = computed(() => wizardSteps.find((step) => step.order === currentStep.value) ?? wizardSteps[0])
const progressPercent = computed(() => Math.round((currentStep.value / wizardSteps.length) * 100))

const scrollStepToTop = () => {
  nextTick(() => stepContent.value?.scrollTo({ top: 0, behavior: 'smooth' }))
}

const focusWizard = () => {
  nextTick(() => closeButton.value?.focus())
}

const openWizard = () => {
  isWizardOpen.value = true
  focusWizard()
}

const closeWizard = () => {
  if (!form.processing) {
    isWizardOpen.value = false
  }
}

const validationMessageForStep = (step) => {
  if (step === 1 && !catalogEventTypes[form.event_type]) {
    return 'Choose an event type before continuing.'
  }

  if (step === 2) {
    if (!supportedBranches.value.some((item) => item.code === form.branch_code)) {
      return 'Choose a branch that supports this event type.'
    }
    if (!(props.roomChoices ?? []).some((item) => item.code === form.room_choice)) {
      return 'Choose a room for the event.'
    }
    if (!selectedDateCard.value || selectedDateCard.value.computed_status === 'full') {
      return 'Choose an available reservation date.'
    }
    if (!selectedTimeRangeIsAvailable.value) {
      return availabilityNotice.value || 'Choose an available start and end time.'
    }
  }

  if (step === 3) {
    const guests = Number(form.guests)
    if (!Number.isInteger(guests) || guests < 2 || guests > 60) {
      return 'Guest count must be between 2 and 60.'
    }
    if (!selectedPackage.value) {
      return 'Choose a package before continuing.'
    }
  }

  if (step === 4 && (form.manual_menu_items ?? []).some((item) => !item.option_code || Number(item.quantity) < 1)) {
    return 'Check the quantities in your food and drinks selection.'
  }

  if (step === 6 && String(form.notes ?? '').length > 1000) {
    return 'Special notes must be 1,000 characters or fewer.'
  }

  if (step === 7) {
    if (!form.payment_proof) {
      return 'Upload an image of your payment proof before confirming.'
    }
    if (!String(form.payment_proof.type ?? '').startsWith('image/')) {
      return 'Payment proof must be an image file.'
    }
    if (Number(form.payment_proof.size ?? 0) > 4 * 1024 * 1024) {
      return 'Payment proof must be 4 MB or smaller.'
    }
  }

  return ''
}

const showStepError = (message) => {
  stepError.value = message
  scrollStepToTop()
}

const goToStep = (step) => {
  const nextStep = Number(step)

  if (nextStep < 1 || nextStep > highestStepVisited.value) {
    return
  }

  currentStep.value = nextStep
  stepError.value = ''
  scrollStepToTop()
}

const continueWizard = () => {
  const message = validationMessageForStep(currentStep.value)

  if (message) {
    showStepError(message)
    return
  }

  const nextStep = Math.min(currentStep.value + 1, wizardSteps.length)
  currentStep.value = nextStep
  highestStepVisited.value = Math.max(highestStepVisited.value, nextStep)
  stepError.value = ''
  scrollStepToTop()
}

const previousStep = () => {
  if (currentStep.value > 1) {
    currentStep.value -= 1
    stepError.value = ''
    scrollStepToTop()
  }
}

const stepForServerErrors = (errors) => {
  const errorFields = Object.keys(errors ?? {})
  const groups = [
    { step: 1, fields: ['event_type'] },
    { step: 2, fields: ['branch_code', 'room_choice', 'event_date', 'event_time', 'event_end_time', 'duration_hours'] },
    { step: 3, fields: ['guests', 'package_code', 'menu_bundles'] },
    { step: 4, fields: ['manual_menu_items'] },
    { step: 5, fields: ['add_ons'] },
    { step: 6, fields: ['notes'] },
    { step: 7, fields: ['payment_proof'] },
  ]

  return groups.find((group) => group.fields.some((field) => errorFields.some((key) => key === field || key.startsWith(`${field}.`))))?.step ?? 7
}

const releasePaymentProofPreview = () => {
  if (paymentProofPreviewUrl.value) {
    URL.revokeObjectURL(paymentProofPreviewUrl.value)
    paymentProofPreviewUrl.value = ''
  }
}

watch(
  () => form.payment_proof,
  (file) => {
    releasePaymentProofPreview()
    if (file && String(file.type ?? '').startsWith('image/')) {
      paymentProofPreviewUrl.value = URL.createObjectURL(file)
    }
    stepError.value = ''
  },
)

watch(isWizardOpen, (isOpen) => {
  document.documentElement.classList.toggle('booking-wizard-open', isOpen)
  if (isOpen) {
    focusWizard()
  }
})

const handleWizardKeydown = (event) => {
  if (event.key === 'Escape' && isWizardOpen.value) {
    closeWizard()
  }
}

onMounted(() => {
  availabilityTimer = window.setInterval(() => {
    if (document.visibilityState !== 'visible') {
      return
    }

    refreshAvailability()
  }, 45000)
  initializeAvailabilitySelection()
  document.documentElement.classList.add('booking-wizard-open')
  window.addEventListener('keydown', handleWizardKeydown)
  focusWizard()
})

onBeforeUnmount(() => {
  if (availabilityTimer) {
    window.clearInterval(availabilityTimer)
  }
  releasePaymentProofPreview()
  document.documentElement.classList.remove('booking-wizard-open')
  window.removeEventListener('keydown', handleWizardKeydown)
})

const submit = () => {
  for (const step of wizardSteps) {
    const message = validationMessageForStep(step.order)
    if (message) {
      currentStep.value = step.order
      highestStepVisited.value = Math.max(highestStepVisited.value, step.order)
      showStepError(message)
      return
    }
  }

  form.post(route('reservations.store'), {
    preserveScroll: true,
    preserveState: true,
    onError: (errors) => {
      const errorStep = stepForServerErrors(errors)
      currentStep.value = errorStep
      highestStepVisited.value = Math.max(highestStepVisited.value, errorStep)
      isWizardOpen.value = true
      showStepError(Object.values(errors)[0] ?? 'Check this step and try again.')
    },
  })
}
</script>

<template>
  <AppShell title="Book Event">
    <section class="mcd-section">
      <div class="mcd-panel booking-wizard-launcher">
        <div class="booking-wizard-launcher__copy">
          <p class="mcd-chip">Guided booking</p>
          <h1>Plan your event one step at a time.</h1>
          <p>Your selections stay saved while you move between the seven short steps.</p>
        </div>
        <div class="booking-wizard-launcher__action">
          <span>{{ highestStepVisited > 1 ? `Step ${currentStep} of ${wizardSteps.length}` : 'Ready when you are' }}</span>
          <strong>{{ currentStepDetails.label }}</strong>
          <button type="button" class="mcd-button" @click="openWizard">
            {{ highestStepVisited > 1 ? 'Continue booking' : 'Start booking' }}
          </button>
        </div>
      </div>
    </section>

    <Teleport to="body">
      <Transition name="booking-wizard">
        <div
          v-if="isWizardOpen"
          class="booking-wizard-overlay"
          role="presentation"
          @mousedown.self="closeWizard"
        >
          <section
            class="booking-wizard-dialog"
            role="dialog"
            aria-modal="true"
            aria-labelledby="booking-wizard-title"
            @mousedown.stop
          >
            <header class="booking-wizard-dialog__header">
              <div class="booking-wizard-dialog__heading">
                <div>
                  <p class="booking-wizard-dialog__eyebrow">
                    Step {{ currentStep }} of {{ wizardSteps.length }} · {{ progressPercent }}% complete
                  </p>
                  <h2 id="booking-wizard-title">{{ currentStepDetails.label }}</h2>
                  <p>{{ currentStepDetails.hint }}</p>
                </div>
                <button
                  ref="closeButton"
                  type="button"
                  class="booking-wizard-dialog__close"
                  aria-label="Close booking wizard"
                  title="Close booking wizard"
                  :disabled="form.processing"
                  @click="closeWizard"
                >
                  &times;
                </button>
              </div>

              <BookingWizardProgress
                :steps="wizardSteps"
                :current-step="currentStep"
                :highest-step-visited="highestStepVisited"
                @go="goToStep"
              />
            </header>

            <form class="booking-wizard-dialog__form" @submit.prevent="submit">
              <div ref="stepContent" class="booking-wizard-dialog__body">
                <div v-if="stepError" class="booking-wizard-dialog__error" role="alert">
                  <strong>Check this step</strong>
                  <span>{{ stepError }}</span>
                </div>

                <BookingStepEventType
                  v-if="currentStep === 1"
                  :form="form"
                  :event-types="catalog.eventTypes"
                />

                <BookingStepSchedule
                  v-else-if="currentStep === 2"
                  :form="form"
                  :supported-branches="supportedBranches"
                  :room-choices="roomChoices"
                  :branch="branch"
                  :booking-window-label="bookingWindowLabel"
                  :refresh-availability="refreshAvailability"
                  :planner-mode="plannerMode"
                  :active-time-field="activeTimeField"
                  :show-previous-month="showPreviousMonth"
                  :show-next-month="showNextMonth"
                  :go-to-previous-month="goToPreviousMonth"
                  :go-to-next-month="goToNextMonth"
                  :calendar-month-label="calendarMonthLabel"
                  :calendar-cells="calendarCells"
                  :select-calendar-date="selectCalendarDate"
                  :set-planner-mode="setPlannerMode"
                  :planner-time-options="plannerTimeOptions"
                  :select-planner-time="selectPlannerTime"
                  :start-time-label="startTimeLabel"
                  :start-date-label="startDateLabel"
                  :end-time-label="endTimeLabel"
                  :duration-label="durationLabel"
                  :availability-notice="availabilityNotice"
                  :selectable-start-slot-options="selectableStartSlotOptions"
                  :selectable-end-slot-options="selectableEndSlotOptions"
                />

                <BookingStepPackagePlan
                  v-else-if="currentStep === 3"
                  :form="form"
                  :packages="packages"
                  :menu-bundles="catalog.menuBundles"
                  :format-currency="formatCurrency"
                />

                <BookingStepManualMenu
                  v-else-if="currentStep === 4"
                  :form="form"
                  :categories="catalog.menuCategories"
                />

                <BookingStepAddOns
                  v-else-if="currentStep === 5"
                  :form="form"
                  :add-ons="catalog.addOns"
                  :format-currency="formatCurrency"
                />

                <BookingStepNotes
                  v-else-if="currentStep === 6"
                  :form="form"
                />

                <BookingStepPayment
                  v-else
                  :form="form"
                  :catalog="catalog"
                  :branch="branch"
                  :room-choices="roomChoices"
                  :start-time-label="startTimeLabel"
                  :end-time-label="endTimeLabel"
                  :receipt-preview="receiptPreview"
                  :pricing-rule="pricingRule"
                  :selected-manual-menu-items="selectedManualMenuItems"
                  :format-currency="formatCurrency"
                  :payment-proof-preview-url="paymentProofPreviewUrl"
                />
              </div>

              <footer class="booking-wizard-dialog__footer">
                <button
                  type="button"
                  class="mcd-button mcd-button--ghost"
                  :disabled="currentStep === 1 || form.processing"
                  @click="previousStep"
                >
                  Back
                </button>

                <div class="booking-wizard-dialog__footer-copy">
                  <span>{{ currentStepDetails.label }}</span>
                  <strong v-if="currentStep === 7">{{ formatCurrency(receiptPreview.total) }}</strong>
                  <strong v-else>Step {{ currentStep }} of {{ wizardSteps.length }}</strong>
                </div>

                <button
                  v-if="currentStep < wizardSteps.length"
                  type="button"
                  class="mcd-button"
                  @click="continueWizard"
                >
                  Continue
                </button>
                <button
                  v-else
                  type="submit"
                  class="mcd-button"
                  :disabled="form.processing || !selectedDateCard || selectedDateCard.computed_status === 'full' || !selectedTimeRangeIsAvailable"
                >
                  {{ form.processing ? 'Submitting...' : 'Confirm reservation' }}
                </button>
              </footer>
            </form>
          </section>
        </div>
      </Transition>
    </Teleport>
  </AppShell>
</template>
