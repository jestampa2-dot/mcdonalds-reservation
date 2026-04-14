<script setup>
defineProps({
  form: {
    type: Object,
    required: true,
  },
})
</script>

<template>
  <section class="mcd-panel p-6">
    <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">5. Special notes</p>
    <h2 class="mt-3 text-3xl">Add booking notes.</h2>
    <p class="mt-2 text-sm text-slate-500">Use this space for celebrant requests, setup reminders, allergies, or anything the branch should know.</p>

    <div class="mt-6 mcd-field">
      <label>Special notes</label>
      <textarea v-model="form.notes" rows="6" class="mcd-textarea"></textarea>
      <p class="mt-2 text-xs text-slate-500">Your profile details will be attached to this reservation automatically.</p>
    </div>
  </section>
</template>
