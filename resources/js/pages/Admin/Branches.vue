<script setup>
import { useForm } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import AdminQuickLinks from '@/Components/AdminQuickLinks.vue'

const props = defineProps({
  branches: Array,
})

const branchForm = useForm({
  name: '',
  city: '',
  code: '',
  map_url: '',
  concurrent_limit: 2,
  max_guests: 40,
  supports: ['birthday', 'table'],
})

const createBranch = () => {
  branchForm.post(route('admin.branches.store'), { preserveScroll: true, preserveState: true })
}
</script>

<template>
  <AppShell title="Admin Branches">
    <section class="mcd-section">
      <div class="mcd-panel p-8">
        <p class="mcd-chip">Branches</p>
        <h1 class="mt-4 text-4xl">Manage store locations on their own clean page.</h1>
        <div class="mt-6">
          <AdminQuickLinks current="branches" />
        </div>
      </div>
    </section>

    <section class="mcd-section">
      <div class="mcd-grid mcd-grid--2">
        <article class="mcd-panel p-6">
          <p class="mcd-chip">Add branch</p>
          <form class="mt-5 grid gap-4" @submit.prevent="createBranch">
            <div class="mcd-grid mcd-grid--2">
              <input v-model="branchForm.name" type="text" class="mcd-input" placeholder="Branch name" />
              <input v-model="branchForm.city" type="text" class="mcd-input" placeholder="City" />
            </div>
            <div class="mcd-grid mcd-grid--2">
              <input v-model="branchForm.code" type="text" class="mcd-input" placeholder="branch-code" />
              <input v-model="branchForm.map_url" type="url" class="mcd-input" placeholder="Map URL" />
            </div>
            <div class="mcd-grid mcd-grid--2">
              <input v-model="branchForm.concurrent_limit" type="number" min="1" max="10" class="mcd-input" placeholder="Concurrent limit" />
              <input v-model="branchForm.max_guests" type="number" min="4" max="200" class="mcd-input" placeholder="Max guests" />
            </div>
            <div class="rounded-3xl bg-amber-50 p-4">
              <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">Supports</p>
              <div class="mt-3 flex flex-wrap gap-4">
                <label class="flex items-center gap-2"><input v-model="branchForm.supports" type="checkbox" value="birthday" /> <span>Birthday</span></label>
                <label class="flex items-center gap-2"><input v-model="branchForm.supports" type="checkbox" value="business" /> <span>Business</span></label>
                <label class="flex items-center gap-2"><input v-model="branchForm.supports" type="checkbox" value="table" /> <span>Table</span></label>
              </div>
            </div>
            <button type="submit" class="mcd-button" :disabled="branchForm.processing">{{ branchForm.processing ? 'Adding...' : 'Add branch' }}</button>
          </form>
        </article>

        <article class="mcd-panel p-6">
          <p class="mcd-chip">Existing branches</p>
          <div class="mt-5 space-y-3">
            <div v-for="branch in branches" :key="branch.code" class="rounded-2xl bg-white p-4">
              <p class="font-bold">{{ branch.name }}</p>
              <p class="mt-1 text-sm text-slate-500">{{ branch.city }} | {{ branch.code }}</p>
            </div>
          </div>
        </article>
      </div>
    </section>
  </AppShell>
</template>
