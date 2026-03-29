<script setup>
import { computed, reactive, ref, watch } from 'vue'

const props = defineProps({
  categories: {
    type: Array,
    default: () => [],
  },
  modelValue: {
    type: Array,
    default: () => [],
  },
})

const emit = defineEmits(['update:modelValue'])

const selectedCategoryCode = ref('')
const selectedOptionByItem = reactive({})

const formatCurrency = (value) =>
  `\u20B1${Number(value).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`

const artworkLabel = (artwork) => {
  const map = {
    burger: 'BG',
    'chicken-burger': 'CB',
    'shrimp-burger': 'SB',
    sandwich: 'SW',
    chicken: 'CK',
    nuggets: 'NG',
    fillet: 'FL',
    'rice-bowl': 'RB',
    pasta: 'PA',
    fries: 'FR',
    combo: 'CB',
    drink: 'DR',
    breakfast: 'BF',
    dessert: 'DS',
    sharing: 'SH',
  }

  return map[artwork] ?? 'MC'
}

const optionIndex = computed(() =>
  props.categories.flatMap((category) =>
    (category.items ?? []).flatMap((item) =>
      (item.options ?? []).map((option) => ({
        ...option,
        item_code: item.code,
        item_name: item.name,
        category_code: category.code,
        category_name: category.name,
      })),
    ),
  ).reduce((carry, option) => {
    carry[option.code] = option
    return carry
  }, {}),
)

const selectionSummary = computed(() =>
  props.modelValue
    .map((selection) => {
      const option = optionIndex.value[selection.option_code]

      if (!option) {
        return null
      }

      return {
        ...selection,
        item_name: option.item_name,
        option_label: option.label,
        unit_price: Number(option.price),
        category_name: option.category_name,
        line_total: Number(option.price) * Number(selection.quantity),
      }
    })
    .filter(Boolean),
)

const selectedItemsCount = computed(() =>
  selectionSummary.value.reduce((sum, item) => sum + Number(item.quantity), 0),
)

const selectedItemsTotal = computed(() =>
  selectionSummary.value.reduce((sum, item) => sum + Number(item.line_total), 0),
)

const activeCategory = computed(() =>
  props.categories.find((category) => category.code === selectedCategoryCode.value) ?? props.categories[0] ?? null,
)

const selectedCategoryItems = computed(() => activeCategory.value?.items ?? [])

const ensureSelections = () => {
  if (!props.categories.length) {
    selectedCategoryCode.value = ''
    return
  }

  if (!props.categories.some((category) => category.code === selectedCategoryCode.value)) {
    selectedCategoryCode.value = props.categories[0].code
  }

  props.categories.forEach((category) => {
    ;(category.items ?? []).forEach((item) => {
      if (!selectedOptionByItem[item.code] || !(item.options ?? []).some((option) => option.code === selectedOptionByItem[item.code])) {
        selectedOptionByItem[item.code] = item.options?.[0]?.code ?? ''
      }
    })
  })
}

watch(() => props.categories, ensureSelections, { immediate: true, deep: true })

const quantityFor = (optionCode) =>
  Number(props.modelValue.find((item) => item.option_code === optionCode)?.quantity ?? 0)

const setQuantity = (optionCode, quantity) => {
  const normalizedQuantity = Math.max(0, Math.min(Number(quantity), 99))
  const nextValue = props.modelValue
    .filter((item) => item.option_code !== optionCode)
    .map((item) => ({ ...item }))

  if (normalizedQuantity > 0) {
    nextValue.push({
      option_code: optionCode,
      quantity: normalizedQuantity,
    })
  }

  emit('update:modelValue', nextValue)
}

const increaseItem = (item) => {
  const optionCode = selectedOptionByItem[item.code] || item.options?.[0]?.code

  if (!optionCode) {
    return
  }

  setQuantity(optionCode, quantityFor(optionCode) + 1)
}

const decreaseItem = (item) => {
  const optionCode = selectedOptionByItem[item.code] || item.options?.[0]?.code

  if (!optionCode) {
    return
  }

  setQuantity(optionCode, quantityFor(optionCode) - 1)
}

const currentOption = (item) =>
  item.options?.find((option) => option.code === selectedOptionByItem[item.code]) ?? item.options?.[0] ?? null

const currentItemQuantity = (item) => {
  const option = currentOption(item)

  return option ? quantityFor(option.code) : 0
}

const viewTray = (event) => {
  event.currentTarget
    ?.closest('#manual-menu-board')
    ?.querySelector('#manual-menu-tray')
    ?.scrollIntoView({ behavior: 'smooth', block: 'start' })
}
</script>

