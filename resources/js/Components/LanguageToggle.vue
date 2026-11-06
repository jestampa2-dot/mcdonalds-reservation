<script setup>
import { useLanguage } from '@/composables/useLanguage'

const { language, setLanguage, t } = useLanguage()
</script>

<template>
  <label class="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white/90 px-3 py-2 text-xs font-black text-slate-700 shadow-sm dark:border-slate-600 dark:bg-slate-800 dark:text-slate-100">
    <span class="sr-only">{{ t('language') }}</span>
    <span aria-hidden="true">🌐</span>
    <select
      :value="language"
      class="border-0 bg-transparent p-0 pr-6 text-xs font-black focus:ring-0 dark:bg-slate-800"
      :aria-label="t('language')"
      @change="setLanguage($event.target.value)"
    >
      <option value="en">English</option>
      <option value="fil">Filipino</option>
    </select>
  </label>
</template>
