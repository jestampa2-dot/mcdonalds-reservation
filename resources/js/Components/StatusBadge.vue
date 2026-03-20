<script setup>
import { computed } from 'vue'

const props = defineProps({
  value: {
    type: String,
    required: true,
  },
})

const className = computed(() => ({
  'mcd-badge--pending': ['pending_review', 'rescheduled'].includes(props.value),
  'mcd-badge--success': ['confirmed', 'checked_in', 'completed', 'available'].includes(props.value),
  'mcd-badge--warning': ['cleaning', 'in_progress'].includes(props.value),
  'mcd-badge--danger': props.value === 'cancelled',
}))

const label = computed(() => props.value.replaceAll('_', ' '))
</script>

<template>
  <span class="mcd-badge" :class="className">
    {{ label }}
  </span>
</template>
