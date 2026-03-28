<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import AdminQuickLinks from '@/Components/AdminQuickLinks.vue'
import StatusBadge from '@/Components/StatusBadge.vue'

const props = defineProps({
  stats: Array,
  groupedBookings: Array,
  staffUsers: Array,
  menuBundles: Array,
  addOns: Array,
  durationOptions: Array,
})
let dashboardTimer = null

const pendingBookings = computed(() => props.groupedBookings.flatMap((group) =>
  group.types.flatMap((typeGroup) => typeGroup.bookings),
))

const statusState = reactive(Object.fromEntries(pendingBookings.value.map((booking) => [booking.id, booking.status])))
const crewState = reactive(Object.fromEntries(pendingBookings.value.map((booking) => [booking.id, booking.assigned_staff_id ?? ''])))
const adjustmentState = reactive(Object.fromEntries(pendingBookings.value.map((booking) => [booking.id, {
  duration_hours: booking.duration_hours ?? 4,
  extra_menu_bundles: booking.service_adjustments?.extra_menu_bundles ?? [],
  extra_add_ons: booking.service_adjustments?.extra_add_ons ?? [],
}])))

onMounted(() => {
  dashboardTimer = window.setInterval(() => {
    if (document.visibilityState !== 'visible') {
      return
    }

    router.reload({
      only: ['stats', 'groupedBookings', 'staffUsers'],
      preserveScroll: true,
      preserveState: true,
    })
  }, 60000)
})

watch(
  () => props.groupedBookings,
  (groups) => {
    groups.flatMap((group) => group.types.flatMap((typeGroup) => typeGroup.bookings)).forEach((booking) => {
      statusState[booking.id] = booking.status
      crewState[booking.id] = booking.assigned_staff_id ?? ''
      adjustmentState[booking.id] = {
        duration_hours: booking.duration_hours ?? 4,
        extra_menu_bundles: booking.service_adjustments?.extra_menu_bundles ?? [],
        extra_add_ons: booking.service_adjustments?.extra_add_ons ?? [],
      }
    })
  },
)

onBeforeUnmount(() => {
  if (dashboardTimer) {
    window.clearInterval(dashboardTimer)
  }
})

const updateStatus = (id) => {
  router.post(route('admin.reservations.status', id), { status: statusState[id] }, { preserveScroll: true, preserveState: true })
}

const updateCrew = (id) => {
  router.post(route('admin.reservations.crew', id), { assigned_staff_id: crewState[id] || null }, { preserveScroll: true, preserveState: true })
}

const updateServiceAdjustments = (id) => {
  router.post(route('staff.reservations.adjustments', id), adjustmentState[id], { preserveScroll: true, preserveState: true })
}
</script>

