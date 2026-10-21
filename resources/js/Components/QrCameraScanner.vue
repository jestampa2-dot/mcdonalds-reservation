<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'
import QrScanner from 'qr-scanner'
import Modal from '@/Components/Modal.vue'

const props = defineProps({
  show: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['close', 'decoded'])

const videoElement = ref(null)
const scanner = ref(null)
const scannerState = ref('idle')
const statusMessage = ref('')
let lifecycleToken = 0

const stopScanner = () => {
  const activeScanner = scanner.value
  scanner.value = null

  if (!activeScanner) {
    return
  }

  activeScanner.stop()
  activeScanner.destroy()
}

const checkInCodeFromQr = (value) => {
  const content = String(value ?? '').trim()

  if (!content) {
    return null
  }

  try {
    const scannedUrl = new URL(content, window.location.origin)
    const urlCode = scannedUrl.searchParams.get('code')?.trim()

    if (urlCode) {
      return urlCode
    }
  } catch {
    // A plain booking reference or check-in code is also a valid QR payload.
  }

  return /^[a-z0-9-]{4,80}$/i.test(content) ? content : null
}

const cameraErrorMessage = (error) => {
  const name = String(error?.name ?? '')
  const message = String(error?.message ?? error ?? '').toLowerCase()

  if (!window.isSecureContext || message.includes('secure context')) {
    return 'Camera access requires HTTPS or a localhost address.'
  }

  if (name === 'NotAllowedError' || message.includes('permission')) {
    return 'Camera permission was blocked. Allow camera access in your browser and try again.'
  }

  if (name === 'NotFoundError' || message.includes('no camera')) {
    return 'No camera was found on this device.'
  }

  if (name === 'NotReadableError' || message.includes('could not start video source')) {
    return 'The camera is being used by another application. Close it there and try again.'
  }

  return 'The camera could not start. Check the browser camera permission and try again.'
}

const handleDecode = (result) => {
  if (scannerState.value !== 'scanning') {
    return
  }

  const code = checkInCodeFromQr(result?.data ?? result)

  if (!code) {
    scannerState.value = 'error'
    statusMessage.value = 'This QR code is not a valid customer check-in pass.'
    stopScanner()
    return
  }

  scannerState.value = 'detected'
  statusMessage.value = 'Customer pass detected. Checking in...'
  stopScanner()
  emit('decoded', code)
}

const startScanner = async () => {
  const startToken = ++lifecycleToken
  stopScanner()
  scannerState.value = 'starting'
  statusMessage.value = 'Starting camera...'

  await nextTick()

  if (!props.show || startToken !== lifecycleToken || !videoElement.value) {
    return
  }

  if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
    scannerState.value = 'error'
    statusMessage.value = cameraErrorMessage(new Error('secure context required'))
    return
  }

  const nextScanner = new QrScanner(videoElement.value, handleDecode, {
    preferredCamera: 'environment',
    maxScansPerSecond: 12,
    highlightScanRegion: true,
    highlightCodeOutline: true,
    returnDetailedScanResult: true,
  })

  scanner.value = nextScanner

  try {
    await nextScanner.start()

    if (!props.show || startToken !== lifecycleToken) {
      nextScanner.destroy()
      return
    }

    scannerState.value = 'scanning'
    statusMessage.value = 'Camera ready. Scan the customer QR pass.'
  } catch (error) {
    if (scanner.value === nextScanner) {
      scanner.value = null
    }

    nextScanner.destroy()
    scannerState.value = 'error'
    statusMessage.value = cameraErrorMessage(error)
  }
}

const closeScanner = () => {
  lifecycleToken += 1
  stopScanner()
  scannerState.value = 'idle'
  statusMessage.value = ''
  emit('close')
}

watch(
  () => props.show,
  (show) => {
    if (show) {
      startScanner()
      return
    }

    lifecycleToken += 1
    stopScanner()
    scannerState.value = 'idle'
    statusMessage.value = ''
  },
)

onBeforeUnmount(() => {
  lifecycleToken += 1
  stopScanner()
})
</script>

