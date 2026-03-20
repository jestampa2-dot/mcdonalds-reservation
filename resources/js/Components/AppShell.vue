<script setup>
import { computed } from 'vue'
import { Head, Link, usePage } from '@inertiajs/vue3'

const props = defineProps({
  title: {
    type: String,
    default: 'McDonald\'s Reservations',
  },
})

const page = usePage()
const user = computed(() => page.props.auth?.user)

const navLinks = computed(() => {
  const links = [{ label: 'Home', href: route('home') }]

  if (!user.value) {
    links.push({ label: 'Login', href: route('login') })
    links.push({ label: 'Register', href: route('register') })
    return links
  }

  links.push({ label: 'Book Event', href: route('reservations.create') })
  links.push({ label: 'My Dashboard', href: route('dashboard') })

  if (['admin', 'manager'].includes(user.value.role)) {
    links.push({ label: 'Admin View', href: route('admin.dashboard') })
  }

  if (['admin', 'manager', 'staff'].includes(user.value.role)) {
    links.push({ label: 'Staff View', href: route('staff.dashboard') })
  }

  return links
})

const flash = computed(() => page.props.flash ?? {})
</script>

<template>
  <div class="mcd-shell">
    <Head :title="title" />

    <div class="mcd-shell__backdrop"></div>

    <header class="mcd-topbar">
      <Link :href="route('home')" class="mcd-brand">
        <span class="mcd-brand__arches">M</span>
        <span>
          <strong>McDonald's Reservations</strong>
          <small>Fast, friendly, branch-ready event booking</small>
        </span>
      </Link>

      <nav class="mcd-nav">
        <Link
          v-for="link in navLinks"
          :key="link.href"
          :href="link.href"
          class="mcd-nav__link"
        >
          {{ link.label }}
        </Link>
        <Link
          v-if="user"
          :href="route('logout')"
          method="post"
          as="button"
          class="mcd-nav__button"
        >
          Logout
        </Link>
      </nav>
    </header>

    <main class="mcd-content">
      <section v-if="flash.success || flash.error" class="mcd-flash-wrap">
        <div v-if="flash.success" class="mcd-flash mcd-flash--success">
          {{ flash.success }}
        </div>
        <div v-if="flash.error" class="mcd-flash mcd-flash--error">
          {{ flash.error }}
        </div>
      </section>

      <slot />
    </main>
  </div>
</template>
