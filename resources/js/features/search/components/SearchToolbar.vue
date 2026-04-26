<script setup>
const props = defineProps({
  modelValue: {
    type: String,
    default: '',
  },
  count: {
    type: Number,
    default: 0,
  },
  singularLabel: {
    type: String,
    default: 'record',
  },
  placeholder: {
    type: String,
    default: 'Search',
  },
  maxWidthClass: {
    type: String,
    default: '',
  },
  showCountText: {
    type: Boolean,
    default: true,
  },
})

const emit = defineEmits(['update:modelValue'])
</script>

<template>
  <div class="mcd-info-strip gap-4">
    <p v-if="showCountText" class="text-sm font-bold text-slate-500">
      {{ count }} {{ singularLabel }}{{ count === 1 ? '' : 's' }}
    </p>
    <input
      :value="modelValue"
      type="search"
      class="mcd-input"
      :class="maxWidthClass"
      :placeholder="placeholder"
      @input="emit('update:modelValue', $event.target.value)"
    />
  </div>
</template>
