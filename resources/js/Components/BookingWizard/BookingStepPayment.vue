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
  paymentPreview: {
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
  paymentProofPreviewUrl: {
    type: String,
    default: '',
  },
})
</script>

<template>
  <div class="space-y-5">
    <section class="mcd-panel p-6">
      <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">7. Payment</p>
      <h2 class="mt-3 text-3xl">Upload proof and review the receipt.</h2>
      <p class="mt-2 text-sm text-slate-500">Finish by attaching the proof of payment and confirming the reservation details.</p>

      <fieldset class="mt-6">
        <legend class="text-sm font-black uppercase tracking-[0.15em] text-slate-700">Payment method</legend>
        <div class="mt-3 grid gap-3 md:grid-cols-2">
          <label
            class="cursor-pointer rounded-3xl border p-4 transition"
            :class="form.payment_type === 'down_payment' ? 'border-red-600 bg-red-50' : 'border-slate-200 bg-white'"
          >
            <span class="flex items-start gap-3">
              <input v-model="form.payment_type" type="radio" value="down_payment" class="mt-1" />
              <span>
                <strong class="block text-slate-900">Down payment</strong>
                <span class="mt-1 block text-sm text-slate-500">Pay {{ paymentPreview.percentage }}% now and settle the remaining balance later.</span>
              </span>
            </span>
          </label>
          <label
            class="cursor-pointer rounded-3xl border p-4 transition"
            :class="form.payment_type === 'full_payment' ? 'border-red-600 bg-red-50' : 'border-slate-200 bg-white'"
          >
            <span class="flex items-start gap-3">
              <input v-model="form.payment_type" type="radio" value="full_payment" class="mt-1" />
              <span>
                <strong class="block text-slate-900">Full payment</strong>
                <span class="mt-1 block text-sm text-slate-500">Pay the complete reservation total now.</span>
              </span>
            </span>
          </label>
        </div>
        <p v-if="form.errors.payment_type" class="mt-2 text-sm text-red-700">{{ form.errors.payment_type }}</p>

        <div class="mt-4 grid gap-3 rounded-3xl bg-amber-50 p-4 sm:grid-cols-2">
          <div>
            <p class="text-xs font-black uppercase tracking-[0.15em] text-red-700">Amount to pay now</p>
            <p class="mt-2 text-2xl font-black text-slate-900">{{ formatCurrency(paymentPreview.amountDue) }}</p>
          </div>
          <div>
            <p class="text-xs font-black uppercase tracking-[0.15em] text-red-700">Remaining balance</p>
            <p class="mt-2 text-2xl font-black text-slate-900">{{ formatCurrency(paymentPreview.balance) }}</p>
          </div>
        </div>

        <div v-if="branch?.payment_qr_url || branch?.payment_number" class="mt-5 rounded-3xl border border-amber-200 bg-white p-5">
          <p class="text-sm font-black uppercase tracking-[0.15em] text-red-700">Pay {{ branch?.name }}</p>
          <div class="mt-4 grid items-center gap-5 sm:grid-cols-[220px,1fr]">
            <div v-if="branch?.payment_qr_url" class="rounded-2xl border border-slate-200 bg-white p-3">
              <img :src="branch.payment_qr_url" :alt="`${branch.name} payment QR code`" class="aspect-square w-full rounded-xl object-contain" />
            </div>
            <div>
              <p class="text-sm text-slate-600">Scan the branch QR or use the payment number below, then upload your receipt as proof.</p>
              <div v-if="branch?.payment_number" class="mt-4 rounded-2xl bg-amber-50 p-4">
                <p class="text-xs font-black uppercase tracking-[0.15em] text-red-700">Payment account or mobile number</p>
                <p class="mt-2 break-all text-xl font-black text-slate-900">{{ branch.payment_number }}</p>
              </div>
              <p class="mt-4 text-sm font-bold text-slate-700">Amount to send: {{ formatCurrency(paymentPreview.amountDue) }}</p>
            </div>
          </div>
        </div>
        <div v-else class="mt-5 rounded-3xl border border-amber-200 bg-amber-50 p-4 text-sm font-semibold text-slate-700">
          This branch has not published a payment QR or number yet. Contact the branch before sending payment.
        </div>
      </fieldset>

      <div class="mt-6 mcd-field">
        <label>Upload proof of payment</label>
        <input type="file" accept="image/*" class="mcd-input" @input="form.payment_proof = $event.target.files[0]" />
        <p class="text-xs text-slate-500">JPG, PNG, or another image format up to 4 MB.</p>
        <p v-if="form.errors.payment_proof" class="text-sm text-red-700">{{ form.errors.payment_proof }}</p>

        <div v-if="form.payment_proof" class="booking-payment-proof">
          <img
            v-if="paymentProofPreviewUrl"
            :src="paymentProofPreviewUrl"
            alt="Selected payment proof preview"
          />
          <div>
            <span>Selected image</span>
            <strong>{{ form.payment_proof.name }}</strong>
            <small>{{ (form.payment_proof.size / 1024 / 1024).toFixed(2) }} MB</small>
          </div>
        </div>
      </div>
    </section>

    <section class="mcd-panel mcd-panel--dark p-6">
      <p class="text-sm font-black uppercase tracking-[0.2em] text-amber-300">Booking receipt</p>
      <div class="mt-5 grid gap-6 xl:grid-cols-[0.85fr,1.15fr]">
        <div class="space-y-3 text-sm text-white/80">
          <div class="flex items-center justify-between">
            <span>Event type</span>
            <strong class="text-white">{{ catalog.eventTypes?.[form.event_type]?.label ?? 'Selected event' }}</strong>
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
