<script setup>
import { computed, ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import AdminQuickLinks from '@/Components/AdminQuickLinks.vue'

const props = defineProps({
  availability: Object,
  initialBranch: String,
  initialMonth: String,
})

const branches = computed(() => props.availability?.branches ?? [])
const selectedBranch = ref(props.initialBranch || branches.value[0]?.code || '')
const selectedMonth = ref(props.initialMonth || '')

const selectedBranchData = computed(() =>
  branches.value.find((branch) => branch.code === selectedBranch.value) ?? branches.value[0] ?? null,
)

const availableMonths = computed(() => {
  const dates = selectedBranchData.value?.dates ?? []

  return [...new Set(dates.map((item) => item.date.slice(0, 7)))].sort()
})

watch(
  selectedBranchData,
  (branch) => {
    if (!branch) {
      return
    }

    if (!selectedBranch.value) {
      selectedBranch.value = branch.code
    }
  },
  { immediate: true },
)

watch(
  availableMonths,
  (months) => {
    if (!months.length) {
      selectedMonth.value = ''
      return
    }

    if (!months.includes(selectedMonth.value)) {
      selectedMonth.value = months[0]
    }
  },
  { immediate: true },
)

const monthIndex = computed(() => availableMonths.value.indexOf(selectedMonth.value))

const monthLabel = computed(() => {
  if (!selectedMonth.value) {
    return 'Availability calendar'
  }

  const [year, month] = selectedMonth.value.split('-').map(Number)

  return new Intl.DateTimeFormat('en-US', {
    month: 'long',
    year: 'numeric',
  }).format(new Date(year, month - 1, 1))
})

const monthSummary = computed(() => {
  const dates = (selectedBranchData.value?.dates ?? []).filter((item) => item.date.startsWith(selectedMonth.value))

  return {
    available: dates.filter((item) => item.status === 'available').length,
    limited: dates.filter((item) => item.status === 'limited').length,
    full: dates.filter((item) => item.status === 'full').length,
  }
})

const calendarCells = computed(() => {
  if (!selectedBranchData.value || !selectedMonth.value) {
    return []
  }

  const [year, month] = selectedMonth.value.split('-').map(Number)
  const firstDay = new Date(year, month - 1, 1)
  const daysInMonth = new Date(year, month, 0).getDate()
  const dateLookup = Object.fromEntries(
    (selectedBranchData.value.dates ?? [])
      .filter((item) => item.date.startsWith(selectedMonth.value))
      .map((item) => [item.date, item]),
  )

  const cells = []

  for (let blank = 0; blank < firstDay.getDay(); blank += 1) {
    cells.push({ kind: 'blank', key: `blank-${blank}` })
  }

  for (let day = 1; day <= daysInMonth; day += 1) {
    const isoDate = `${selectedMonth.value}-${String(day).padStart(2, '0')}`
    cells.push({
      kind: 'date',
      key: isoDate,
      date: isoDate,
      day,
      item: dateLookup[isoDate] ?? null,
    })
  }

  return cells
})

const previousMonth = () => {
  if (monthIndex.value > 0) {
    selectedMonth.value = availableMonths.value[monthIndex.value - 1]
  }
}

const nextMonth = () => {
  if (monthIndex.value < availableMonths.value.length - 1) {
    selectedMonth.value = availableMonths.value[monthIndex.value + 1]
  }
}

const refreshAvailability = () => {
  router.get(route('admin.availability'), {
    branch: selectedBranch.value,
    month: selectedMonth.value,
  }, {
    preserveScroll: true,
    preserveState: false,
    replace: true,
  })
}

const dayLink = (date) => route('admin.availability.day', {
  branchCode: selectedBranch.value,
  date,
  branch: selectedBranch.value,
  month: selectedMonth.value,
})

const statusClasses = {
  available: 'border-emerald-200 bg-emerald-50 text-emerald-700',
  limited: 'border-amber-200 bg-amber-50 text-amber-700',
  full: 'border-rose-200 bg-rose-50 text-rose-700',
}
</script>

<template>
  <AppShell title="Admin Availability Calendar">
    <section class="mcd-section">
      <div class="mcd-panel p-8">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <p class="mcd-chip">Availability</p>
            <h1 class="mt-4 text-4xl">Availability calendar</h1>
            <p class="mt-2 text-sm text-slate-500">Choose a branch, then click a date to see open times and rooms for that day.</p>
          </div>
          <button type="button" class="mcd-button mcd-button--ghost" @click="refreshAvailability">Refresh calendar</button>
        </div>
        <div class="mt-6">
          <AdminQuickLinks current="availability" />
        </div>
      </div>
    </section>

    <section class="mcd-section">
      <div class="grid gap-6 xl:grid-cols-[0.35fr,1fr]">
        <article class="mcd-panel p-6">
          <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">Branches</p>
          <div class="mt-4 grid gap-3">
            <button
              v-for="branch in branches"
              :key="branch.code"
              type="button"
              class="rounded-3xl border px-4 py-4 text-left transition"
              :class="selectedBranch === branch.code ? 'border-red-500 bg-red-50 shadow-[0_18px_40px_rgba(220,38,38,0.14)]' : 'border-slate-200 bg-white hover:border-amber-300 hover:bg-amber-50'"
              @click="selectedBranch = branch.code"
            >
              <p class="font-bold text-slate-900">{{ branch.name }}</p>
              <p class="mt-1 text-sm text-slate-500">{{ branch.city }}</p>
            </button>
          </div>
        </article>

        <article class="mcd-panel p-6">
          <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
              <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">{{ selectedBranchData?.name || 'Branch' }}</p>
              <h2 class="mt-2 text-3xl">{{ monthLabel }}</h2>
            </div>

            <div class="flex items-center gap-3">
              <button type="button" class="mcd-button mcd-button--ghost px-5" :disabled="monthIndex <= 0" @click="previousMonth">&lt;</button>
              <button type="button" class="mcd-button mcd-button--ghost px-5" :disabled="monthIndex >= availableMonths.length - 1" @click="nextMonth">&gt;</button>
            </div>
          </div>

          <div class="mt-6 grid gap-3 md:grid-cols-3">
            <div class="rounded-3xl border border-emerald-200 bg-emerald-50 px-4 py-4">
              <p class="text-xs font-black uppercase tracking-[0.18em] text-emerald-700">Open days</p>
              <p class="mt-3 text-3xl font-black text-slate-900">{{ monthSummary.available }}</p>
            </div>
            <div class="rounded-3xl border border-amber-200 bg-amber-50 px-4 py-4">
              <p class="text-xs font-black uppercase tracking-[0.18em] text-amber-700">Limited days</p>
              <p class="mt-3 text-3xl font-black text-slate-900">{{ monthSummary.limited }}</p>
            </div>
            <div class="rounded-3xl border border-rose-200 bg-rose-50 px-4 py-4">
              <p class="text-xs font-black uppercase tracking-[0.18em] text-rose-700">Fully booked</p>
              <p class="mt-3 text-3xl font-black text-slate-900">{{ monthSummary.full }}</p>
            </div>
          </div>

          <div class="mt-8 grid grid-cols-7 gap-3 text-center text-xs font-black uppercase tracking-[0.2em] text-slate-400">
            <p>Sun</p>
            <p>Mon</p>
            <p>Tue</p>
            <p>Wed</p>
            <p>Thu</p>
            <p>Fri</p>
            <p>Sat</p>
          </div>

          <div class="mt-4 grid grid-cols-7 gap-3">
            <template v-for="cell in calendarCells" :key="cell.key">
              <div v-if="cell.kind === 'blank'" class="min-h-[8.5rem] rounded-3xl border border-transparent" />

              <Link
                v-else-if="cell.item"
                :href="dayLink(cell.date)"
                class="group flex min-h-[8.5rem] flex-col rounded-3xl border p-4 text-left transition hover:-translate-y-1 hover:shadow-[0_18px_40px_rgba(15,23,42,0.12)]"
                :class="statusClasses[cell.item.status]"
              >
                <div class="flex items-start justify-between gap-2">
                  <span class="text-2xl font-black leading-none">{{ cell.day }}</span>
                  <span class="rounded-full bg-white/80 px-2 py-1 text-[0.65rem] font-black uppercase tracking-[0.18em]">
                    {{ cell.item.status }}
                  </span>
                </div>
                <p class="mt-4 text-sm font-semibold text-slate-700">{{ cell.item.available_slots }} open start windows</p>
                <p class="mt-auto pt-4 text-xs font-semibold text-slate-500 group-hover:text-slate-700">Open daily view</p>
              </Link>

              <div
                v-else
                class="flex min-h-[8.5rem] flex-col rounded-3xl border border-slate-200 bg-slate-50 p-4 text-left text-slate-300"
              >
                <span class="text-2xl font-black leading-none">{{ cell.day }}</span>
                <p class="mt-auto pt-4 text-xs font-semibold uppercase tracking-[0.18em]">Unavailable</p>
              </div>
            </template>
          </div>
        </article>
      </div>
    </section>
  </AppShell>
</template>
