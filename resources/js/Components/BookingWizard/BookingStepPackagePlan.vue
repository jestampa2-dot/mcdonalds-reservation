<script setup>
defineProps({
  form: {
    type: Object,
    required: true,
  },
  packages: {
    type: Array,
    default: () => [],
  },
  menuBundles: {
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
      <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">2. Package and bundle plan</p>
      <h2 class="mt-3 text-3xl">Pick the core package.</h2>
      <p class="mt-2 text-sm text-slate-500">Set the guest count, choose a package, and add bundle plans for the event.</p>

      <div class="mt-6 mcd-field">
        <label>Guest count</label>
        <input v-model="form.guests" type="number" min="2" max="120" class="mcd-input" />
      </div>

      <div class="mt-6 grid gap-4">
        <label
          v-for="item in packages"
          :key="item.code"
          class="rounded-[1.75rem] border p-5 transition"
          :class="form.package_code === item.code ? 'border-red-500 bg-red-50 shadow-sm' : 'border-slate-200 bg-white hover:border-red-200'"
        >
          <input v-model="form.package_code" type="radio" :value="item.code" class="hidden" />
          <div class="flex items-start justify-between gap-4">
            <div>
              <p class="text-xl text-slate-900">{{ item.name }}</p>
              <p class="mt-1 text-sm text-slate-500">{{ item.guest_range }}</p>
            </div>
            <strong class="text-lg text-red-700">{{ formatCurrency(item.price) }}</strong>
          </div>
          <ul class="mt-4 space-y-2 text-sm text-slate-600">
            <li v-for="feature in item.features" :key="feature">{{ feature }}</li>
          </ul>
        </label>
      </div>
    </section>

    <section class="mcd-panel p-6">
      <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">Bundle plan</p>
      <p class="mt-2 text-sm text-slate-500">Add shareable menu bundles that should be prepared with the package.</p>

      <div class="mt-5 grid gap-3">
        <label
          v-for="item in menuBundles"
          :key="item.code"
          class="flex items-center justify-between gap-4 rounded-[1.6rem] border border-slate-200 bg-white p-4"
        >
          <div>
            <p class="text-lg text-slate-900">{{ item.name }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ item.prep_label }}</p>
          </div>
          <div class="flex items-center gap-3">
            <span class="font-bold text-red-700">{{ formatCurrency(item.price) }}</span>
            <input v-model="form.menu_bundles" :value="item.code" type="checkbox" class="h-5 w-5 rounded border-slate-300 text-red-600" />
          </div>
        </label>
      </div>
    </section>
  </div>
</template>
