<script setup>
defineProps({
  form: {
    type: Object,
    required: true,
  },
  supportedBranches: {
    type: Array,
    default: () => [],
  },
  roomChoices: {
    type: Array,
    default: () => [],
  },
  branch: {
    type: Object,
    default: null,
  },
  bookingWindowLabel: {
    type: String,
    default: '',
  },
  refreshAvailability: {
    type: Function,
    required: true,
  },
  plannerMode: {
    type: String,
    default: 'date',
  },
  activeTimeField: {
    type: String,
    default: 'start',
  },
  showPreviousMonth: {
    type: Boolean,
    default: false,
  },
  showNextMonth: {
    type: Boolean,
    default: false,
  },
  goToPreviousMonth: {
    type: Function,
    required: true,
  },
  goToNextMonth: {
    type: Function,
    required: true,
  },
  calendarMonthLabel: {
    type: String,
    default: '',
  },
  calendarCells: {
    type: Array,
    default: () => [],
  },
  selectCalendarDate: {
    type: Function,
    required: true,
  },
  setPlannerMode: {
    type: Function,
    required: true,
  },
  plannerTimeOptions: {
    type: Array,
    default: () => [],
  },
  selectPlannerTime: {
    type: Function,
    required: true,
  },
  startTimeLabel: {
    type: String,
    default: '--',
  },
  startDateLabel: {
    type: String,
    default: 'Select date',
  },
  endTimeLabel: {
    type: String,
    default: '--',
  },
  durationLabel: {
    type: String,
    default: '--',
  },
  availabilityNotice: {
    type: String,
    default: '',
  },
  selectableStartSlotOptions: {
    type: Array,
    default: () => [],
  },
  selectableEndSlotOptions: {
    type: Array,
    default: () => [],
  },
})
</script>

