<script setup>
import { computed } from 'vue'

const props = defineProps({
  form: {
    type: Object,
    required: true,
  },
  addOns: {
    type: Array,
    default: () => [],
  },
  formatCurrency: {
    type: Function,
    required: true,
  },
})

const themeOptions = computed(() => props.addOns.filter((item) => item.kind === 'theme'))
const serviceOptions = computed(() => [...props.addOns]
  .filter((item) => item.kind !== 'theme')
  .sort((left, right) => Number(Boolean(right.badge)) - Number(Boolean(left.badge))))
const themeCodes = computed(() => new Set(themeOptions.value.map((item) => item.code)))

const selectedThemeCode = computed({
  get: () => props.form.add_ons.find((code) => themeCodes.value.has(code)) ?? '',
  set: (code) => {
    const selectedServices = props.form.add_ons.filter((itemCode) => !themeCodes.value.has(itemCode))
    props.form.add_ons = code ? [...selectedServices, code] : selectedServices
  },
})

const isSelected = (code) => props.form.add_ons.includes(code)
</script>

<template>
  <section class="mcd-panel booking-theme-step p-6">
    <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">5. Theme & extras</p>
    <h2 class="mt-3 text-3xl">Make the event feel special.</h2>
    <p class="mt-2 text-sm text-slate-500">Choose one decoration theme, then add any services you would like the branch to prepare.</p>

    <div class="booking-theme-step__heading">
      <div>
        <p class="booking-theme-step__kicker">Decoration theme</p>
        <h3>Choose the look of your event</h3>
      </div>
      <span>Choose one</span>
    </div>

    <div class="booking-theme-grid">
      <label
        v-for="theme in themeOptions"
        :key="theme.code"
        class="booking-theme-card"
        :class="{ 'is-selected': selectedThemeCode === theme.code }"
      >
        <input v-model="selectedThemeCode" :value="theme.code" type="radio" name="event-theme" />
        <img :src="theme.image_url" :alt="`${theme.name} event decoration preview`" loading="lazy" />
        <span class="booking-theme-card__check" aria-hidden="true">{{ selectedThemeCode === theme.code ? 'Selected' : 'Select' }}</span>
        <div class="booking-theme-card__body">
          <div class="booking-theme-card__title">
            <strong>{{ theme.name }}</strong>
            <span>{{ formatCurrency(theme.price) }}</span>
          </div>
          <p>{{ theme.description }}</p>
        </div>
      </label>

      <label class="booking-theme-none" :class="{ 'is-selected': selectedThemeCode === '' }">
        <input v-model="selectedThemeCode" value="" type="radio" name="event-theme" />
        <span class="booking-theme-none__icon" aria-hidden="true">-</span>
        <span>
          <strong>No decoration package</strong>
          <small>Continue without adding a theme.</small>
        </span>
      </label>
    </div>

    <div class="booking-theme-step__heading booking-theme-step__heading--services">
      <div>
        <p class="booking-theme-step__kicker">Add-on services</p>
        <h3>Add more fun to the celebration</h3>
      </div>
      <span>Choose any</span>
    </div>

    <div class="booking-character-banner">
      <img
        src="/images/event-themes/character-appearances.webp"
        alt="McDonald's character appearance service preview"
        loading="lazy"
      />
      <div>
        <strong>Character appearances</strong>
        <span>Choose your guest favorites below. Each character can be booked separately.</span>
      </div>
    </div>

    <div class="booking-service-grid">
      <label
        v-for="service in serviceOptions"
        :key="service.code"
        class="booking-service-card"
        :class="{ 'is-selected': isSelected(service.code) }"
      >
        <input v-model="form.add_ons" :value="service.code" type="checkbox" />
        <span class="booking-service-card__control" aria-hidden="true">{{ isSelected(service.code) ? '✓' : '+' }}</span>
        <div class="booking-service-card__copy">
          <span v-if="service.badge" class="booking-service-card__badge">{{ service.badge }}</span>
          <strong>{{ service.name }}</strong>
          <p>{{ service.description }}</p>
        </div>
        <div class="booking-service-card__price">
          <span>{{ formatCurrency(service.price) }}</span>
          <small>{{ isSelected(service.code) ? 'Added' : 'Add' }}</small>
        </div>
      </label>
    </div>
  </section>
</template>
