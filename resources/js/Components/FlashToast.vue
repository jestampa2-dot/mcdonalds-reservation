<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import { usePage } from '@inertiajs/vue3'

const page = usePage()
const visible = ref(false)
const timeoutId = ref(null)

const flash = computed(() => page.props.flash ?? {})
const activeMessage = computed(() => flash.value.success || flash.value.error || '')
const activeType = computed(() => (flash.value.error ? 'error' : 'success'))
const isReservationConfirmed = computed(() =>
  activeType.value === 'success' && /reservation is successful|officially confirmed|confirmed/i.test(activeMessage.value),
)

const showToast = () => {
  if (!activeMessage.value) {
    visible.value = false
    return
  }

  visible.value = true

  if (timeoutId.value) {
    window.clearTimeout(timeoutId.value)
  }

  timeoutId.value = window.setTimeout(() => {
    visible.value = false
  }, 3500)
}

watch(activeMessage, () => {
  showToast()
}, { immediate: true })

onBeforeUnmount(() => {
  if (timeoutId.value) {
    window.clearTimeout(timeoutId.value)
  }
})
</script>

<template>
  <transition name="mcd-toast">
    <div
      v-if="visible && activeMessage"
      class="mcd-toast"
      :class="activeType === 'error' ? 'mcd-toast--error' : 'mcd-toast--success'"
      role="status"
      aria-live="polite"
    >
      <div class="mcd-toast__icon">
        {{ activeType === 'error' ? '!' : isReservationConfirmed ? 'M' : 'OK' }}
      </div>
      <div class="mcd-toast__body">
        <p class="mcd-toast__label">
          {{ activeType === 'error' ? 'Action needed' : isReservationConfirmed ? 'McDonald\'s Reservation Ready' : 'Confirmed' }}
        </p>
        <p>{{ activeMessage }}</p>
      </div>
      <button type="button" class="mcd-toast__close" @click="visible = false">Close</button>
    </div>
  </transition>
</template>
