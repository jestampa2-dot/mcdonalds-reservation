<script setup>
import { Link } from '@inertiajs/vue3'
import FlashToast from '@/Components/FlashToast.vue'

defineProps({
  title: {
    type: String,
    default: 'Welcome',
  },
  eyebrow: {
    type: String,
    default: 'McDonald\'s Reservations',
  },
  heading: {
    type: String,
    default: 'Plan and manage reservations with speed.',
  },
  description: {
    type: String,
    default: 'From birthday parties to staff-ready check-ins, keep every booking in one fast-moving system.',
  },
  stats: {
    type: Array,
    default: () => [],
  },
})
</script>

<template>
  <div class="auth-shell">
    <FlashToast />
    <div class="auth-shell__glow auth-shell__glow--left"></div>
    <div class="auth-shell__glow auth-shell__glow--right"></div>

    <div class="auth-shell__frame">
      <section class="auth-shell__hero">
        <Link href="/" class="auth-shell__brand">
          <span class="auth-shell__brand-mark">M</span>
          <span>
            <strong>McDonald's Reservations</strong>
            <small>Fast bookings for guests, managers, and crew</small>
          </span>
        </Link>

        <div class="auth-shell__copy">
          <p class="auth-shell__eyebrow">{{ eyebrow }}</p>
          <h1>{{ heading }}</h1>
          <p class="auth-shell__description">{{ description }}</p>
        </div>

        <div class="auth-shell__feature-grid">
          <article class="auth-shell__feature">
            <p class="auth-shell__feature-label">Customer Flow</p>
            <h2>Live slots, menu bundles, proof-of-payment, and QR pass delivery.</h2>
          </article>
          <article class="auth-shell__feature">
            <p class="auth-shell__feature-label">Operations</p>
            <h2>Branch calendars, inventory alerts, floor status, and host scheduling.</h2>
          </article>
        </div>

        <div class="auth-shell__stats">
          <article
            v-for="item in stats"
            :key="item.label"
            class="auth-shell__stat"
          >
            <strong>{{ item.value }}</strong>
            <span>{{ item.label }}</span>
          </article>
        </div>
      </section>

      <section class="auth-shell__panel">
        <div class="auth-shell__panel-inner">
          <slot />
        </div>
      </section>
    </div>
  </div>
</template>
