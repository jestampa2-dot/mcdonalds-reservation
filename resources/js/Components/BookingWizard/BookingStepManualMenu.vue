<script setup>
import ManualMenuBoard from '@/Components/ManualMenuBoard.vue'

defineProps({
  form: {
    type: Object,
    required: true,
  },
  categories: {
    type: Array,
    default: () => [],
  },
})
</script>

<template>
  <ManualMenuBoard
    v-if="categories.length"
    v-model="form.manual_menu_items"
    :categories="categories"
    step-label="3. Manual foods and drinks"
    title="Add exact foods and drinks."
    description="Choose the items the customer specifically wants on top of the package and bundle plan."
  />

  <section v-else class="mcd-panel p-6">
    <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">3. Manual foods and drinks</p>
    <p class="mt-3 text-sm text-slate-500">Menu unavailable.</p>
  </section>
</template>
