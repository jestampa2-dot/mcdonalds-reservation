<script setup>
import { computed } from 'vue'

const props = defineProps({
  form: {
    type: Object,
    required: true,
  },
  eventTypes: {
    type: Object,
    default: () => ({}),
  },
})

const typeEntries = computed(() => Object.entries(props.eventTypes ?? {}))
</script>

<template>
  <section class="mcd-panel p-6">
    <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">1. Event type</p>
    <h2 class="mt-3 text-3xl">Choose the kind of reservation.</h2>
    <p class="mt-2 text-sm text-slate-500">Start with the event type so the booking plan, room options, and schedule stay aligned.</p>

    <div class="mt-6 grid gap-4">
      <label
        v-for="[key, type] in typeEntries"
        :key="key"
        class="rounded-[1.75rem] border p-5 transition"
        :class="form.event_type === key ? 'border-red-500 bg-red-50 shadow-sm' : 'border-slate-200 bg-white hover:border-red-200'"
      >
        <input v-model="form.event_type" type="radio" :value="key" class="hidden" />
        <div class="flex items-start justify-between gap-4">
          <div>
            <p class="text-xl text-slate-900">{{ type.label }}</p>
            <p class="mt-2 text-sm leading-6 text-slate-500">{{ type.description }}</p>
          </div>
          <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-black uppercase tracking-[0.16em] text-red-700">
            {{ form.event_type === key ? 'Selected' : 'Available' }}
          </span>
        </div>
      </label>
    </div>
  </section>
</template>
