<script setup>
import { computed } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import ThemeToggle from '@/Components/ThemeToggle.vue'

const props = defineProps({
  result: String,
  success: Boolean,
  title: String,
  message: String,
  booking: Object,
  viewerAuthenticated: Boolean,
})

const statusLabel = computed(() => props.success ? 'Ongoing' : 'Not started')
</script>

<template>
  <Head :title="title" />

  <main class="qr-result-page">
    <header class="qr-result-page__header">
      <Link :href="route('home')" class="qr-result-page__brand">
        <span aria-hidden="true">M</span>
        <strong>McDonald's Event Planner</strong>
      </Link>
      <ThemeToggle />
    </header>

    <section class="qr-result" :class="{ 'is-success': success }">
      <div class="qr-result__status-row">
        <span class="qr-result__status">{{ statusLabel }}</span>
        <span class="qr-result__reference">{{ booking.reference }}</span>
      </div>

      <p class="qr-result__eyebrow">QR event check-in</p>
      <h1>{{ title }}</h1>
      <p class="qr-result__message">{{ message }}</p>

      <dl class="qr-result__details">
        <div>
          <dt>Branch</dt>
          <dd>{{ booking.branch }}</dd>
        </div>
        <div>
          <dt>Schedule</dt>
          <dd>{{ booking.event_date }} | {{ booking.event_time }}</dd>
        </div>
        <div v-if="booking.checked_in_at">
          <dt>Checked in</dt>
          <dd>{{ booking.checked_in_at }}</dd>
        </div>
      </dl>

      <div class="qr-result__actions">
        <Link v-if="viewerAuthenticated" :href="route('dashboard')" class="mcd-button">Open my dashboard</Link>
        <Link :href="route('home')" class="mcd-button mcd-button--ghost">Return home</Link>
      </div>
    </section>
  </main>
</template>

<style scoped>
.qr-result-page {
  min-height: 100vh;
  padding: 1.25rem;
  background: #fff8eb;
  color: #241714;
}

.qr-result-page__header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
  width: min(54rem, 100%);
  margin: 0 auto;
}

.qr-result-page__brand {
  display: inline-flex;
  align-items: center;
  gap: 0.75rem;
  color: #241714;
  text-decoration: none;
}

.qr-result-page__brand > span {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 2.5rem;
  height: 2.5rem;
  border-radius: 50%;
  background: #da291c;
  color: #ffc72c;
  font-size: 1.35rem;
  font-weight: 900;
}

.qr-result {
  width: min(44rem, 100%);
  margin: clamp(3rem, 10vh, 7rem) auto 0;
  padding: clamp(1.5rem, 5vw, 3rem);
  border: 1px solid rgba(36, 23, 20, 0.12);
  border-top: 6px solid #da291c;
  border-radius: 0.75rem;
  background: #ffffff;
  box-shadow: 0 24px 60px rgba(36, 23, 20, 0.12);
}

.qr-result.is-success {
  border-top-color: #16803c;
}

.qr-result__status-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 1rem;
}

.qr-result__status {
  padding: 0.35rem 0.65rem;
  border-radius: 999px;
  background: #fff1ef;
  color: #a61b15;
  font-size: 0.72rem;
  font-weight: 900;
  text-transform: uppercase;
}

.is-success .qr-result__status {
  background: #e9f8ee;
  color: #12642f;
}

.qr-result__reference {
  color: #64748b;
  font-size: 0.78rem;
  font-weight: 800;
}

.qr-result__eyebrow {
  margin-top: 2rem;
  color: #b42318;
  font-size: 0.72rem;
  font-weight: 900;
  text-transform: uppercase;
}

.qr-result h1 {
  margin-top: 0.5rem;
  font-size: clamp(2rem, 5vw, 3.5rem);
  line-height: 1.05;
}

.qr-result__message {
  max-width: 38rem;
  margin-top: 1rem;
  color: #64748b;
  line-height: 1.7;
}

.qr-result__details {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 1rem;
  margin-top: 2rem;
  padding: 1.25rem 0;
  border-top: 1px solid #e2e8f0;
  border-bottom: 1px solid #e2e8f0;
}

.qr-result__details div {
  min-width: 0;
}

.qr-result__details dt {
  color: #64748b;
  font-size: 0.68rem;
  font-weight: 900;
  text-transform: uppercase;
}

.qr-result__details dd {
  margin-top: 0.35rem;
  overflow-wrap: anywhere;
  font-size: 0.9rem;
  font-weight: 800;
  line-height: 1.45;
}

.qr-result__actions {
  display: flex;
  flex-wrap: wrap;
  gap: 0.75rem;
  margin-top: 1.5rem;
}

:global(html[data-theme='dark']) .qr-result-page {
  background: #151617;
  color: #f8fafc;
}

:global(html[data-theme='dark']) .qr-result-page__brand {
  color: #f8fafc;
}

:global(html[data-theme='dark']) .qr-result {
  border-color: #3d4148;
  background: #202225;
  box-shadow: 0 24px 60px rgba(0, 0, 0, 0.3);
}

:global(html[data-theme='dark']) .qr-result__reference,
:global(html[data-theme='dark']) .qr-result__message,
:global(html[data-theme='dark']) .qr-result__details dt {
  color: #b9c0ca;
}

:global(html[data-theme='dark']) .qr-result__details {
  border-color: #3d4148;
}

@media (max-width: 640px) {
  .qr-result-page {
    padding: 1rem;
  }

  .qr-result {
    margin-top: 2.5rem;
  }

  .qr-result__details {
    grid-template-columns: minmax(0, 1fr);
  }

  .qr-result__actions .mcd-button {
    width: 100%;
  }
}
</style>
