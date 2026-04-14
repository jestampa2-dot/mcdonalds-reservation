<script setup>
import { computed } from 'vue'

const props = defineProps({
  steps: {
    type: Array,
    default: () => [],
  },
  currentStep: {
    type: Number,
    default: 1,
  },
})

const emit = defineEmits(['go'])

const nextStep = computed(() => props.steps.find((step) => step.order === props.currentStep + 1) ?? null)

const goTo = (step) => {
  emit('go', step)
}
</script>

<template>
  <aside class="mcd-panel p-6 mcd-booking-wizard__rail">
    <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">Booking flow</p>
    <h2 class="mt-3 text-3xl">Reserve in seven steps.</h2>
    <p class="mt-2 text-sm text-slate-500">Move one section at a time and finish everything in the payment step.</p>

    <div class="mt-6 space-y-3">
      <button
        v-for="step in steps"
        :key="step.order"
        type="button"
        class="mcd-booking-wizard__step"
        :class="{
          'is-current': step.order === currentStep,
          'is-complete': step.order < currentStep,
        }"
        @click="goTo(step.order)"
      >
        <span class="mcd-booking-wizard__step-index">{{ step.order }}</span>
        <span class="min-w-0 flex-1">
          <strong>{{ step.label }}</strong>
          <small>{{ step.hint }}</small>
        </span>
        <span class="mcd-booking-wizard__step-state">
          {{ step.order < currentStep ? 'Done' : (step.order === currentStep ? 'Now' : 'Next') }}
        </span>
      </button>
    </div>

    <div class="mcd-booking-wizard__next-card">
      <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">What's next</p>
      <p class="mt-3 text-lg font-black text-slate-900">
        {{ nextStep ? nextStep.label : 'Payment review and confirmation' }}
      </p>
      <p class="mt-2 text-sm text-slate-500">
        {{ nextStep ? nextStep.hint : 'Upload proof of payment, review the receipt, and confirm the reservation.' }}
      </p>
    </div>
  </aside>
</template>
