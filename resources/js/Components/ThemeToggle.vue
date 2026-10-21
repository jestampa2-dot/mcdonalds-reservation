<script setup>
import { computed } from 'vue'
import { useTheme } from '@/composables/useTheme'

const { isDark, toggleTheme } = useTheme()
const label = computed(() => isDark.value ? 'Switch to light mode' : 'Switch to dark mode')
</script>

<template>
  <button
    type="button"
    class="theme-toggle"
    :class="{ 'is-dark': isDark }"
    :aria-label="label"
    :aria-pressed="isDark"
    :title="label"
    @click="toggleTheme"
  >
    <svg v-if="isDark" viewBox="0 0 24 24" aria-hidden="true">
      <path d="M12 3a9 9 0 1 0 9 9 7 7 0 0 1-9-9Z" />
    </svg>
    <svg v-else viewBox="0 0 24 24" aria-hidden="true">
      <circle cx="12" cy="12" r="4" />
      <path d="M12 2v2M12 20v2M4.93 4.93l1.42 1.42M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.42-1.42M17.66 6.34l1.41-1.41" />
    </svg>
    <span class="sr-only">{{ label }}</span>
  </button>
</template>
