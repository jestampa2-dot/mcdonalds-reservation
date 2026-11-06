<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue'
import { computed } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import FlashToast from '@/Components/FlashToast.vue'
import LanguageToggle from '@/Components/LanguageToggle.vue'
import ThemeToggle from '@/Components/ThemeToggle.vue'
import { useLanguage } from '@/composables/useLanguage'

const props = defineProps({
  title: {
    type: String,
    default: 'McDonald\'s Reservations',
  },
})

const page = usePage()
const user = computed(() => page.props.auth?.user)
const { t } = useLanguage()

const navLinks = computed(() => {
  const links = [
    {
      label: t('home'),
      href: route('home'),
      current: 'home',
    },
  ]

  if (!user.value) {
    links.push({
      label: t('login'),
      href: route('login'),
      current: 'login',
    })
    links.push({
      label: t('register'),
      href: route('register'),
      current: 'register',
    })
    return links
  }

  links.push({
    label: t('bookEvent'),
    href: route('reservations.create'),
    current: 'reservations.create',
  })
  links.push({
    label: t('dashboard'),
    href: route('dashboard'),
    current: 'dashboard',
  })
  links.push({
    label: t('account'),
    href: route('profile.edit'),
    current: 'profile.*',
  })

  if (['master_admin', 'admin', 'manager'].includes(user.value.role)) {
    links.push({
      label: user.value.role === 'master_admin' ? 'Master Admin' : 'Admin View',
      href: route('admin.dashboard'),
      current: 'admin.*',
    })
  }

  if (['master_admin', 'admin', 'manager', 'staff'].includes(user.value.role)) {
    links.push({
      label: t('staffView'),
      href: route('staff.dashboard'),
      current: 'staff.*',
    })
  }

  return links
})

const roleLabel = computed(() => {
  if (!user.value) {
    return 'Guest'
  }

  return String(user.value.role)
    .split('_')
    .map((part) => `${part.charAt(0).toUpperCase()}${part.slice(1)}`)
    .join(' ')
})

const workspaceSubtitle = computed(() => {
  if (!user.value) {
    return t('browseAndBook')
  }

  if (user.value.role === 'master_admin') {
    return 'Cross-branch governance, reports, accounts, and activity.'
  }

  if (['admin', 'manager'].includes(user.value.role)) {
    return 'Bookings, branches, catalog, and reports.'
  }

  if (user.value.role === 'staff') {
    return 'Check-ins, prep, and floor updates.'
  }

  return t('customerWorkspace')
})

const userInitials = computed(() => {
  const name = user.value?.name ?? 'Guest'

  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase())
    .join('')
})

const isLinkActive = (link) => {
  const patterns = Array.isArray(link.current) ? link.current : [link.current]

  return patterns.some((pattern) => pattern && route().current(pattern))
}
</script>

<template>
  <div class="mcd-shell">
    <Head :title="title" />

    <div class="mcd-shell__backdrop"></div>

    <div class="mcd-shell__layout">
      <aside class="mcd-sidebar">
        <Link :href="route('home')" prefetch class="mcd-brand">
          <ApplicationLogo class="mcd-brand__arches" />
          <span>
            <strong>McDonald's Reservations</strong>
            <small>Event booking</small>
          </span>
        </Link>

        <div class="mcd-sidebar__meta">
          <h2>Book and manage events.</h2>
        </div>

        <nav class="mcd-sidebar__nav">
          <Link
            v-for="link in navLinks"
            :key="link.href"
            :href="link.href"
            prefetch
            class="mcd-sidebar__link"
            :class="{ 'is-active': isLinkActive(link) }"
          >
            <span>{{ link.label }}</span>
          </Link>
        </nav>

      </aside>

      <div class="mcd-main">
        <header class="mcd-toolbar">
          <div class="mcd-toolbar__copy">
            <p class="mcd-toolbar__eyebrow">{{ roleLabel }}</p>
            <p class="mcd-toolbar__title">{{ props.title }}</p>
            <p class="mcd-toolbar__subtitle">{{ workspaceSubtitle }}</p>
          </div>

          <div class="mcd-toolbar__actions">
            <LanguageToggle />
            <ThemeToggle />

            <div class="mcd-toolbar__profile">
              <img
                v-if="user?.profile_photo_url"
                :src="user.profile_photo_url"
                alt="Profile photo"
                class="mcd-toolbar__avatar"
              />
              <span v-else class="mcd-toolbar__avatar">{{ userInitials }}</span>
              <span>
                <strong>{{ user?.name ?? 'Guest User' }}</strong>
                <small>{{ user?.email ?? 'Sign in to continue.' }}</small>
              </span>
            </div>

            <Link
              v-if="!user"
              :href="route('login')"
              prefetch
              class="mcd-nav__button"
            >
              {{ t('signIn') }}
            </Link>
            <Link
              v-else
              :href="route('logout')"
              method="post"
              as="button"
              class="mcd-nav__button"
            >
              {{ t('logout') }}
            </Link>
          </div>
        </header>

        <main class="mcd-content">
          <FlashToast />

          <slot />
        </main>
      </div>
    </div>
  </div>
</template>