<template>
  <section id="manual-menu-board" class="mcd-order-board">
    <div class="mcd-order-board__header">
      <div>
        <p class="text-sm font-black uppercase tracking-[0.2em] text-red-700">5. Manual foods and drinks</p>
        <h2 class="mt-3 text-3xl">Build the customer's tray like the McDonald's ordering app.</h2>
        <p class="mt-2 text-sm text-slate-500">Select a category, choose the meal size you want, and add exact quantities to the reservation.</p>
      </div>
      <div class="space-y-3">
        <div class="rounded-[1.75rem] bg-white px-5 py-4 shadow-sm">
          <p class="text-xs font-black uppercase tracking-[0.18em] text-slate-400">Tray total</p>
          <p class="mt-2 text-2xl font-black text-red-700">{{ formatCurrency(selectedItemsTotal) }}</p>
          <p class="mt-1 text-sm text-slate-500">{{ selectedItemsCount }} manually added item{{ selectedItemsCount === 1 ? '' : 's' }}</p>
        </div>
        <button type="button" class="mcd-button mcd-button--ghost w-full" :disabled="!selectionSummary.length" @click="viewTray">
          View added foods and drinks
        </button>
      </div>
    </div>

    <div class="mcd-order-board__categories">
      <button
        v-for="category in categories"
        :key="category.code"
        type="button"
        class="mcd-order-board__category"
        :class="{ 'is-active': selectedCategoryCode === category.code }"
        @click="selectedCategoryCode = category.code"
      >
        <span class="mcd-order-board__category-icon">{{ category.icon }}</span>
        <span class="mcd-order-board__category-label">{{ category.name }}</span>
      </button>
    </div>

    <div v-if="activeCategory" class="mcd-order-board__surface">
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h3 class="text-3xl">{{ activeCategory.name }}</h3>
          <p class="mt-2 max-w-3xl text-sm text-slate-500">{{ activeCategory.description }}</p>
        </div>
        <div class="rounded-full bg-emerald-50 px-4 py-2 text-sm font-bold text-emerald-700">
          {{ selectedCategoryItems.length }} items ready to add
        </div>
      </div>

      <div class="mt-6 grid gap-5 md:grid-cols-2 xl:grid-cols-4">
        <article v-for="item in selectedCategoryItems" :key="item.code" class="mcd-order-card">
          <div class="mcd-order-card__visual">
            <span>{{ artworkLabel(item.artwork) }}</span>
          </div>

          <div class="mcd-order-card__body">
            <div class="flex items-start justify-between gap-3">
              <div>
                <h4 class="text-xl leading-tight">{{ item.name }}</h4>
                <p v-if="item.badge" class="mt-2 inline-flex rounded-full bg-amber-50 px-3 py-1 text-[11px] font-black uppercase tracking-[0.16em] text-red-700">
                  {{ item.badge }}
                </p>
              </div>
              <p class="text-sm font-black text-red-700">
                {{ currentOption(item) ? formatCurrency(currentOption(item).price) : '' }}
              </p>
            </div>

            <p class="mt-3 text-sm leading-6 text-slate-600">{{ item.description }}</p>

            <div class="mt-4 flex flex-wrap gap-2">
              <button
                v-for="option in item.options"
                :key="option.code"
                type="button"
                class="mcd-order-card__option"
                :class="{ 'is-active': selectedOptionByItem[item.code] === option.code }"
                @click="selectedOptionByItem[item.code] = option.code"
              >
                <span>{{ option.label }}</span>
                <strong>{{ formatCurrency(option.price) }}</strong>
              </button>
            </div>

            <div class="mt-5 flex items-center justify-between gap-3">
              <div v-if="currentItemQuantity(item) > 0" class="mcd-order-card__stepper">
                <button type="button" @click="decreaseItem(item)">-</button>
                <span>{{ currentItemQuantity(item) }}</span>
                <button type="button" @click="increaseItem(item)">+</button>
              </div>
              <div v-else class="text-xs font-semibold uppercase tracking-[0.12em] text-slate-400">
                Tap add to include this option
              </div>

              <button type="button" class="mcd-order-card__add" @click="increaseItem(item)">
                {{ currentItemQuantity(item) > 0 ? 'Add more' : 'Add' }}
              </button>
            </div>

            <p v-if="currentItemQuantity(item) > 0 && currentOption(item)" class="mt-3 text-sm font-semibold text-slate-500">
              {{ currentItemQuantity(item) }} in tray | {{ formatCurrency(currentItemQuantity(item) * Number(currentOption(item).price)) }}
            </p>
          </div>
        </article>
      </div>
    </div>

    <div v-if="selectionSummary.length" id="manual-menu-tray" class="mcd-order-board__tray">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <p class="text-sm font-black uppercase tracking-[0.18em] text-red-700">Tray summary</p>
          <h3 class="mt-2 text-2xl">Manual order added to the reservation</h3>
        </div>
        <div class="rounded-full bg-white px-4 py-2 text-sm font-black text-slate-700 shadow-sm">
          {{ formatCurrency(selectedItemsTotal) }}
        </div>
      </div>

      <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
        <div v-for="item in selectionSummary" :key="item.option_code" class="rounded-3xl bg-white px-4 py-4 shadow-sm">
          <div class="flex items-start justify-between gap-3">
            <div>
              <p class="font-bold text-slate-800">{{ item.item_name }}</p>
              <p class="mt-1 text-sm text-slate-500">{{ item.option_label }} | {{ item.quantity }} qty</p>
            </div>
            <strong class="text-red-700">{{ formatCurrency(item.line_total) }}</strong>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
