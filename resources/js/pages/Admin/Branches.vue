<script setup>
import { reactive, watch } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import AppShell from '@/Components/AppShell.vue'
import AdminQuickLinks from '@/Components/AdminQuickLinks.vue'

const props = defineProps({
  branches: Array,
  isMasterAdmin: Boolean,
})

const emptyBranchEdit = (branch) => ({
  id: branch.id,
  code: branch.code,
  name: branch.name ?? '',
  city: branch.city ?? '',
  map_url: branch.map_url ?? '',
  concurrent_limit: branch.concurrent_limit ?? 2,
  max_guests: branch.max_guests ?? 40,
  supports: Array.isArray(branch.supports) ? [...branch.supports] : [],
  is_active: branch.is_active ?? true,
})

const branchEdits = reactive({})
const paymentEdits = reactive({})
const paymentSaving = reactive({})

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

watch(
  () => props.branches,
  (branches) => {
    Object.keys(branchEdits).forEach((key) => {
      delete branchEdits[key]
    })
    Object.keys(paymentEdits).forEach((key) => {
      delete paymentEdits[key]
    })

    branches.forEach((branch) => {
      branchEdits[branch.id] = emptyBranchEdit(branch)
      paymentEdits[branch.id] = {
        payment_number: branch.payment_number ?? '',
        payment_qr: null,
        remove_payment_qr: false,
      }
    })
  },
  { immediate: true },
)

const updateBranch = (id) => {
  router.post(route('admin.branches.update', id), branchEdits[id], {
    preserveScroll: true,
    preserveState: true,
  })
}

const selectPaymentQr = (id, event) => {
  paymentEdits[id].payment_qr = event.target.files?.[0] ?? null
  paymentEdits[id].remove_payment_qr = false
}

const updatePaymentDetails = (id) => {
  const data = new FormData()
  data.append('payment_number', paymentEdits[id].payment_number ?? '')
  data.append('remove_payment_qr', paymentEdits[id].remove_payment_qr ? '1' : '0')

  if (paymentEdits[id].payment_qr) {
    data.append('payment_qr', paymentEdits[id].payment_qr)
  }

  paymentSaving[id] = true
  router.post(route('staff.branches.payment-details', id), data, {
    forceFormData: true,
    preserveScroll: true,
    preserveState: true,
    onFinish: () => {
      paymentSaving[id] = false
    },
  })
}

const deleteBranch = (id) => {
  if (!window.confirm('Delete this branch? Existing branch-linked records will also be removed.')) {
    return
  }

  router.delete(route('admin.branches.destroy', id), {
    preserveScroll: true,
    preserveState: true,
  })
}
</script>

<template>
  <AppShell title="Admin Branches">
    <section class="mcd-section">
      <div class="mcd-panel p-8">
        <p class="mcd-chip">Branches</p>
        <h1 class="mt-4 text-4xl">Branches</h1>
        <div class="mt-6">
          <AdminQuickLinks current="branches" />
        </div>
      </div>
    </section>

    <section class="mcd-section">
      <div class="mcd-grid mcd-grid--2">
        <article v-if="isMasterAdmin" class="mcd-panel p-6">
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
            <form
              v-for="branch in branches"
              :key="branch.id"
              class="rounded-3xl bg-white p-5"
              @submit.prevent="updateBranch(branch.id)"
            >
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <p class="font-bold">{{ branch.code }}</p>
                  <p class="mt-1 text-sm text-slate-500">Branch code is fixed for linked bookings.</p>
                </div>
                <label class="flex items-center gap-2 text-sm font-semibold text-slate-600">
                  <input v-model="branchEdits[branch.id].is_active" type="checkbox" />
                  <span>Active</span>
                </label>
              </div>

              <div class="mt-4 grid gap-4">
                <div class="mcd-grid mcd-grid--2">
                  <input v-model="branchEdits[branch.id].name" type="text" class="mcd-input" placeholder="Branch name" />
                  <input v-model="branchEdits[branch.id].city" type="text" class="mcd-input" placeholder="City" />
                </div>
                <input v-model="branchEdits[branch.id].map_url" type="url" class="mcd-input" placeholder="Map URL" />
                <div class="mcd-grid mcd-grid--2">
                  <input v-model="branchEdits[branch.id].concurrent_limit" type="number" min="1" max="10" class="mcd-input" placeholder="Concurrent limit" />
                  <input v-model="branchEdits[branch.id].max_guests" type="number" min="4" max="200" class="mcd-input" placeholder="Max guests" />
                </div>
                <div class="rounded-3xl bg-amber-50 p-4">
                  <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">Supports</p>
                  <div class="mt-3 flex flex-wrap gap-4">
                    <label class="flex items-center gap-2"><input v-model="branchEdits[branch.id].supports" type="checkbox" value="birthday" /> <span>Birthday</span></label>
                    <label class="flex items-center gap-2"><input v-model="branchEdits[branch.id].supports" type="checkbox" value="business" /> <span>Business</span></label>
                    <label class="flex items-center gap-2"><input v-model="branchEdits[branch.id].supports" type="checkbox" value="table" /> <span>Table</span></label>
                  </div>
                </div>
                <div class="rounded-3xl border border-amber-200 bg-amber-50 p-5">
                  <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">Customer payment destination</p>
                  <p class="mt-2 text-sm text-slate-600">This branch's QR code and account or mobile number will appear in the customer's payment step.</p>
                  <div class="mt-4 grid gap-4 lg:grid-cols-[180px,1fr]">
                    <div class="rounded-2xl bg-white p-3 text-center">
                      <img
                        v-if="branch.payment_qr_url && !paymentEdits[branch.id].remove_payment_qr"
                        :src="branch.payment_qr_url"
                        :alt="`${branch.name} payment QR code`"
                        class="mx-auto aspect-square w-full rounded-xl object-contain"
                      />
                      <p v-else class="grid aspect-square place-items-center text-sm font-bold text-slate-400">No QR uploaded</p>
                    </div>
                    <div class="grid gap-3">
                      <label class="mcd-field">
                        <span>Payment account or mobile number</span>
                        <input v-model="paymentEdits[branch.id].payment_number" type="text" class="mcd-input" maxlength="100" placeholder="Example: GCash 0917 123 4567" />
                      </label>
                      <label class="mcd-field">
                        <span>Upload payment QR</span>
                        <input type="file" accept="image/*" class="mcd-input" @change="selectPaymentQr(branch.id, $event)" />
                      </label>
                      <label v-if="branch.payment_qr_url" class="flex items-center gap-2 text-sm font-semibold text-slate-600">
                        <input v-model="paymentEdits[branch.id].remove_payment_qr" type="checkbox" />
                        <span>Remove the current QR image</span>
                      </label>
                      <button type="button" class="mcd-button" :disabled="paymentSaving[branch.id]" @click="updatePaymentDetails(branch.id)">
                        {{ paymentSaving[branch.id] ? 'Saving payment details...' : 'Save payment QR and number' }}
                      </button>
                    </div>
                  </div>
                </div>
                <div class="flex flex-wrap gap-3">
                  <button type="submit" class="mcd-button">Save changes</button>
                  <button v-if="isMasterAdmin" type="button" class="mcd-button mcd-button--ghost" @click="deleteBranch(branch.id)">Delete branch</button>
                </div>
              </div>
            </form>
          </div>
        </article>
      </div>
    </section>
  </AppShell>
</template>