<template>
  <div class="space-y-5">
    <section class="mcd-panel p-6">
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">6. Date, time, and branch</p>
          <h2 class="mt-3 text-3xl">Set the schedule and location.</h2>
          <p class="mt-2 text-sm text-slate-500">Choose the branch, room, and exact schedule for the event.</p>
        </div>
        <div class="rounded-3xl bg-red-50 px-5 py-4 text-sm font-semibold text-red-800">
          Hours: {{ bookingWindowLabel }}
        </div>
      </div>

      <div class="mt-6 grid gap-4 xl:grid-cols-2">
        <div class="mcd-field">
          <label>Branch</label>
          <select v-model="form.branch_code" class="mcd-select">
            <option v-for="item in supportedBranches" :key="item.code" :value="item.code">
              {{ item.name }} | {{ item.city }}
            </option>
          </select>
          <p v-if="form.errors.branch_code" class="text-sm text-red-700">{{ form.errors.branch_code }}</p>
        </div>

        <div class="mcd-field">
          <label>Room rental</label>
          <div class="grid gap-3 md:grid-cols-3">
            <label
              v-for="item in roomChoices"
              :key="item.code"
              class="rounded-[1.6rem] border p-4 transition"
              :class="form.room_choice === item.code ? 'border-red-500 bg-red-50 shadow-sm' : 'border-slate-200 bg-white hover:border-red-200'"
            >
              <input v-model="form.room_choice" type="radio" :value="item.code" class="hidden" />
              <p class="text-base font-black text-slate-800">{{ item.label }}</p>
              <p class="mt-2 text-sm text-slate-500">{{ item.description }}</p>
            </label>
          </div>
          <p v-if="form.errors.room_choice" class="text-sm text-red-700">{{ form.errors.room_choice }}</p>
        </div>
      </div>

      <div class="mt-6 rounded-[2rem] bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">Reservation schedule</p>
            <p class="mt-2 text-sm text-slate-500">Pick the date first, then choose the start and end times.</p>
          </div>
          <button type="button" class="mcd-button mcd-button--ghost" @click="refreshAvailability">Refresh now</button>
        </div>

        <div class="mcd-schedule-planner mt-6">
          <div class="mcd-schedule-planner__surface">
            <div class="mcd-schedule-planner__orb">
              <div class="flex items-center justify-between gap-3">
                <button type="button" class="mcd-schedule-planner__month-button" :disabled="!showPreviousMonth" @click="goToPreviousMonth">
                  &lt;
                </button>
                <p class="text-base font-black tracking-[0.03em] text-slate-700">{{ calendarMonthLabel }}</p>
                <button type="button" class="mcd-schedule-planner__month-button" :disabled="!showNextMonth" @click="goToNextMonth">
                  &gt;
                </button>
              </div>

              <div v-if="plannerMode === 'date'">
                <div class="mt-5 grid grid-cols-7 gap-2 text-center text-[11px] font-black uppercase tracking-[0.12em] text-slate-400">
                  <span v-for="day in ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa']" :key="day">{{ day }}</span>
                </div>

                <div class="mt-3 grid grid-cols-7 gap-2">
                  <button
                    v-for="cell in calendarCells"
                    :key="cell.key"
                    type="button"
                    class="mcd-schedule-planner__day"
                    :class="{
                      'is-empty': cell.empty,
                      'is-selected': cell.selected,
                      'is-unavailable': cell.unavailable,
                      'is-limited': cell.limited,
                    }"
                    :disabled="cell.empty || cell.unavailable"
                    @click="selectCalendarDate(cell)"
                  >
                    <span v-if="!cell.empty">{{ cell.day }}</span>
                  </button>
                </div>
              </div>

              <div v-else class="mcd-schedule-planner__time-panel">
                <p class="mcd-schedule-planner__time-title">
                  {{ activeTimeField === 'start' ? 'Start Time' : 'End Time' }}
                </p>

                <div class="mcd-schedule-planner__time-grid">
                  <button
                    v-for="option in plannerTimeOptions"
                    :key="option.value"
                    type="button"
                    class="mcd-schedule-planner__time-option"
                    :class="{ 'is-selected': activeTimeField === 'start' ? form.event_time === option.value : form.event_end_time === option.value }"
                    @click="selectPlannerTime(option.value)"
                  >
                    <strong>{{ option.label ?? option.value }}</strong>
                    <small>
                      {{ activeTimeField === 'start' ? 'Start of event' : (option.duration ? `${option.duration} hours total` : 'End of event') }}
                    </small>
                  </button>
                </div>

                <p v-if="!plannerTimeOptions.length" class="rounded-2xl bg-slate-100 px-4 py-3 text-sm font-semibold text-slate-500">
                  No available {{ activeTimeField === 'start' ? 'start' : 'end' }} times for this selection.
                </p>
              </div>

              <div class="mcd-schedule-planner__switches">
                <button
                  type="button"
                  class="mcd-schedule-planner__switch"
                  :class="{ 'is-active': plannerMode === 'date' }"
                  @click="setPlannerMode('date', 'start')"
                >
                  <span class="mcd-schedule-planner__switch-box"></span>
                  Start Date
                </button>
                <button
                  type="button"
                  class="mcd-schedule-planner__switch"
                  :class="{ 'is-active': plannerMode === 'time' }"
                  @click="setPlannerMode('time', activeTimeField)"
                >
                  <span class="mcd-schedule-planner__switch-box"></span>
                  Time
                </button>
              </div>

              <div class="mcd-schedule-planner__time-cards">
                <button type="button" class="mcd-schedule-planner__time-card" :class="{ 'is-active': plannerMode === 'time' && activeTimeField === 'start' }" @click="setPlannerMode('time', 'start')">
                  <span>Start Time</span>
                  <strong>{{ startTimeLabel }}</strong>
                  <small>{{ startDateLabel }}</small>
                </button>
                <button type="button" class="mcd-schedule-planner__time-card" :class="{ 'is-active': plannerMode === 'time' && activeTimeField === 'end' }" @click="setPlannerMode('time', 'end')">
                  <span>Due Time</span>
                  <strong>{{ endTimeLabel }}</strong>
                  <small>{{ durationLabel }}</small>
                </button>
              </div>
            </div>

            <div class="mcd-schedule-planner__details">
              <div v-if="availabilityNotice" class="rounded-2xl bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                {{ availabilityNotice }}
              </div>

              <div class="mcd-schedule-planner__fields">
                <div class="grid gap-2">
                  <label class="text-base font-bold text-slate-700">Event Date <span class="text-red-500">*</span></label>
                  <input v-model="form.event_date" type="date" class="mcd-schedule-planner__input" />
                  <p v-if="form.errors.event_date" class="text-sm text-red-700">{{ form.errors.event_date }}</p>
                </div>

                <div class="grid gap-2">
                  <label class="text-base font-bold text-slate-700">Start Time <span class="text-red-500">*</span></label>
                  <select v-model="form.event_time" class="mcd-schedule-planner__input">
                    <option v-if="!selectableStartSlotOptions.length" value="" disabled>No start times available</option>
                    <option v-for="slot in selectableStartSlotOptions" :key="slot.value" :value="slot.value">
                      {{ slot.label }}
                    </option>
                  </select>
                  <p v-if="form.errors.event_time" class="text-sm text-red-700">{{ form.errors.event_time }}</p>
                </div>

                <div class="grid gap-2">
                  <label class="text-base font-bold text-slate-700">End Time <span class="text-red-500">*</span></label>
                  <select v-model="form.event_end_time" class="mcd-schedule-planner__input">
                    <option v-if="!selectableEndSlotOptions.length" value="" disabled>No end times available</option>
                    <option v-for="slot in selectableEndSlotOptions" :key="slot.value" :value="slot.value">
                      {{ slot.label }}
                    </option>
                  </select>
                  <p v-if="form.errors.event_end_time" class="text-sm text-red-700">{{ form.errors.event_end_time }}</p>
                </div>

                <div class="grid gap-2">
                  <label class="text-base font-bold text-slate-700">Duration</label>
                  <div class="mcd-schedule-planner__input flex items-center justify-between">
                    <span class="font-semibold text-slate-500">Reserved span</span>
                    <strong class="text-base font-black text-slate-800">{{ durationLabel }}</strong>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section v-if="branch" class="mcd-panel p-6">
      <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
          <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">Selected branch</p>
          <h3 class="mt-3 text-2xl">{{ branch.name }}</h3>
          <p class="mt-1 text-sm text-slate-500">{{ branch.city }} | Up to {{ branch.max_guests }} guests</p>
        </div>
        <a :href="branch.map_url" target="_blank" rel="noreferrer" class="mcd-button mcd-button--ghost">Open map</a>
      </div>
    </section>
  </div>
</template>
