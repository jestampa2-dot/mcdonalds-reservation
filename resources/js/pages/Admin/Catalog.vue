<script setup>
import { reactive } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import AdminQuickLinks from '@/Components/AdminQuickLinks.vue'

const props = defineProps({
  eventTypes: Array,
  roomOptions: Array,
  bookingSettings: Object,
})

const eventTypeState = reactive(Object.fromEntries(
  props.eventTypes.map((eventType) => [
    eventType.id,
    {
      label: eventType.label,
      description: eventType.description,
      icon: eventType.icon ?? '',
      is_active: eventType.is_active,
    },
  ]),
))

const packageState = reactive(Object.fromEntries(
  props.eventTypes.flatMap((eventType) => eventType.packages.map((item) => [
    item.id,
    {
      name: item.name,
      price: item.price,
      guest_range: item.guest_range ?? '',
      features: (item.features ?? []).join('\n'),
      is_active: item.is_active,
    },
  ])),
))

const roomOptionState = reactive(Object.fromEntries(
  props.roomOptions.map((roomOption) => [
    roomOption.id,
    {
      label: roomOption.label,
      description: roomOption.description,
      preferred_event_type: roomOption.preferred_event_type ?? '',
      is_active: roomOption.is_active,
    },
  ]),
))

const bookingSettingsForm = useForm({
  opening_hour: props.bookingSettings.opening_hour ?? 7,
  closing_hour: props.bookingSettings.closing_hour ?? 23,
  default_duration_hours: props.bookingSettings.default_duration_hours ?? 4,
})

const roomOptionForm = useForm({
  label: '',
  description: '',
  preferred_event_type: '',
})

const updateEventType = (id) => {
  router.post(route('admin.event-types.update', id), eventTypeState[id], {
    preserveScroll: true,
    preserveState: true,
  })
}

const updatePackage = (id) => {
  router.post(route('admin.packages.update', id), packageState[id], {
    preserveScroll: true,
    preserveState: true,
  })
}

const updateRoomOption = (id) => {
  router.post(route('admin.room-options.update', id), roomOptionState[id], {
    preserveScroll: true,
    preserveState: true,
  })
}

const createRoomOption = () => {
  roomOptionForm.post(route('admin.room-options.store'), {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => roomOptionForm.reset(),
  })
}

const updateBookingSettings = () => {
  bookingSettingsForm.post(route('admin.booking-settings.update'), {
    preserveScroll: true,
    preserveState: true,
  })
}

const formatCurrency = (value) =>
  `\u20B1${Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`

const formatHour = (hour) => {
  const value = Number(hour)
  const meridian = value >= 12 ? 'PM' : 'AM'
  const hour12 = value % 12 || 12
  return `${hour12}:00 ${meridian}`
}
</script>

