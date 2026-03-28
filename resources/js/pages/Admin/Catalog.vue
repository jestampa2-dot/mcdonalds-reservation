<script setup>
import { reactive } from 'vue'
import { router } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import AdminQuickLinks from '@/Components/AdminQuickLinks.vue'

const props = defineProps({
  eventTypes: Array,
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

const formatCurrency = (value) =>
  `\u20B1${Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
</script>

<template>
  <AppShell title="Admin Catalog">
    <section class="mcd-section">
      <div class="mcd-panel p-8">
        <p class="mcd-chip">Catalog</p>
        <h1 class="mt-4 text-4xl">Edit event types, package details, and availability from one clean admin page.</h1>
        <p class="mt-4 max-w-3xl text-sm text-slate-600">
          Marking an event type or package as unavailable removes it from the customer booking flow without needing extra page reloads.
        </p>
        <div class="mt-6">
          <AdminQuickLinks current="catalog" />
        </div>
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
  </AppShell>
</template>
