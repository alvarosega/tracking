<template>
  <div class="relative flex items-center">
    <select
      :value="modelValue"
      @change="$emit('update:modelValue', $event.target.value)"
      :disabled="disabled"
      :class="[
        'w-full bg-white border border-slate-200 text-slate-800 rounded-lg text-xs appearance-none pr-8 pl-3 transition-all shadow-2xs cursor-pointer',
        'focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500',
        'disabled:bg-slate-50 disabled:text-slate-400 disabled:cursor-not-allowed',
        sizeClasses[size] || sizeClasses.sm
      ]"
      v-bind="$attrs"
    >
      <slot />
    </select>

    <!-- Flecha personalizada sutil -->
    <div class="absolute right-2.5 pointer-events-none text-slate-400">
      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
      </svg>
    </div>
  </div>
</template>

<script setup>
defineProps({
  modelValue: {
    type: [String, Number],
    default: '',
  },
  size: {
    type: String,
    default: 'sm', // xs, sm, md
  },
  disabled: {
    type: Boolean,
    default: false,
  }
});

defineEmits(['update:modelValue']);

const sizeClasses = {
  xs: 'h-7 text-[11px]',
  sm: 'h-8 text-xs',
  md: 'h-9 text-sm',
};
</script>