<template>
  <AppShell title="Admin Catalog">
    <section class="mcd-section">
      <div class="mcd-panel p-8">
        <p class="mcd-chip">Catalog</p>
        <h1 class="mt-4 text-4xl">Database-backed event catalog, room rentals, and booking window controls.</h1>
        <p class="mt-4 max-w-3xl text-sm text-slate-600">
          This page now manages customer-facing booking data directly from the database, including event types, packages, room rental options, and operating hours.
        </p>
        <div class="mt-6">
          <AdminQuickLinks current="catalog" />
        </div>
      </div>
    </section>

    <section class="mcd-section">
      <div class="mcd-grid mcd-grid--2">
        <article class="mcd-panel p-6">
          <p class="mcd-chip">Booking hours</p>
          <h2 class="mt-4 text-3xl">Reservation operating window</h2>
          <p class="mt-3 text-sm leading-6 text-slate-600">
            Update the daily booking hours and the default starting duration used in the customer booking flow.
          </p>

          <div class="mt-6 grid gap-4 md:grid-cols-3">
            <div class="mcd-field">
              <label>Opening hour</label>
              <select v-model="bookingSettingsForm.opening_hour" class="mcd-select">
                <option v-for="hour in 23" :key="hour - 1" :value="hour - 1">{{ formatHour(hour - 1) }}</option>
              </select>
            </div>
            <div class="mcd-field">
              <label>Closing hour</label>
              <select v-model="bookingSettingsForm.closing_hour" class="mcd-select">
                <option v-for="hour in 23" :key="hour" :value="hour">{{ formatHour(hour) }}</option>
              </select>
            </div>
            <div class="mcd-field">
              <label>Default duration</label>
              <input v-model="bookingSettingsForm.default_duration_hours" type="number" min="1" max="16" class="mcd-input" />
            </div>
          </div>

          <div class="mt-5 flex flex-wrap items-center justify-between gap-3 rounded-3xl bg-amber-50 p-4">
            <div>
              <p class="text-xs font-black uppercase tracking-[0.16em] text-red-700">Current booking window</p>
              <p class="mt-2 text-lg font-bold text-slate-800">
                {{ formatHour(bookingSettingsForm.opening_hour) }} to {{ formatHour(bookingSettingsForm.closing_hour) }}
              </p>
            </div>
            <button type="button" class="mcd-button" @click="updateBookingSettings">Save booking hours</button>
          </div>
        </article>

        <article class="mcd-panel p-6">
          <p class="mcd-chip">Room rentals</p>
          <h2 class="mt-4 text-3xl">Add new room options</h2>
          <p class="mt-3 text-sm leading-6 text-slate-600">
            Add rentable spaces that customers can choose during booking. Each option is stored as its own database record.
          </p>

          <form class="mt-6 grid gap-4" @submit.prevent="createRoomOption">
            <input v-model="roomOptionForm.label" type="text" class="mcd-input" placeholder="Room label" />
            <textarea v-model="roomOptionForm.description" rows="4" class="mcd-textarea" placeholder="Room description"></textarea>
            <select v-model="roomOptionForm.preferred_event_type" class="mcd-select">
              <option value="">No preferred event type</option>
              <option v-for="eventType in eventTypes" :key="eventType.id" :value="eventType.code">{{ eventType.label }}</option>
            </select>
            <button type="submit" class="mcd-button" :disabled="roomOptionForm.processing">
              {{ roomOptionForm.processing ? 'Adding...' : 'Add room option' }}
            </button>
          </form>
        </article>
      </div>
    </section>

    <section class="mcd-section">
      <div class="space-y-6">
        <article v-for="eventType in eventTypes" :key="eventType.id" class="mcd-panel p-6">
          <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
              <p class="mcd-chip">{{ eventType.code }}</p>
              <h2 class="mt-3 text-3xl">{{ eventType.label }}</h2>
            </div>
            <div class="rounded-3xl px-4 py-2 text-sm font-bold" :class="eventTypeState[eventType.id].is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700'">
              {{ eventTypeState[eventType.id].is_active ? 'Available' : 'Unavailable' }}
            </div>
          </div>

          <div class="mt-6 grid gap-6 xl:grid-cols-[0.9fr,1.1fr]">
            <div class="rounded-3xl bg-amber-50 p-5">
              <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">Edit event type</p>
              <div class="mt-4 grid gap-4">
                <input v-model="eventTypeState[eventType.id].label" type="text" class="mcd-input" placeholder="Event label" />
                <input v-model="eventTypeState[eventType.id].icon" type="text" class="mcd-input" placeholder="Icon name" />
                <textarea v-model="eventTypeState[eventType.id].description" rows="4" class="mcd-textarea" placeholder="Event description"></textarea>
                <select v-model="eventTypeState[eventType.id].is_active" class="mcd-select">
                  <option :value="true">available</option>
                  <option :value="false">unavailable</option>
                </select>
                <button type="button" class="mcd-button" @click="updateEventType(eventType.id)">Save event type</button>
              </div>
            </div>

            <div class="space-y-4">
              <div
                v-for="item in eventType.packages"
                :key="item.id"
                class="rounded-3xl border border-slate-200 bg-white p-5"
              >
                <div class="flex flex-wrap items-start justify-between gap-3">
                  <div>
                    <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-400">{{ item.code }}</p>
                    <h3 class="mt-2 text-2xl">{{ item.name }}</h3>
                  </div>
                  <div class="text-right">
                    <p class="text-xs font-black uppercase tracking-[0.16em] text-slate-400">Current price</p>
                    <p class="mt-2 text-xl font-bold text-red-700">{{ formatCurrency(packageState[item.id].price) }}</p>
                  </div>
                </div>

                <div class="mt-4 grid gap-4 md:grid-cols-2">
                  <input v-model="packageState[item.id].name" type="text" class="mcd-input" placeholder="Package name" />
                  <input v-model="packageState[item.id].guest_range" type="text" class="mcd-input" placeholder="Guest range" />
                </div>

                <div class="mt-4 grid gap-4 md:grid-cols-[0.7fr,0.5fr]">
                  <textarea
                    v-model="packageState[item.id].features"
                    rows="4"
                    class="mcd-textarea"
                    placeholder="One feature per line"
                  ></textarea>
                  <div class="grid gap-4">
                    <input v-model="packageState[item.id].price" type="number" min="0" step="0.01" class="mcd-input" placeholder="Price" />
                    <select v-model="packageState[item.id].is_active" class="mcd-select">
                      <option :value="true">available</option>
                      <option :value="false">unavailable</option>
                    </select>
                    <button type="button" class="mcd-button" @click="updatePackage(item.id)">Save package</button>
                  </div>
                </div>
              </div>

              <div v-if="!eventType.packages.length" class="mcd-empty">
                No packages are assigned to this event type yet.
              </div>
            </div>
          </div>
        </article>
      </div>
    </section>

    <section class="mcd-section">
      <div class="mcd-grid mcd-grid--2">
        <article
          v-for="roomOption in roomOptions"
          :key="roomOption.id"
          class="mcd-panel p-6"
        >
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="mcd-chip">{{ roomOption.code }}</p>
              <h3 class="mt-3 text-2xl">{{ roomOption.label }}</h3>
            </div>
            <div class="rounded-3xl px-4 py-2 text-sm font-bold" :class="roomOptionState[roomOption.id].is_active ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-200 text-slate-700'">
              {{ roomOptionState[roomOption.id].is_active ? 'Available' : 'Unavailable' }}
            </div>
          </div>

          <div class="mt-5 grid gap-4">
            <input v-model="roomOptionState[roomOption.id].label" type="text" class="mcd-input" placeholder="Room label" />
            <textarea v-model="roomOptionState[roomOption.id].description" rows="4" class="mcd-textarea" placeholder="Room description"></textarea>
            <select v-model="roomOptionState[roomOption.id].preferred_event_type" class="mcd-select">
              <option value="">No preferred event type</option>
              <option v-for="eventType in eventTypes" :key="eventType.id" :value="eventType.code">{{ eventType.label }}</option>
            </select>
            <select v-model="roomOptionState[roomOption.id].is_active" class="mcd-select">
              <option :value="true">available</option>
              <option :value="false">unavailable</option>
            </select>
            <button type="button" class="mcd-button" @click="updateRoomOption(roomOption.id)">Save room option</button>
          </div>
        </article>
      </div>
    </section>
  </AppShell>
</template>