<template>
  <Modal :show="show" max-width="lg" @close="closeScanner">
    <section class="qr-camera-dialog" aria-labelledby="qr-camera-title">
      <header class="qr-camera-dialog__header">
        <div>
          <p class="qr-camera-dialog__eyebrow">Staff check-in</p>
          <h2 id="qr-camera-title">Scan customer QR</h2>
        </div>
        <button type="button" class="qr-camera-dialog__close" aria-label="Close camera" @click="closeScanner">
          Close
        </button>
      </header>

      <div class="qr-camera-dialog__viewport" :class="`is-${scannerState}`">
        <video ref="videoElement" playsinline muted />
        <div v-if="scannerState === 'starting'" class="qr-camera-dialog__loading" aria-hidden="true">
          <span />
        </div>
      </div>

      <p class="qr-camera-dialog__status" :class="{ 'is-error': scannerState === 'error' }" role="status" aria-live="polite">
        {{ statusMessage }}
      </p>

      <div v-if="scannerState === 'error'" class="qr-camera-dialog__actions">
        <button type="button" class="mcd-button" @click="startScanner">Try camera again</button>
        <button type="button" class="mcd-button mcd-button--ghost" @click="closeScanner">Use manual code</button>
      </div>
    </section>
  </Modal>
</template>

<style scoped>
.qr-camera-dialog {
  background: #18191b;
  color: #f8fafc;
  padding: 1.5rem;
}

.qr-camera-dialog__header {
  align-items: flex-start;
  display: flex;
  gap: 1rem;
  justify-content: space-between;
}

.qr-camera-dialog__eyebrow {
  color: #ffc72c;
  font-size: 0.75rem;
  font-weight: 900;
  text-transform: uppercase;
}

.qr-camera-dialog h2 {
  color: #f8fafc;
  font-size: 1.75rem;
  font-weight: 900;
  line-height: 1.15;
  margin-top: 0.35rem;
}

.qr-camera-dialog__close {
  border: 1px solid #4b5563;
  border-radius: 9999px;
  color: #f8fafc;
  flex: 0 0 auto;
  font-size: 0.875rem;
  font-weight: 800;
  padding: 0.6rem 0.9rem;
}

.qr-camera-dialog__close:hover {
  background: #2b2d31;
}

.qr-camera-dialog__viewport {
  aspect-ratio: 4 / 3;
  background: #090a0b;
  border: 1px solid #44474d;
  border-radius: 8px;
  margin-top: 1.25rem;
  overflow: hidden;
  position: relative;
  width: 100%;
}

.qr-camera-dialog__viewport video {
  display: block;
  height: 100%;
  object-fit: cover;
  width: 100%;
}

.qr-camera-dialog__viewport.is-error {
  border-color: #ff6b61;
}

.qr-camera-dialog__loading {
  align-items: center;
  background: #090a0b;
  display: flex;
  inset: 0;
  justify-content: center;
  position: absolute;
}

.qr-camera-dialog__loading span {
  animation: qr-camera-spin 0.8s linear infinite;
  border: 3px solid #4b5563;
  border-radius: 50%;
  border-top-color: #ffc72c;
  height: 2.25rem;
  width: 2.25rem;
}

.qr-camera-dialog__status {
  color: #d7dbe0;
  font-size: 0.9rem;
  margin-top: 1rem;
  min-height: 1.4rem;
}

.qr-camera-dialog__status.is-error {
  color: #ff9b91;
}

.qr-camera-dialog__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  margin-top: 1rem;
}

@keyframes qr-camera-spin {
  to {
    transform: rotate(360deg);
  }
}

@media (max-width: 640px) {
  .qr-camera-dialog {
    padding: 1rem;
  }

  .qr-camera-dialog h2 {
    font-size: 1.4rem;
  }

  .qr-camera-dialog__viewport {
    aspect-ratio: 3 / 4;
  }

  .qr-camera-dialog__actions .mcd-button {
    width: 100%;
  }
}
</style>
