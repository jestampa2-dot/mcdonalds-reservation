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
let availabilityTimer = null

const form = useForm({
  name: '',
  email: '',
  phone: '',
  event_type: eventTypeKeys[0],
  branch_code: Object.keys(props.catalog.branches)[0],
  event_date: props.defaults.event_date,
  event_time: props.defaults.event_time,
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

const selectedDateAvailability = computed(() =>
  dateCards.value.find((item) => item.date === form.event_date),
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
    ...(selectedPackage.value ? [{ label: selectedPackage.value.name, amount: selectedPackage.value.price }] : []),
    ...selectedBundles.value.map((item) => ({ label: item.name, amount: item.price })),
    ...selectedAddOns.value.map((item) => ({ label: item.name, amount: item.price })),
  ]
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

const slotIsFull = (time) => {
  const slot = selectedDateAvailability.value?.slots.find((item) => item.time === time)
  return slot?.full ?? false
}

const syncSelectionToAvailability = () => {
  const fallbackBranch = supportedBranches.value[0]

  if (!supportedBranches.value.some((item) => item.code === form.branch_code)) {
    form.branch_code = fallbackBranch?.code ?? form.branch_code
  }

  if (!packages.value.some((item) => item.code === form.package_code)) {
    form.package_code = packages.value[0]?.code ?? ''
  }

  if (!dateCards.value.some((item) => item.date === form.event_date && item.status !== 'full')) {
    form.event_date = dateCards.value.find((item) => item.status !== 'full')?.date ?? props.defaults.event_date
  }

  if (slotIsFull(form.event_time)) {
    form.event_time = selectedDateAvailability.value?.slots.find((item) => !item.full)?.time ?? props.defaults.event_time
  }
}

watch(
  () => [form.event_type, form.branch_code],
  () => {
    syncSelectionToAvailability()
  },
)

watch(
  () => form.event_date,
  () => {
    if (slotIsFull(form.event_time)) {
      form.event_time = selectedDateAvailability.value?.slots.find((item) => !item.full)?.time ?? props.defaults.event_time
    }
  },
)

const refreshAvailability = async () => {
  const { data } = await axios.get(route('availability.index'))
  availabilityState.value = data
  syncSelectionToAvailability()
}

onMounted(() => {
  availabilityTimer = window.setInterval(refreshAvailability, 15000)
  syncSelectionToAvailability()
})

onBeforeUnmount(() => {
  if (availabilityTimer) {
    window.clearInterval(availabilityTimer)
  }
})

const submit = () => {
  form.post(route('reservations.store'))
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
            Live availability refreshes every 15 seconds for customers and admins.
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
                <label>Branch</label>
                <select v-model="form.branch_code" class="mcd-select">
                  <option v-for="item in supportedBranches" :key="item.code" :value="item.code">
                    {{ item.name }} | {{ item.city }}
                  </option>
                </select>
                <p v-if="form.errors.branch_code" class="text-sm text-red-700">{{ form.errors.branch_code }}</p>
              </div>

              <div class="rounded-3xl bg-white p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                  <div>
                    <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">Date availability</p>
                    <p class="mt-1 text-sm text-slate-500">Pick from live available, limited, or full dates.</p>
                  </div>
                  <button type="button" class="mcd-button mcd-button--ghost" @click="refreshAvailability">Refresh now</button>
                </div>

                <div class="mt-4 grid gap-3 md:grid-cols-2">
                  <button
                    v-for="item in dateCards"
                    :key="item.date"
                    type="button"
                    class="rounded-3xl border p-4 text-left transition"
                    :class="item.status === 'full'
                      ? 'border-slate-200 bg-slate-100 text-slate-500'
                      : form.event_date === item.date
                        ? 'border-red-500 bg-red-50'
                        : item.status === 'limited'
                          ? 'border-amber-300 bg-amber-50'
                          : 'border-emerald-200 bg-emerald-50'"
                    :disabled="item.status === 'full'"
                    @click="form.event_date = item.date"
                  >
                    <p class="font-bold">{{ item.date }}</p>
                    <p class="mt-1 text-sm">Status: {{ item.status }}</p>
                    <p class="mt-1 text-xs">Available slots: {{ item.available_slots }}</p>
                  </button>
                </div>
              </div>

              <div class="rounded-3xl bg-amber-50 p-5">
                <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">Available slots</p>
                <div class="mt-4 flex flex-wrap gap-2">
                  <button
                    v-for="slot in selectedDateAvailability?.slots ?? []"
                    :key="slot.time"
                    type="button"
                    class="rounded-full px-4 py-2 text-sm font-bold"
                    :class="slot.full
                      ? 'cursor-not-allowed bg-slate-200 text-slate-500'
                      : form.event_time === slot.time
                        ? 'bg-red-600 text-white'
                        : 'bg-white text-slate-700'"
                    :disabled="slot.full"
                    @click="form.event_time = slot.time"
                  >
                    {{ slot.time }} ({{ slot.remaining }} left)
                  </button>
                </div>
                <p v-if="form.errors.event_time" class="mt-3 text-sm text-red-700">{{ form.errors.event_time }}</p>
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
                  </div>
                  <strong class="text-red-700">${{ Number(item.price).toLocaleString() }}</strong>
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
                  <span class="font-bold text-red-700">${{ Number(item.price).toLocaleString() }}</span>
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
                  <span class="font-bold text-red-700">${{ Number(item.price).toLocaleString() }}</span>
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
                <span>Slot</span>
                <strong class="text-white">{{ form.event_date }} at {{ form.event_time }}</strong>
              </div>
              <div class="mt-4 rounded-3xl bg-white/5 p-4">
                <p class="text-sm uppercase tracking-[0.2em] text-amber-200">Receipt preview</p>
                <div class="mt-4 space-y-3">
                  <div v-for="item in receiptPreview.lineItems" :key="item.label" class="flex items-center justify-between gap-4">
                    <span>{{ item.label }}</span>
                    <strong class="text-white">${{ item.amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</strong>
                  </div>
                </div>
                <div class="mt-4 space-y-2 border-t border-white/10 pt-4">
                  <div class="flex items-center justify-between">
                    <span>Subtotal</span>
                    <strong class="text-white">${{ receiptPreview.subtotal.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</strong>
                  </div>
                  <div class="flex items-center justify-between">
                    <span>{{ pricingRule.label }} ({{ pricingRule.multiplier }}x)</span>
                    <strong class="text-white">${{ receiptPreview.adjustment.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</strong>
                  </div>
                </div>
              </div>
              <div class="mt-4 border-t border-white/10 pt-4">
                <p class="text-sm uppercase tracking-[0.2em] text-amber-200">Total before confirmation</p>
                <p class="mt-2 text-4xl text-white">${{ receiptPreview.total.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}</p>
                <p class="mt-2 text-xs text-white/65">This receipt updates in real time as dates and slots become unavailable.</p>
              </div>
              <button type="submit" class="mcd-button mt-6 w-full" :disabled="form.processing || !selectedDateAvailability || selectedDateAvailability.status === 'full'">
                {{ form.processing ? 'Submitting...' : 'Confirm reservation' }}
              </button>
            </div>
          </section>
        </div>
      </form>
    </section>
  </AppShell>
</template>
