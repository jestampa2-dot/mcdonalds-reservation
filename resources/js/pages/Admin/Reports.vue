<script setup>
import { reactive } from 'vue'
import { router } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import AdminQuickLinks from '@/Components/AdminQuickLinks.vue'

const props = defineProps({
  pricing: Object,
  report: Object,
  completedEvents: Array,
  inventory: Array,
  staffAssignments: Array,
})

const formatCurrency = (value) =>
  `\u20B1${Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`

const refreshReports = () => {
  router.reload({
    only: ['pricing', 'report', 'completedEvents', 'inventory', 'staffAssignments'],
    preserveScroll: true,
    preserveState: true,
  })
}

const inventoryForms = reactive(
  Object.fromEntries(
    props.inventory.flatMap((branch) =>
      (branch.alerts ?? [])
        .filter((item) => item.id)
        .map((item) => [item.id, {
          item: item.item,
          stock: item.stock,
          threshold: item.threshold,
        }]),
    ),
  ),
)

const newInventoryForms = reactive(
  Object.fromEntries(
    props.inventory
      .filter((branch) => branch.branch_id)
      .map((branch) => [branch.branch_id, {
        item: '',
        stock: 0,
        threshold: 0,
      }]),
  ),
)

const saveInventoryItem = (itemId) => {
  router.post(route('admin.inventory-items.update', itemId), inventoryForms[itemId], {
    preserveScroll: true,
    preserveState: true,
  })
}

const addInventoryItem = (branchId) => {
  router.post(route('admin.branches.inventory.store', branchId), newInventoryForms[branchId], {
    preserveScroll: true,
    preserveState: true,
    onSuccess: () => {
      newInventoryForms[branchId].item = ''
      newInventoryForms[branchId].stock = 0
      newInventoryForms[branchId].threshold = 0
    },
  })
}
</script>

