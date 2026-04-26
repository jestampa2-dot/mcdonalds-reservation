<script setup>
import { ref } from 'vue'
import StatusBadge from '@/Components/StatusBadge.vue'
import SearchToolbar from '@/features/search/components/SearchToolbar.vue'
import { useSearchCollection } from '@/features/search/composables/useSearchCollection'

const props = defineProps({
  items: {
    type: Array,
    default: () => [],
  },
  chipLabel: {
    type: String,
    default: 'Event history',
  },
  title: {
    type: String,
    default: '',
  },
  titleClass: {
    type: String,
    default: 'text-2xl',
  },
  searchPlaceholder: {
    type: String,
    default: 'Search history by booking reference, branch, or date',
  },
  emptyMessage: {
    type: String,
    default: 'No history.',
  },
  emptySearchMessage: {
    type: String,
    default: 'No history matched that search.',
  },
  showHeaderBadge: {
    type: Boolean,
    default: false,
  },
  headerBadgeClass: {
    type: String,
    default: 'mcd-badge--success',
  },
  listClass: {
    type: String,
    default: 'space-y-4',
  },
  cardClass: {
    type: String,
    default: 'rounded-3xl bg-white p-5',
  },
})

const searchQuery = ref('')

const filteredItems = useSearchCollection(
  () => props.items,
  searchQuery,
  [
    'booking_reference',
    'package_name',
    'branch',
    'event_type',
    'event_date',
    'event_time',
    'status',
    'service_status',
    'assigned_staff_name',
    'checked_in_by',
  ],
)
</script>

<template>
  <article class="mcd-panel p-6">
    <div v-if="title || showHeaderBadge" class="flex items-center justify-between gap-3">
      <div>
        <p class="mcd-chip">{{ chipLabel }}</p>
        <h2 v-if="title" class="mt-3" :class="titleClass">{{ title }}</h2>
      </div>
      <span v-if="showHeaderBadge" class="mcd-badge" :class="headerBadgeClass">{{ filteredItems.length }} records</span>
    </div>
    <p v-else class="mcd-chip">{{ chipLabel }}</p>

    <div :class="title || showHeaderBadge ? 'mt-4' : 'mt-5'">
      <SearchToolbar
        v-model="searchQuery"
        :count="filteredItems.length"
        singular-label="record"
        :placeholder="searchPlaceholder"
        :show-count-text="!showHeaderBadge"
      />
    </div>

    <div v-if="filteredItems.length" class="mt-5" :class="listClass">
      <div v-for="item in filteredItems" :key="item.id" :class="cardClass">
        <div class="flex flex-wrap items-center gap-2">
          <strong>{{ item.booking_reference }}</strong>
          <StatusBadge :value="item.status" />
          <StatusBadge :value="item.service_status" />
        </div>
        <p class="mt-2 text-sm text-slate-600">{{ item.package_name }} | {{ item.event_type }}</p>
        <p class="mt-1 text-sm text-slate-500">{{ item.branch }} | {{ item.event_date }} | {{ item.event_time }}</p>
        <p class="mt-2 text-xs text-slate-500">Checked in by: {{ item.checked_in_by || 'No check-in recorded' }}</p>
      </div>
    </div>

    <div v-else class="mcd-empty mt-5">
      {{ items.length ? emptySearchMessage : emptyMessage }}
    </div>
  </article>
</template>
