<script setup>
import axios from 'axios'
import { onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import StatusBadge from '@/Components/StatusBadge.vue'

const props = defineProps({
  stats: Array,
  calendar: Array,
  groupedBookings: Array,
  inventory: Array,
  staffAssignments: Array,
  availability: Object,
  pricing: Object,
  report: Object,
  users: Array,
  staffUsers: Array,
  branches: Array,
})

const availabilityState = ref(props.availability)
const statusState = reactive(Object.fromEntries(props.calendar.map((booking) => [booking.id, booking.status])))
const crewState = reactive(Object.fromEntries(props.calendar.map((booking) => [booking.id, booking.assigned_staff_id ?? ''])))
const roleState = reactive(Object.fromEntries(props.users.map((user) => [user.id, user.role])))
let dashboardTimer = null

const branchForm = useForm({
  name: '',
  city: '',
  code: '',
  map_url: '',
  concurrent_limit: 2,
  max_guests: 40,
  supports: ['birthday', 'table'],
})

const updateStatus = (id) => {
  router.post(route('admin.reservations.status', id), { status: statusState[id] })
}

const updateCrew = (id) => {
  router.post(route('admin.reservations.crew', id), { assigned_staff_id: crewState[id] || null })
}

const updateRole = (id) => {
  router.post(route('admin.users.role', id), { role: roleState[id] })
}

const createBranch = () => {
  branchForm.post(route('admin.branches.store'))
}

const refreshAvailability = async () => {
  const { data } = await axios.get(route('availability.index'))
  availabilityState.value = data
}

onMounted(() => {
  dashboardTimer = window.setInterval(() => {
    router.reload({
      only: ['calendar', 'groupedBookings', 'stats', 'users', 'staffUsers', 'branches'],
      preserveScroll: true,
      preserveState: true,
    })
    refreshAvailability()
  }, 15000)
})

watch(
  () => props.calendar,
  (bookings) => {
    bookings.forEach((booking) => {
      statusState[booking.id] = booking.status
      crewState[booking.id] = booking.assigned_staff_id ?? ''
    })
  },
)

watch(
  () => props.users,
  (users) => {
    users.forEach((user) => {
      roleState[user.id] = user.role
    })
  },
)

onBeforeUnmount(() => {
  if (dashboardTimer) {
    window.clearInterval(dashboardTimer)
  }
})
</script>

<template>
  <AppShell title="Admin Dashboard">
    <section class="mcd-section">
      <div class="mcd-panel p-8">
        <p class="mcd-chip">Admin operations hub</p>
        <h1 class="mt-4 text-4xl">Branch-led bookings, account approvals, crew assignments, and live availability in one view.</h1>
      </div>

      <div class="mcd-grid mcd-grid--3">
        <article v-for="item in stats" :key="item.label" class="mcd-panel p-6">
          <p class="text-sm uppercase tracking-[0.25em] text-slate-500">{{ item.label }}</p>
          <p class="mt-3 text-4xl text-red-700">{{ item.value }}</p>
        </article>
      </div>
    </section>

    <section class="mcd-section">
      <div class="mcd-grid mcd-grid--2">
        <article class="mcd-panel p-6">
          <div class="flex items-center justify-between gap-3">
            <div>
              <p class="mcd-chip">Live availability</p>
              <h2 class="mt-3 text-3xl">Available and unavailable dates by branch</h2>
            </div>
            <button type="button" class="mcd-button mcd-button--ghost" @click="refreshAvailability">Refresh now</button>
          </div>

          <div class="mt-5 space-y-4">
            <div v-for="branch in availabilityState.branches" :key="branch.code" class="rounded-3xl bg-white p-5">
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <h3 class="text-xl">{{ branch.name }}</h3>
                  <p class="mt-1 text-sm text-slate-500">{{ branch.city }}</p>
                </div>
                <div class="flex flex-wrap gap-2">
                  <span
                    v-for="(supported, key) in branch.supports"
                    :key="key"
                    class="mcd-badge"
                    :class="supported ? 'mcd-badge--success' : 'mcd-badge--danger'"
                  >
                    {{ key }}
                  </span>
                </div>
              </div>

              <div class="mt-4 grid gap-3 md:grid-cols-2">
                <div
                  v-for="dateItem in branch.dates.slice(0, 6)"
                  :key="dateItem.date"
                  class="rounded-2xl border p-4"
                  :class="dateItem.status === 'full'
                    ? 'border-slate-200 bg-slate-100'
                    : dateItem.status === 'limited'
                      ? 'border-amber-300 bg-amber-50'
                      : 'border-emerald-200 bg-emerald-50'"
                >
                  <p class="font-bold">{{ dateItem.date }}</p>
                  <p class="mt-1 text-sm capitalize">{{ dateItem.status }}</p>
                  <p class="mt-1 text-xs">Available slots: {{ dateItem.available_slots }}</p>
                </div>
              </div>
            </div>
          </div>
        </article>

        <article class="mcd-panel p-6">
          <p class="mcd-chip">Branch management</p>
          <h2 class="mt-3 text-3xl">Add a new location</h2>
          <form class="mt-5 grid gap-4" @submit.prevent="createBranch">
            <div class="mcd-grid mcd-grid--2">
              <input v-model="branchForm.name" type="text" class="mcd-input" placeholder="Branch name" />
              <input v-model="branchForm.city" type="text" class="mcd-input" placeholder="City" />
            </div>
            <div class="mcd-grid mcd-grid--2">
              <input v-model="branchForm.code" type="text" class="mcd-input" placeholder="branch-code" />
              <input v-model="branchForm.map_url" type="url" class="mcd-input" placeholder="Map URL" />
            </div>
            <div class="mcd-grid mcd-grid--2">
              <input v-model="branchForm.concurrent_limit" type="number" min="1" max="10" class="mcd-input" placeholder="Concurrent limit" />
              <input v-model="branchForm.max_guests" type="number" min="4" max="200" class="mcd-input" placeholder="Max guests" />
            </div>
            <div class="rounded-3xl bg-amber-50 p-4">
              <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">Supports</p>
              <div class="mt-3 flex flex-wrap gap-4">
                <label class="flex items-center gap-2">
                  <input v-model="branchForm.supports" type="checkbox" value="birthday" />
                  <span>Birthday</span>
                </label>
                <label class="flex items-center gap-2">
                  <input v-model="branchForm.supports" type="checkbox" value="business" />
                  <span>Business</span>
                </label>
                <label class="flex items-center gap-2">
                  <input v-model="branchForm.supports" type="checkbox" value="table" />
                  <span>Table</span>
                </label>
              </div>
            </div>
            <button type="submit" class="mcd-button" :disabled="branchForm.processing">
              {{ branchForm.processing ? 'Adding...' : 'Add branch' }}
            </button>
          </form>

          <div class="mt-6 space-y-3">
            <div v-for="branch in branches" :key="branch.code" class="rounded-2xl bg-white p-4">
              <p class="font-bold">{{ branch.name }}</p>
              <p class="mt-1 text-sm text-slate-500">{{ branch.city }} | {{ branch.code }}</p>
            </div>
          </div>
        </article>
      </div>
    </section>

    <section class="mcd-section">
      <article class="mcd-panel p-6">
        <p class="mcd-chip">Bookings organized by branch and type</p>
        <div class="mt-5 space-y-6">
          <section v-for="group in groupedBookings" :key="group.branch_code" class="rounded-3xl bg-white p-5">
            <div class="flex flex-wrap items-end justify-between gap-3">
              <div>
                <h2 class="text-2xl">{{ group.branch }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ group.city }}</p>
              </div>
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
                        <p class="mt-1 text-sm text-slate-500">Assigned crew: {{ booking.assigned_staff_name || 'Unassigned' }}</p>

                        <div class="mt-4 rounded-2xl bg-white p-4 text-sm">
                          <p class="font-black uppercase tracking-[0.15em] text-red-700">Receipt</p>
                          <div class="mt-3 space-y-2">
                            <div
                              v-for="item in booking.receipt.line_items"
                              :key="`${booking.id}-${item.label}`"
                              class="flex items-center justify-between gap-3"
                            >
                              <span>{{ item.label }}</span>
                              <strong>${{ item.amount }}</strong>
                            </div>
                          </div>
                          <div class="mt-3 space-y-1 border-t border-red-100 pt-3">
                            <div class="flex items-center justify-between">
                              <span>Subtotal</span>
                              <strong>${{ booking.receipt.subtotal }}</strong>
                            </div>
                            <div class="flex items-center justify-between text-slate-600">
                              <span>{{ booking.receipt.pricing_rule }} ({{ booking.receipt.multiplier }}x)</span>
                              <strong>${{ booking.receipt.adjustment }}</strong>
                            </div>
                            <div class="flex items-center justify-between text-red-700">
                              <span>Total</span>
                              <strong>${{ booking.receipt.total }}</strong>
                            </div>
                          </div>
                        </div>
                      </div>

                      <div class="space-y-3">
                        <p class="text-sm font-black uppercase tracking-[0.15em] text-red-700">Confirm reservation</p>
                        <select v-model="statusState[booking.id]" class="mcd-select">
                          <option value="pending_review">pending review</option>
                          <option value="confirmed">confirmed</option>
                          <option value="checked_in">checked in</option>
                          <option value="completed">completed</option>
                          <option value="cancelled">cancelled</option>
                        </select>
                        <button type="button" class="mcd-button" @click="updateStatus(booking.id)">Save status</button>
                      </div>

                      <div class="space-y-3">
                        <p class="text-sm font-black uppercase tracking-[0.15em] text-red-700">Assigned crew</p>
                        <select v-model="crewState[booking.id]" class="mcd-select">
                          <option value="">Unassigned</option>
                          <option v-for="staff in staffUsers" :key="staff.id" :value="staff.id">
                            {{ staff.name }} ({{ staff.role }})
                          </option>
                        </select>
                        <button type="button" class="mcd-button mcd-button--ghost" @click="updateCrew(booking.id)">Update crew</button>
                      </div>
                    </div>
                  </div>
                </div>

                <div v-else class="mcd-empty mt-4">No bookings for this branch and type.</div>
              </article>
            </div>
          </section>
        </div>
      </article>
    </section>

    <section class="mcd-section">
      <div class="mcd-grid mcd-grid--2">
        <article class="mcd-panel p-6">
          <p class="mcd-chip">Account approvals</p>
          <h2 class="mt-3 text-3xl">Promote or change user access</h2>
          <div class="mt-5 space-y-3">
            <div v-for="user in users" :key="user.id" class="rounded-3xl bg-white p-5">
              <div class="grid gap-3 md:grid-cols-[1fr,0.8fr,0.6fr] md:items-center">
                <div>
                  <p class="font-bold">{{ user.name }}</p>
                  <p class="mt-1 text-sm text-slate-500">{{ user.email }}</p>
                </div>
                <select v-model="roleState[user.id]" class="mcd-select">
                  <option value="customer">customer</option>
                  <option value="staff">staff</option>
                  <option value="manager">manager</option>
                  <option value="admin">admin</option>
                </select>
                <button type="button" class="mcd-button" @click="updateRole(user.id)">Approve role</button>
              </div>
            </div>
          </div>
        </article>

        <article class="mcd-panel p-6">
          <p class="mcd-chip">Operations overview</p>
          <div class="mt-5 grid gap-4">
            <div class="rounded-3xl bg-red-50 p-5">
              <p class="text-sm uppercase tracking-[0.2em] text-red-700">Weekend rate</p>
              <p class="mt-2 text-3xl">{{ pricing.weekend_multiplier }}x</p>
            </div>
            <div class="rounded-3xl bg-amber-50 p-5">
              <p class="text-sm uppercase tracking-[0.2em] text-red-700">Holiday rate</p>
              <p class="mt-2 text-3xl">{{ pricing.holiday_multiplier }}x</p>
            </div>
            <div class="rounded-3xl bg-white p-5">
              <p class="text-sm uppercase tracking-[0.2em] text-red-700">Top event types</p>
              <div class="mt-3 space-y-2">
                <div v-for="item in report.top_event_types" :key="item.type" class="flex items-center justify-between">
                  <span>{{ item.type }}</span>
                  <strong>{{ item.count }}</strong>
                </div>
              </div>
            </div>
            <div class="rounded-3xl bg-white p-5">
              <p class="text-sm uppercase tracking-[0.2em] text-red-700">Branch mix</p>
              <div class="mt-3 space-y-2">
                <div v-for="item in report.branch_mix" :key="item.branch" class="flex items-center justify-between">
                  <span>{{ item.branch }}</span>
                  <strong>{{ item.count }}</strong>
                </div>
              </div>
            </div>
            <p class="text-xs text-slate-500">This admin dashboard refreshes live booking and availability data every 15 seconds.</p>
          </div>
        </article>
      </div>
    </section>
  </AppShell>
</template>
