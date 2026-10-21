import { computed, readonly, ref } from 'vue'

const STORAGE_KEY = 'mcd-reservations-theme'
const theme = ref('light')
let initialized = false
let mediaQuery

const preferredTheme = () => (
  window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
)

const applyTheme = (value) => {
  const nextTheme = value === 'dark' ? 'dark' : 'light'

  theme.value = nextTheme
  document.documentElement.dataset.theme = nextTheme
  document.documentElement.classList.toggle('dark', nextTheme === 'dark')
  document.documentElement.style.colorScheme = nextTheme
}

export const initializeTheme = () => {
  if (typeof window === 'undefined') {
    return
  }

  let savedTheme = null

  try {
    savedTheme = window.localStorage.getItem(STORAGE_KEY)
  } catch {
    savedTheme = null
  }

  applyTheme(savedTheme ?? document.documentElement.dataset.theme ?? preferredTheme())

  if (!initialized && window.matchMedia) {
    mediaQuery = window.matchMedia('(prefers-color-scheme: dark)')
    mediaQuery.addEventListener?.('change', (event) => {
      let hasSavedTheme = false

      try {
        hasSavedTheme = Boolean(window.localStorage.getItem(STORAGE_KEY))
      } catch {
        hasSavedTheme = false
      }

      if (!hasSavedTheme) {
        applyTheme(event.matches ? 'dark' : 'light')
      }
    })
  }

  initialized = true
}

export const useTheme = () => {
  initializeTheme()

  const setTheme = (value) => {
    const nextTheme = value === 'dark' ? 'dark' : 'light'

    try {
      window.localStorage.setItem(STORAGE_KEY, nextTheme)
    } catch {
      // The visual theme still works when browser storage is unavailable.
    }

    applyTheme(nextTheme)
  }

  const toggleTheme = () => setTheme(theme.value === 'dark' ? 'light' : 'dark')

  return {
    theme: readonly(theme),
    isDark: computed(() => theme.value === 'dark'),
    setTheme,
    toggleTheme,
  }
}
