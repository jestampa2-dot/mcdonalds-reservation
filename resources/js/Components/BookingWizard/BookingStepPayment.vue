<script setup>
defineProps({
  form: {
    type: Object,
    required: true,
  },
  catalog: {
    type: Object,
    required: true,
  },
  branch: {
    type: Object,
    default: null,
  },
  roomChoices: {
    type: Array,
    default: () => [],
  },
  startTimeLabel: {
    type: String,
    default: '--',
  },
  endTimeLabel: {
    type: String,
    default: '--',
  },
  receiptPreview: {
    type: Object,
    required: true,
  },
  pricingRule: {
    type: Object,
    required: true,
  },
  selectedManualMenuItems: {
    type: Array,
    default: () => [],
  },
  formatCurrency: {
    type: Function,
    required: true,
  },
})
</script>

<template>
  <div class="space-y-5">
    <section class="mcd-panel p-6">
      <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">7. Payment</p>
      <h2 class="mt-3 text-3xl">Upload proof and review the receipt.</h2>
      <p class="mt-2 text-sm text-slate-500">Finish by attaching the proof of payment and confirming the reservation details.</p>

      <div class="mt-6 mcd-field">
        <label>Upload proof of payment</label>
        <input type="file" accept="image/*" class="mcd-input" @input="form.payment_proof = $event.target.files[0]" />
        <p v-if="form.errors.payment_proof" class="text-sm text-red-700">{{ form.errors.payment_proof }}</p>
      </div>
    </section>

    <section class="mcd-panel mcd-panel--dark p-6">
      <p class="text-sm font-black uppercase tracking-[0.2em] text-amber-300">Booking receipt</p>
      <div class="mt-5 grid gap-6 xl:grid-cols-[0.85fr,1.15fr]">
        <div class="space-y-3 text-sm text-white/80">
          <div class="flex items-center justify-between">
            <span>Event type</span>
            <strong class="text-white">{{ catalog.eventTypes[form.event_type].label }}</strong>
          </div>
          <div class="flex items-center justify-between">
            <span>Branch</span>
            <strong class="text-white">{{ branch?.name }}</strong>
          </div>
          <div class="flex items-center justify-between">
            <span>Room rental</span>
            <strong class="text-white">{{ roomChoices.find((item) => item.code === form.room_choice)?.label }}</strong>
          </div>
          <div class="flex items-center justify-between">
            <span>Event time</span>
            <strong class="text-white">{{ startTimeLabel }} to {{ endTimeLabel }}</strong>
          </div>
          <div class="flex items-center justify-between">
            <span>Duration</span>
            <strong class="text-white">{{ form.duration_hours }} hours</strong>
          </div>
          <div class="rounded-3xl bg-white/5 p-4">
            <p class="text-sm uppercase tracking-[0.2em] text-amber-200">Manual tray</p>
            <div v-if="selectedManualMenuItems.length" class="mt-4 space-y-3">
              <div v-for="item in selectedManualMenuItems" :key="item.option_code" class="flex items-center justify-between gap-4">
                <span>{{ item.quantity }} x {{ item.item_name }} ({{ item.option_label }})</span>
                <strong class="text-white">{{ formatCurrency(item.line_total) }}</strong>
              </div>
            </div>
            <p v-else class="mt-3 text-sm text-white/65">No items added.</p>
          </div>
        </div>

        <div class="space-y-3 text-sm text-white/80">
          <div class="rounded-3xl bg-white/5 p-4">
            <p class="text-sm uppercase tracking-[0.2em] text-amber-200">Receipt preview</p>
            <div class="mt-4 space-y-3">
              <div v-for="item in receiptPreview.lineItems" :key="item.label" class="flex items-center justify-between gap-4">
                <span>{{ item.label }}</span>
                <strong class="text-white">{{ formatCurrency(item.amount) }}</strong>
              </div>
            </div>
            <div class="mt-4 space-y-2 border-t border-white/10 pt-4">
              <div class="flex items-center justify-between">
                <span>Subtotal</span>
                <strong class="text-white">{{ formatCurrency(receiptPreview.subtotal) }}</strong>
              </div>
              <div class="flex items-center justify-between">
                <span>{{ pricingRule.label }} ({{ pricingRule.multiplier }}x)</span>
                <strong class="text-white">{{ formatCurrency(receiptPreview.adjustment) }}</strong>
              </div>
            </div>
          </div>
          <div class="border-t border-white/10 pt-4">
            <p class="text-sm uppercase tracking-[0.2em] text-amber-200">Total before confirmation</p>
            <p class="mt-2 text-4xl text-white">{{ formatCurrency(receiptPreview.total) }}</p>
          </div>
        </div>
      </div>
    </section>
  </div>
</template>
