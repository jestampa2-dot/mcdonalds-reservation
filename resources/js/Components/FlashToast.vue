<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { usePage } from '@inertiajs/vue3'

const page = usePage()
const visible = ref(false)
const timeoutId = ref(null)
const lastVisitMethod = ref('get')
const toast = ref({
  message: '',
  type: 'success',
  label: 'Done',
})

const flash = computed(() => page.props.flash ?? {})
const activeMessage = computed(() => toast.value.message)
const activeType = computed(() => toast.value.type)
const activeLabel = computed(() => toast.value.label)
const isReservationConfirmed = computed(() =>
  activeType.value === 'success' && /reservation is successful|officially confirmed|confirmed/i.test(activeMessage.value),
)

const toastLabelForMessage = (message, type = 'success') => {
  if (type === 'error') {
    return 'Action needed'
  }

  if (/reservation is successful|officially confirmed|confirmed/i.test(message)) {
    return "McDonald's Reservation Ready"
  }

  if (/checked in/i.test(message)) {
    return 'Checked in'
  }

  if (/added/i.test(message)) {
    return 'Added'
  }

  if (/updated|saved|rescheduled|approved/i.test(message)) {
    return 'Saved'
  }

  if (/cancelled/i.test(message)) {
    return 'Cancelled'
  }

  return 'Done'
}

const setToast = ({ message = '', type = 'success', label = toastLabelForMessage(message, type) }) => {
  toast.value = {
    message,
    type,
    label,
  }
}

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

const applyFlashToast = (flashPayload = flash.value) => {
  const errorMessage = flashPayload?.error
  const successMessage = flashPayload?.success
  const nextMessage = errorMessage || successMessage

  if (!nextMessage) {
    return false
  }

  const nextType = errorMessage ? 'error' : 'success'

  setToast({
    message: nextMessage,
    type: nextType,
  })
  showToast()

  return true
}

const handleVisitStart = (event) => {
  lastVisitMethod.value = String(event.detail?.visit?.method ?? 'get').toLowerCase()
}

const handleVisitSuccess = (event) => {
  const pageFlash = event.detail?.page?.props?.flash ?? {}

  if (applyFlashToast(pageFlash)) {
    return
  }

  if (lastVisitMethod.value !== 'get') {
    setToast({
      message: 'Task completed.',
      type: 'success',
    })
    showToast()
  }
}

const handleCustomToast = (event) => {
  setToast(event.detail ?? {})
  showToast()
}

onMounted(() => {
  applyFlashToast()

  document.addEventListener('inertia:start', handleVisitStart)
  document.addEventListener('inertia:success', handleVisitSuccess)
  window.addEventListener('mcd:toast', handleCustomToast)
})

onBeforeUnmount(() => {
  if (timeoutId.value) {
    window.clearTimeout(timeoutId.value)
  }

  document.removeEventListener('inertia:start', handleVisitStart)
  document.removeEventListener('inertia:success', handleVisitSuccess)
  window.removeEventListener('mcd:toast', handleCustomToast)
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
          {{ isReservationConfirmed ? "McDonald's Reservation Ready" : activeLabel }}
        </p>
        <p>{{ activeMessage }}</p>
      </div>
      <button type="button" class="mcd-toast__close" @click="visible = false">Close</button>
    </div>
  </transition>
</template>
