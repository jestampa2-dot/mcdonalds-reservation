<script setup>
defineProps({
  form: {
    type: Object,
    required: true,
  },
  addOns: {
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
  <section class="mcd-panel p-6">
    <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">4. Add-ons</p>
    <h2 class="mt-3 text-3xl">Enhance the event.</h2>
    <p class="mt-2 text-sm text-slate-500">Add services and experience upgrades that should be prepared with the reservation.</p>

    <div class="mt-6 grid gap-3">
      <label
        v-for="item in addOns"
        :key="item.code"
        class="flex items-center justify-between gap-4 rounded-[1.6rem] border border-slate-200 bg-white p-4"
      >
        <div>
          <p class="text-lg text-slate-900">{{ item.name }}</p>
        </div>
        <div class="flex items-center gap-3">
          <span class="font-bold text-red-700">{{ formatCurrency(item.price) }}</span>
          <input v-model="form.add_ons" :value="item.code" type="checkbox" class="h-5 w-5 rounded border-slate-300 text-red-600" />
        </div>
      </label>
    </div>
  </section>
</template>
