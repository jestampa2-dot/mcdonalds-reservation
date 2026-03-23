<script setup>
import axios from 'axios'
import { onBeforeUnmount, onMounted, ref } from 'vue'
import AppShell from '@/Components/AppShell.vue'
import AdminQuickLinks from '@/Components/AdminQuickLinks.vue'

const props = defineProps({
  availability: Object,
})

const availabilityState = ref(props.availability)
let timer = null

const refreshAvailability = async () => {
  const { data } = await axios.get(route('availability.index'))
  availabilityState.value = data
}

onMounted(() => {
  timer = window.setInterval(refreshAvailability, 15000)
})

onBeforeUnmount(() => {
  if (timer) window.clearInterval(timer)
})
</script>

<template>
  <AppShell title="Admin Availability">
    <section class="mcd-section">
      <div class="mcd-panel p-8">
        <p class="mcd-chip">Availability</p>
        <h1 class="mt-4 text-4xl">Monitor available and unavailable dates by branch.</h1>
        <div class="mt-6 flex flex-wrap items-center justify-between gap-4">
          <AdminQuickLinks current="availability" />
          <button type="button" class="mcd-button mcd-button--ghost" @click="refreshAvailability">Refresh now</button>
        </div>
      </div>
    </section>

    <section class="mcd-section">
      <div class="space-y-4">
        <article v-for="branch in availabilityState.branches" :key="branch.code" class="mcd-panel p-6">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <h2 class="text-2xl">{{ branch.name }}</h2>
              <p class="mt-1 text-sm text-slate-500">{{ branch.city }}</p>
            </div>
          </div>
          <div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <div
              v-for="dateItem in branch.dates"
              :key="dateItem.date"
              class="rounded-2xl border p-4"
              :class="dateItem.status === 'full' ? 'border-slate-200 bg-slate-100' : dateItem.status === 'limited' ? 'border-amber-300 bg-amber-50' : 'border-emerald-200 bg-emerald-50'"
            >
              <p class="font-bold">{{ dateItem.date }}</p>
              <p class="mt-1 text-sm capitalize">{{ dateItem.status }}</p>
              <p class="mt-1 text-xs">Open 4-hour start times: {{ dateItem.available_slots }}</p>
            </div>
          </div>
        </article>
      </div>
    </section>
  </AppShell>
</template>
