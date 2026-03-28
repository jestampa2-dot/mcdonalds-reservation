<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
  current: {
    type: String,
    default: 'hub',
  },
})

const links = [
  { key: 'hub', label: 'Hub', href: route('admin.dashboard') },
  { key: 'pending', label: 'Pending Bookings', href: route('admin.bookings') },
  { key: 'confirmed', label: 'Confirmed Events', href: route('admin.confirmed') },
  { key: 'availability', label: 'Availability', href: route('admin.availability') },
  { key: 'catalog', label: 'Catalog', href: route('admin.catalog') },
  { key: 'branches', label: 'Branches', href: route('admin.branches') },
  { key: 'accounts', label: 'Accounts', href: route('admin.accounts') },
  { key: 'reports', label: 'Reports', href: route('admin.reports') },
  { key: 'timeline', label: 'Timeline', href: route('admin.timeline') },
]
</script>

<template>
  <div class="flex flex-wrap gap-3">
    <Link
      v-for="link in links"
      :key="link.key"
      :href="link.href"
      class="mcd-button"
      :class="current === link.key ? '' : 'mcd-button--ghost'"
    >
      {{ link.label }}
    </Link>
  </div>
</template>