<template>
  <AppShell title="Admin Reports">
    <section class="mcd-section">
      <div class="mcd-panel p-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <p class="mcd-chip">Reports</p>
            <h1 class="mt-4 text-4xl">Reports</h1>
          </div>
          <div class="flex flex-wrap gap-3">
            <a :href="route('admin.reports.export', { status: 'completed' })" class="mcd-button">Download completed-event CSV</a>
            <a :href="route('admin.reports.export')" class="mcd-button">Download reservation CSV</a>
            <button type="button" class="mcd-button mcd-button--ghost" @click="refreshReports">Refresh reports</button>
          </div>
        </div>
        <div class="mt-6">
          <AdminQuickLinks current="reports" />
        </div>
      </div>
    </section>

    <section id="completed-event-reports" class="mcd-section scroll-mt-6">
      <article class="mcd-panel p-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <p class="mcd-chip">Completion reports</p>
            <h2 class="mt-4 text-3xl">Completed event billing reports</h2>
            <p class="mt-2 text-sm text-slate-500">A final report appears here automatically when an administrator marks an event as completed.</p>
          </div>
          <div class="rounded-3xl bg-red-700 px-5 py-4 text-white">
            <p class="text-xs font-black uppercase tracking-[0.15em] text-white/75">Completed revenue</p>
            <p class="mt-2 text-2xl font-black">{{ report.completed_revenue }}</p>
            <p class="mt-1 text-sm text-white/75">{{ report.completed_count }} completed event{{ report.completed_count === 1 ? '' : 's' }}</p>
          </div>
        </div>

        <div v-if="completedEvents.length" class="mt-6 grid gap-4 xl:grid-cols-2">
          <article v-for="booking in completedEvents" :key="booking.id" class="rounded-3xl border border-slate-200 bg-white p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <p class="text-lg font-black text-slate-900">{{ booking.booking_reference }}</p>
                <p class="mt-1 text-sm text-slate-500">{{ booking.customer_name }} · {{ booking.branch }}</p>
              </div>
              <strong class="rounded-full bg-red-50 px-4 py-2 text-red-700">{{ formatCurrency(booking.total_amount) }}</strong>
            </div>

            <div class="mt-4 grid gap-2 text-sm text-slate-600 sm:grid-cols-2">
              <p><strong>Event:</strong> {{ booking.package_name }}</p>
              <p><strong>Guests:</strong> {{ booking.guests }}</p>
              <p><strong>Payment:</strong> {{ booking.payment_type_label }}</p>
              <p><strong>Paid/proof amount:</strong> {{ formatCurrency(booking.payment_amount_due) }}</p>
              <p><strong>Balance:</strong> {{ formatCurrency(booking.payment_balance) }}</p>
              <p><strong>Schedule:</strong> {{ booking.event_date }} · {{ booking.event_time }}</p>
              <p><strong>Completed:</strong> {{ booking.completed_at || 'Completion time recorded' }}</p>
              <p><strong>Venue:</strong> {{ booking.venue_name || booking.branch }}</p>
              <p><strong>Crew:</strong> {{ booking.assigned_staff_name || 'Unassigned' }}</p>
            </div>

            <div class="mt-4 rounded-2xl bg-amber-50 p-4">
              <p class="text-xs font-black uppercase tracking-[0.15em] text-red-700">Final bill breakdown</p>
              <div class="mt-3 space-y-2 text-sm">
                <div v-for="line in booking.receipt.line_items" :key="`${booking.id}-${line.label}`" class="flex items-start justify-between gap-3">
                  <span class="text-slate-600">{{ line.label }}</span>
                  <strong>₱{{ line.amount }}</strong>
                </div>
                <div class="flex items-center justify-between gap-3 border-t border-amber-200 pt-3 text-base">
                  <strong>Final total</strong>
                  <strong class="text-red-700">{{ formatCurrency(booking.total_amount) }}</strong>
                </div>
              </div>
            </div>
          </article>
        </div>
        <div v-else class="mcd-empty mt-6">No completed-event reports yet.</div>
      </article>
    </section>

    <section class="mcd-section">
      <div class="mcd-grid mcd-grid--2">
        <article class="mcd-panel p-6">
          <p class="mcd-chip">Pricing</p>
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
              <p class="text-sm uppercase tracking-[0.2em] text-red-700">Extension hourly rate</p>
              <p class="mt-2 text-3xl">{{ formatCurrency(pricing.extension_hourly_rate) }}</p>
            </div>
          </div>
        </article>

        <article class="mcd-panel p-6">
          <p class="mcd-chip">Analytics</p>
          <div class="mt-5 space-y-4">
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
          </div>
        </article>
      </div>
    </section>

    <section class="mcd-section">
      <div class="mcd-grid mcd-grid--2">
        <article class="mcd-panel p-6">
          <p class="mcd-chip">Inventory</p>
          <div class="mt-5 space-y-4">
            <div v-for="branch in inventory" :key="branch.branch" class="rounded-3xl bg-white p-5">
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <p class="font-bold">{{ branch.branch }}</p>
                  <p class="mt-1 text-xs font-bold uppercase tracking-[0.14em] text-slate-400">Database-backed inventory</p>
                </div>
                <span class="rounded-full bg-amber-50 px-3 py-2 text-xs font-black uppercase tracking-[0.12em] text-amber-700">
                  {{ branch.alerts.length }} item{{ branch.alerts.length === 1 ? '' : 's' }}
                </span>
              </div>

              <div class="mt-4 space-y-3">
                <div
                  v-for="item in branch.alerts"
                  :key="item.id ?? `${branch.branch}-${item.item}`"
                  class="rounded-2xl border border-slate-200 bg-slate-50/70 p-4"
                >
                  <div class="grid gap-3 xl:grid-cols-[1.2fr,0.55fr,0.55fr,auto] xl:items-end">
                    <div class="mcd-field">
                      <label>Item name</label>
                      <input
                        v-if="item.id"
                        v-model="inventoryForms[item.id].item"
                        type="text"
                        class="mcd-input"
                      />
                      <input
                        v-else
                        :value="item.item"
                        type="text"
                        class="mcd-input"
                        readonly
                      />
                    </div>
                    <div class="mcd-field">
                      <label>Stock</label>
                      <input
                        v-if="item.id"
                        v-model="inventoryForms[item.id].stock"
                        type="number"
                        min="0"
                        class="mcd-input"
                      />
                      <input
                        v-else
                        :value="item.stock"
                        type="number"
                        class="mcd-input"
                        readonly
                      />
                    </div>
                    <div class="mcd-field">
                      <label>Low-stock threshold</label>
                      <input
                        v-if="item.id"
                        v-model="inventoryForms[item.id].threshold"
                        type="number"
                        min="0"
                        class="mcd-input"
                      />
                      <input
                        v-else
                        :value="item.threshold"
                        type="number"
                        class="mcd-input"
                        readonly
                      />
                    </div>
                    <button
                      v-if="item.id"
                      type="button"
                      class="mcd-button"
                      @click="saveInventoryItem(item.id)"
                    >
                      Save
                    </button>
                  </div>

                  <div class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-2xl bg-white px-4 py-3">
                    <span class="text-sm text-slate-500">Projected stock after upcoming events</span>
                    <strong :class="item.low ? 'text-red-700' : 'text-slate-700'">{{ item.projected }} projected</strong>
                  </div>
                </div>

                <div v-if="branch.branch_id" class="rounded-2xl border border-dashed border-amber-300 bg-amber-50 p-4">
                  <p class="text-sm font-black uppercase tracking-[0.14em] text-red-700">Add inventory item</p>
                  <div class="mt-3 grid gap-3 xl:grid-cols-[1.2fr,0.55fr,0.55fr,auto] xl:items-end">
                    <div class="mcd-field">
                      <label>Item name</label>
                      <input v-model="newInventoryForms[branch.branch_id].item" type="text" class="mcd-input" />
                    </div>
                    <div class="mcd-field">
                      <label>Stock</label>
                      <input v-model="newInventoryForms[branch.branch_id].stock" type="number" min="0" class="mcd-input" />
                    </div>
                    <div class="mcd-field">
                      <label>Threshold</label>
                      <input v-model="newInventoryForms[branch.branch_id].threshold" type="number" min="0" class="mcd-input" />
                    </div>
                    <button type="button" class="mcd-button" @click="addInventoryItem(branch.branch_id)">
                      Add item
                    </button>
                  </div>
                </div>

                <div v-else class="rounded-2xl bg-slate-100 px-4 py-3 text-sm text-slate-500">Inventory unavailable.</div>
              </div>
            </div>
          </div>
        </article>

        <article class="mcd-panel p-6">
          <p class="mcd-chip">Staff assignments</p>
          <div class="mt-5 space-y-3">
            <div v-for="item in staffAssignments" :key="item.booking_reference" class="rounded-3xl bg-white p-5">
              <p class="font-bold">{{ item.booking_reference }}</p>
              <p class="mt-1 text-sm text-slate-500">{{ item.branch }} | {{ item.slot }}</p>
              <p class="mt-2 text-sm text-slate-600">{{ item.event_type }} | Host: {{ item.host }}</p>
            </div>
          </div>
        </article>
      </div>
    </section>
  </AppShell>
</template>
