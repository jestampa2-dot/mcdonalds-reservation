<script setup>
import StatusBadge from '@/Components/StatusBadge.vue'

defineProps({
  items: {
    type: Array,
    default: () => [],
  },
})
</script>

<template>
  <article class="mcd-panel p-6">
    <p class="mcd-chip">Cancelled events</p>
    <div v-if="items.length" class="mt-5 space-y-4">
      <div v-for="item in items" :key="item.id" class="rounded-3xl bg-white p-5">
        <div class="flex flex-wrap items-center gap-2">
          <strong>{{ item.booking_reference }}</strong>
          <StatusBadge :value="item.status" />
        </div>
        <p class="mt-2 text-sm text-slate-600">{{ item.package_name }} | {{ item.event_type }}</p>
        <p class="mt-1 text-sm text-slate-500">{{ item.branch }} | {{ item.event_date }} | {{ item.event_time }}</p>
        <p class="mt-3 text-sm text-slate-600">Customer: {{ item.customer_name }}</p>
        <p class="mt-1 text-sm text-slate-600">Notes: {{ item.cancelled_note || 'No cancellation note.' }}</p>
      </div>
    </div>
    <div v-else class="mcd-empty mt-5">No cancelled events.</div>
  </article>
</template>
