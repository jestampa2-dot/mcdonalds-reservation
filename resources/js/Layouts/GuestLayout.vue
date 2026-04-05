<script setup>
import { Link } from '@inertiajs/vue3'
import FlashToast from '@/Components/FlashToast.vue'

const authPhotos = [
  {
    src: 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?auto=format&fit=crop&w=1200&q=80',
    alt: 'Stacked burger close-up',
    featured: true,
  },
  {
    src: 'https://images.unsplash.com/photo-1586190848861-99aa4a171e90?auto=format&fit=crop&w=900&q=80',
    alt: 'Fast food tray with fries and drinks',
  },
  {
    src: 'https://images.unsplash.com/photo-1550547660-d9450f859349?auto=format&fit=crop&w=900&q=80',
    alt: 'Restaurant burger presentation',
  },
]

defineProps({
  title: {
    type: String,
    default: 'Welcome',
  },
  panelSize: {
    type: String,
    default: 'default',
  },
  eyebrow: {
    type: String,
    default: 'McDonald\'s Reservations',
  },
  heading: {
    type: String,
    default: 'Sign in or create an account.',
  },
  description: {
    type: String,
    default: '',
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
        <div class="auth-shell__hero-stack">
          <Link href="/" prefetch class="auth-shell__brand">
            <span class="auth-shell__brand-mark">M</span>
            <span>
              <strong>McDonald's Reservations</strong>
              <small>Event booking</small>
            </span>
          </Link>

          <div class="auth-shell__copy">
            <p class="auth-shell__eyebrow">{{ eyebrow }}</p>
            <h1>{{ heading }}</h1>
            <p v-if="description" class="auth-shell__description">{{ description }}</p>
          </div>

          <div class="auth-shell__photo-grid" aria-hidden="true">
            <article
              v-for="photo in authPhotos"
              :key="photo.src"
              class="auth-shell__photo-card"
              :class="{ 'auth-shell__photo-card--feature': photo.featured }"
            >
              <img :src="photo.src" :alt="photo.alt" class="auth-shell__photo-image" loading="lazy" decoding="async" />
            </article>
          </div>
        </div>
      </section>

      <section class="auth-shell__panel">
        <div class="auth-shell__panel-inner" :class="{ 'auth-shell__panel-inner--wide': panelSize === 'wide' }">
          <slot />
        </div>
      </section>
    </div>
  </div>
</template>