<template>
  <AppShell title="Admin Pending Bookings">
    <section class="mcd-section">
      <div class="mcd-panel p-8">
        <p class="mcd-chip">Pending bookings</p>
        <h1 class="mt-4 text-4xl">Review new reservations before they move into confirmed events.</h1>
        <div class="mt-6">
          <AdminQuickLinks current="pending" />
        </div>
      </div>
    </section>

    <section class="mcd-section">
      <article class="mcd-panel p-6">
        <p class="mcd-chip">Pending approvals by branch and type</p>
        <div class="mt-5 space-y-6">
          <section v-for="group in groupedBookings" :key="group.branch_code" class="rounded-3xl bg-white p-5">
            <div>
              <h2 class="text-2xl">{{ group.branch }}</h2>
              <p class="mt-1 text-sm text-slate-500">{{ group.city }}</p>
            </div>

            <div class="mt-5 grid gap-4">
              <article v-for="typeGroup in group.types" :key="typeGroup.type" class="rounded-3xl border border-slate-200 p-5">
                <div class="flex items-center justify-between gap-3">
                  <h3 class="text-xl">{{ typeGroup.label }}</h3>
                  <span class="mcd-badge mcd-badge--pending">{{ typeGroup.bookings.length }} bookings</span>
                </div>

                <div v-if="typeGroup.bookings.length" class="mt-4 space-y-4">
                  <div v-for="booking in typeGroup.bookings" :key="booking.id" class="rounded-2xl bg-amber-50 p-4">
                    <div class="grid gap-4 md:grid-cols-[1.1fr,0.8fr,0.8fr]">
                      <div>
                        <div class="flex flex-wrap items-center gap-2">
                          <strong>{{ booking.booking_reference }}</strong>
                          <StatusBadge :value="booking.status" />
                          <StatusBadge :value="booking.service_status" />
                        </div>
                        <p class="mt-2 text-sm text-slate-600">{{ booking.package_name }} | {{ booking.event_date }} | {{ booking.event_time }}</p>
                        <p class="mt-1 text-sm text-slate-500">Customer: {{ booking.customer_name }} | {{ booking.customer_phone }}</p>
                        <p class="mt-1 text-sm text-slate-500">Duration: {{ booking.duration_hours }} hours</p>

                        <div class="mt-4 grid gap-4 lg:grid-cols-[1fr,0.95fr]">
                          <div class="rounded-2xl bg-white p-4 text-sm">
                            <p class="font-black uppercase tracking-[0.15em] text-red-700">Customer notes</p>
                            <p class="mt-3 text-slate-600">{{ booking.notes || 'No special notes provided.' }}</p>
                          </div>
                          <div class="rounded-2xl bg-white p-4 text-sm">
                            <div class="flex items-center justify-between gap-3">
                              <p class="font-black uppercase tracking-[0.15em] text-red-700">Proof of payment</p>
                              <a :href="booking.payment_proof_url" class="text-sm font-bold text-red-700">Download proof</a>
                            </div>
                            <img
                              v-if="booking.payment_proof_preview_url"
                              :src="booking.payment_proof_preview_url"
                              :alt="`Payment proof ${booking.booking_reference}`"
                              class="mt-3 h-40 w-full rounded-2xl object-cover"
                            />
                          </div>
                        </div>
                      </div>

                      <div class="space-y-3">
                        <p class="text-sm font-black uppercase tracking-[0.15em] text-red-700">Approve booking</p>
                        <select v-model="statusState[booking.id]" class="mcd-select">
                          <option value="pending_review">pending review</option>
                          <option value="confirmed">confirmed</option>
                          <option value="cancelled">cancelled</option>
                        </select>
                        <button type="button" class="mcd-button" @click="updateStatus(booking.id)">Save status</button>
                      </div>

                      <div class="space-y-3">
                        <p class="text-sm font-black uppercase tracking-[0.15em] text-red-700">Crew and adjustments</p>
                        <select v-model="crewState[booking.id]" class="mcd-select">
                          <option value="">Unassigned</option>
                          <option v-for="staff in staffUsers" :key="staff.id" :value="staff.id">{{ staff.name }} ({{ staff.role }})</option>
                        </select>
                        <button type="button" class="mcd-button mcd-button--ghost" @click="updateCrew(booking.id)">Update crew</button>

                        <div class="rounded-2xl bg-white p-4">
                          <div class="space-y-3">
                            <select v-model="adjustmentState[booking.id].duration_hours" class="mcd-select">
                              <option v-for="hours in durationOptions" :key="hours" :value="hours">{{ hours }} hours total</option>
                            </select>

                            <div class="rounded-2xl bg-amber-50 p-3">
                              <p class="text-xs font-black uppercase tracking-[0.15em] text-red-700">Extra food</p>
                              <label v-for="bundle in menuBundles" :key="`${booking.id}-${bundle.code}`" class="mt-2 flex items-center gap-2 text-sm">
                                <input v-model="adjustmentState[booking.id].extra_menu_bundles" :value="bundle.code" type="checkbox" />
                                <span>{{ bundle.name }}</span>
                              </label>
                            </div>

                            <div class="rounded-2xl bg-amber-50 p-3">
                              <p class="text-xs font-black uppercase tracking-[0.15em] text-red-700">Extra services</p>
                              <label v-for="item in addOns" :key="`${booking.id}-${item.code}`" class="mt-2 flex items-center gap-2 text-sm">
                                <input v-model="adjustmentState[booking.id].extra_add_ons" :value="item.code" type="checkbox" />
                                <span>{{ item.name }}</span>
                              </label>
                            </div>

                            <button type="button" class="mcd-button mcd-button--ghost" @click="updateServiceAdjustments(booking.id)">Save event edits</button>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <div v-else class="mcd-empty mt-4">No pending bookings for this branch and type.</div>
              </article>
            </div>
          </section>
        </div>
      </article>
    </section>
  </AppShell>
</template>
