<script setup>
const props = defineProps({
  steps: {
    type: Array,
    default: () => [],
  },
  currentStep: {
    type: Number,
    default: 1,
  },
  highestStepVisited: {
    type: Number,
    default: 1,
  },
})

const emit = defineEmits(['go'])

const goTo = (step) => {
  emit('go', step)
}
</script>

<template>
  <nav class="booking-wizard-progress" aria-label="Booking progress">
    <button
      v-for="step in steps"
      :key="step.order"
      type="button"
      class="booking-wizard-progress__step"
      :class="{
        'is-current': step.order === currentStep,
        'is-complete': step.order < currentStep,
      }"
      :disabled="step.order > highestStepVisited"
      :aria-current="step.order === currentStep ? 'step' : undefined"
      @click="goTo(step.order)"
    >
      <span class="booking-wizard-progress__index">{{ step.order }}</span>
      <strong>{{ step.label }}</strong>
    </button>
  </nav>
</template>
