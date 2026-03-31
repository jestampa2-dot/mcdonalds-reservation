<script setup>
import { computed } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'
import FlashToast from '@/Components/FlashToast.vue'

const props = defineProps({
  title: {
    type: String,
    default: 'McDonald\'s Reservations',
  },
})

const page = usePage()
const user = computed(() => page.props.auth?.user)

const navLinks = computed(() => {
  const links = [
    {
      label: 'Home',
      href: route('home'),
      current: 'home',
    },
  ]

  if (!user.value) {
    links.push({
      label: 'Login',
      href: route('login'),
      current: 'login',
    })
    links.push({
      label: 'Register',
      href: route('register'),
      current: 'register',
    })
    return links
  }

  links.push({
    label: 'Book Event',
    href: route('reservations.create'),
    current: 'reservations.create',
  })
  links.push({
    label: 'My Dashboard',
    href: route('dashboard'),
    current: 'dashboard',
  })
  links.push({
    label: 'My Account',
    href: route('profile.edit'),
    current: 'profile.*',
  })

  if (['admin', 'manager'].includes(user.value.role)) {
    links.push({
      label: 'Admin View',
      href: route('admin.dashboard'),
      current: 'admin.*',
    })
  }

  if (['admin', 'manager', 'staff'].includes(user.value.role)) {
    links.push({
      label: 'Staff View',
      href: route('staff.dashboard'),
      current: 'staff.*',
    })
  }

  return links
})

const roleLabel = computed(() => {
  if (!user.value) {
    return 'Guest experience'
  }

  return `${String(user.value.role).charAt(0).toUpperCase()}${String(user.value.role).slice(1)} workspace`
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
          <span class="mcd-brand__arches">M</span>
          <span>
            <strong>McDonald's Reservations</strong>
            <small>Fast, friendly, branch-ready event booking</small>
          </span>
        </Link>

        <div class="mcd-sidebar__meta">
          <p class="mcd-chip">Reservation OS</p>
          <h2>McDonald&apos;s event booking platform.</h2>
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
          </div>

          <div class="mcd-toolbar__actions">
            <span class="mcd-toolbar__pill">Philippine-time booking flow</span>

            <div class="mcd-toolbar__profile">
              <span class="mcd-toolbar__avatar">{{ userInitials }}</span>
              <span>
                <strong>{{ user?.name ?? 'Guest User' }}</strong>
                <small>{{ user?.email ?? 'Browse packages, then sign in to book.' }}</small>
              </span>
            </div>

            <Link
              v-if="!user"
              :href="route('login')"
              prefetch
              class="mcd-nav__button"
            >
              Sign in
            </Link>
            <Link
              v-else
              :href="route('logout')"
              method="post"
              as="button"
              class="mcd-nav__button"
            >
              Logout
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
