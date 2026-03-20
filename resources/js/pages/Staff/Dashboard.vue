<script setup>
import { reactive } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import StatusBadge from '@/Components/StatusBadge.vue'

const props = defineProps({
  prepList: Array,
  todayBookings: Array,
  statusOptions: Array,
})

const checkInForm = useForm({
  code: '',
})

const statusState = reactive(
  Object.fromEntries(props.todayBookings.map((booking) => [booking.id, booking.service_status])),
)

const checkIn = () => {
  checkInForm.post(route('staff.check-in'), {
    onSuccess: () => checkInForm.reset(),
  })
}

const updateStatus = (id) => {
  router.post(route('staff.reservations.service-status', id), {
    service_status: statusState[id],
  })
}
</script>

<template>
  <AppShell title="Staff Dashboard">
    <section class="mcd-section">
      <div class="mcd-grid mcd-grid--2">
        <article class="mcd-panel p-8">
          <p class="mcd-chip">Crew view</p>
          <h1 class="mt-4 text-4xl">Prep meals, check in arrivals, and keep the floor updated in real time.</h1>
        </article>

        <article class="mcd-panel mcd-panel--dark p-8">
          <p class="text-sm uppercase tracking-[0.2em] text-amber-300">Check-in scanner</p>
          <div class="mt-5 flex flex-col gap-3 md:flex-row">
            <input v-model="checkInForm.code" type="text" class="mcd-input" placeholder="Enter booking reference or check-in code" />
            <button type="button" class="mcd-button" @click="checkIn">Check in guest</button>
          </div>
          <p class="mt-3 text-sm text-white/70">
            Works with the customer&apos;s QR pass reference and fallback check-in code.
          </p>
        </article>
      </div>
    </section>

    <section class="mcd-section">
      <div class="mcd-grid mcd-grid--2">
        <article class="mcd-panel p-6">
          <p class="mcd-chip">Daily prep list</p>
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
            </div>
          </div>
          <div v-else class="mcd-empty mt-5">No prep items scheduled for today.</div>
        </article>

        <article class="mcd-panel p-6">
          <p class="mcd-chip">Floor status</p>
          <div v-if="todayBookings.length" class="mt-5 space-y-4">
            <div v-for="booking in todayBookings" :key="booking.id" class="rounded-3xl bg-white p-5">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="font-bold">{{ booking.package_name }}</p>
                  <p class="mt-1 text-sm text-slate-500">{{ booking.branch }} | {{ booking.event_time }}</p>
                </div>
                <div class="flex gap-2">
                  <StatusBadge :value="booking.status" />
                  <StatusBadge :value="booking.service_status" />
                </div>
              </div>

              <div class="mt-4 flex flex-col gap-3 md:flex-row">
                <select v-model="statusState[booking.id]" class="mcd-select">
                  <option v-for="option in statusOptions" :key="option" :value="option">{{ option }}</option>
                </select>
                <button type="button" class="mcd-button" @click="updateStatus(booking.id)">Update status</button>
              </div>
            </div>
          </div>
          <div v-else class="mcd-empty mt-5">No live events on the floor right now.</div>
        </article>
      </div>
    </section>
  </AppShell>
</template>
