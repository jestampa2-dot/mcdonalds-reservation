<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import AdminQuickLinks from '@/Components/AdminQuickLinks.vue'
import StatusBadge from '@/Components/StatusBadge.vue'

const props = defineProps({
  dayAvailability: Object,
  returnTo: Object,
})

const backHref = computed(() => route('admin.availability', {
  branch: props.returnTo?.branch || props.dayAvailability?.branch?.code,
  month: props.returnTo?.month || props.dayAvailability?.date?.slice(0, 7),
}))

const statusStyles = {
  available: 'border-emerald-200 bg-emerald-50 text-emerald-700',
  limited: 'border-amber-200 bg-amber-50 text-amber-700',
  full: 'border-rose-200 bg-rose-50 text-rose-700',
}
</script>

<template>
  <AppShell title="Availability Day View">
    <section class="mcd-section">
      <div class="mcd-panel p-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <p class="mcd-chip">{{ dayAvailability.branch.name }}</p>
            <h1 class="mt-4 text-4xl">{{ dayAvailability.formatted_date }}</h1>
            <p class="mt-2 text-sm text-slate-500">{{ dayAvailability.weekday }} availability with open times, active bookings, and rooms.</p>
          </div>
          <Link :href="backHref" class="mcd-button mcd-button--ghost">Back to calendar</Link>
        </div>

        <div class="mt-6">
          <AdminQuickLinks current="availability" />
        </div>

        <div class="mt-6 grid gap-3 md:grid-cols-3">
          <div class="rounded-3xl border border-emerald-200 bg-emerald-50 px-4 py-4">
            <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-700">Open times</p>
            <p class="mt-3 text-3xl font-black text-slate-900">{{ dayAvailability.open_slots }}</p>
          </div>
          <div class="rounded-3xl border border-amber-200 bg-amber-50 px-4 py-4">
            <p class="text-xs font-black uppercase tracking-[0.18em] text-amber-700">Rooms</p>
            <p class="mt-3 text-3xl font-black text-slate-900">{{ dayAvailability.rooms.length }}</p>
          </div>
          <div class="rounded-3xl border border-rose-200 bg-rose-50 px-4 py-4">
            <p class="text-xs font-black uppercase tracking-[0.18em] text-rose-700">Active events</p>
            <p class="mt-3 text-3xl font-black text-slate-900">{{ dayAvailability.bookings.length }}</p>
          </div>
        </div>
      </div>
    </section>

    <section class="mcd-section">
      <div class="grid gap-6 xl:grid-cols-[0.38fr,1fr]">
        <article class="mcd-panel p-6">
          <div class="flex items-center justify-between gap-3">
            <div>
              <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">Rooms</p>
              <h2 class="mt-2 text-2xl">{{ dayAvailability.branch.name }}</h2>
            </div>
            <span class="rounded-full bg-amber-100 px-3 py-2 text-xs font-black uppercase tracking-[0.18em] text-amber-700">
              {{ dayAvailability.branch.concurrent_limit }} at once
            </span>
          </div>

          <div class="mt-5 space-y-3">
            <article
              v-for="room in dayAvailability.rooms"
              :key="room.code"
              class="rounded-3xl border border-slate-200 bg-white p-4"
            >
              <div class="flex items-start justify-between gap-3">
                <div>
                  <h3 class="text-lg font-bold">{{ room.label }}</h3>
                  <p class="mt-1 text-sm text-slate-500">{{ room.description }}</p>
                </div>
                <span class="rounded-full bg-slate-100 px-3 py-2 text-xs font-black uppercase tracking-[0.18em] text-slate-600">
                  {{ room.bookings_count }} booking{{ room.bookings_count === 1 ? '' : 's' }}
                </span>
              </div>
              <div class="mt-3 text-sm text-slate-600">
                <p v-if="room.schedule.length">{{ room.schedule.join(' | ') }}</p>
                <p v-else>No events scheduled.</p>
              </div>
            </article>
          </div>
        </article>

        <article class="mcd-panel p-6">
          <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
              <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">Time availability</p>
              <h2 class="mt-2 text-2xl">Open times and rooms</h2>
            </div>
            <p class="text-sm text-slate-500">Click back to choose another date.</p>
          </div>

          <div class="mt-5 grid gap-4 lg:grid-cols-2">
            <article
              v-for="slot in dayAvailability.time_slots"
              :key="slot.time"
              class="rounded-3xl border p-5"
              :class="statusStyles[slot.status]"
            >
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="text-sm font-black uppercase tracking-[0.18em]">{{ slot.status }}</p>
                  <h3 class="mt-2 text-2xl font-black text-slate-900">{{ slot.range_label }}</h3>
                </div>
                <span class="rounded-full bg-white/80 px-3 py-2 text-xs font-black uppercase tracking-[0.18em] text-slate-700">
                  {{ slot.remaining_capacity }} slot{{ slot.remaining_capacity === 1 ? '' : 's' }} left
                </span>
              </div>

              <div class="mt-4 grid gap-3 md:grid-cols-2">
                <div class="rounded-2xl bg-white/80 p-4">
                  <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Available rooms</p>
                  <p v-if="slot.available_rooms.length" class="mt-2 text-sm font-semibold text-slate-700">
                    {{ slot.available_rooms.join(', ') }}
                  </p>
                  <p v-else class="mt-2 text-sm font-semibold text-slate-400">No room available.</p>
                </div>
                <div class="rounded-2xl bg-white/80 p-4">
                  <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Occupied rooms</p>
                  <p v-if="slot.occupied_rooms.length" class="mt-2 text-sm font-semibold text-slate-700">
                    {{ slot.occupied_rooms.join(', ') }}
                  </p>
                  <p v-else class="mt-2 text-sm font-semibold text-slate-400">No occupied room.</p>
                </div>
              </div>

              <div v-if="slot.active_events.length" class="mt-4 rounded-2xl bg-white/80 p-4">
                <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-500">Running at this time</p>
                <div class="mt-3 space-y-2">
                  <div v-for="event in slot.active_events" :key="`${slot.time}-${event.booking_reference}`" class="flex items-center justify-between gap-3 rounded-2xl bg-slate-50 px-3 py-3">
                    <div>
                      <p class="font-bold text-slate-900">{{ event.booking_reference }}</p>
                      <p class="text-sm text-slate-500">{{ event.customer_name }} | {{ event.room_choice }}</p>
                    </div>
                    <div class="text-right">
                      <StatusBadge :value="event.status" />
                      <p class="mt-2 text-xs text-slate-500">{{ event.event_time }}</p>
                    </div>
                  </div>
                </div>
              </div>
            </article>
          </div>
        </article>
      </div>
    </section>

    <section class="mcd-section">
      <article class="mcd-panel p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">Day bookings</p>
            <h2 class="mt-2 text-2xl">Reservations on this date</h2>
          </div>
          <p class="text-sm text-slate-500">{{ dayAvailability.bookings.length }} booking{{ dayAvailability.bookings.length === 1 ? '' : 's' }}</p>
        </div>

        <div v-if="dayAvailability.bookings.length" class="mt-5 grid gap-4 xl:grid-cols-2">
          <article
            v-for="booking in dayAvailability.bookings"
            :key="booking.id"
            class="rounded-3xl border border-slate-200 bg-white p-5"
          >
            <div class="flex flex-wrap items-center gap-2">
              <strong>{{ booking.booking_reference }}</strong>
              <StatusBadge :value="booking.status" />
              <StatusBadge :value="booking.service_status" />
            </div>
            <p class="mt-3 text-sm text-slate-600">{{ booking.customer_name }} | {{ booking.customer_phone }}</p>
            <p class="mt-1 text-sm text-slate-600">{{ booking.event_time }}</p>
            <p class="mt-1 text-sm text-slate-600">Room: {{ booking.room_choice || 'Main event floor' }}</p>
            <p class="mt-1 text-sm text-slate-600">Package: {{ booking.package_name }}</p>
          </article>
        </div>
        <div v-else class="mcd-empty mt-5">No reservations on this date.</div>
      </article>
    </section>
  </AppShell>
</template>
