<template>
  <div class="relative flex items-center">
    <!-- Icono a la izquierda -->
    <div
      v-if="$slots.icon || icon"
      class="absolute left-2.5 flex items-center pointer-events-none text-slate-400"
    >
      <slot name="icon">
        <svg v-if="icon === 'search'" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
        </svg>
      </slot>
    </div>

    <input
      :value="modelValue"
      @input="$emit('update:modelValue', $event.target.value)"
      :type="type"
      :placeholder="placeholder"
      :disabled="disabled"
      :class="[
        'w-full bg-white border border-slate-200 text-slate-900 rounded-lg text-xs placeholder:text-slate-400 transition-all shadow-2xs',
        'focus:outline-none focus:ring-2 focus:ring-sky-500/20 focus:border-sky-500',
        'disabled:bg-slate-50 disabled:text-slate-400 disabled:cursor-not-allowed',
        sizeClasses[size] || sizeClasses.sm,
        ($slots.icon || icon) ? 'pl-8' : 'pl-3',
        (clearable && modelValue) ? 'pr-8' : 'pr-3'
      ]"
      v-bind="$attrs"
    />

    <!-- Botón de Limpiar si tiene valor -->
    <button
      v-if="clearable && modelValue"
      type="button"
      @click="$emit('update:modelValue', '')"
      class="absolute right-2 p-0.5 text-slate-400 hover:text-slate-600 rounded-md transition-colors"
      title="Limpiar"
    >
      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
      </svg>
    </button>
  </div>
</template>

<script setup>
defineProps({
  modelValue: {
    type: [String, Number],
    default: '',
  },
  placeholder: {
    type: String,
    default: '',
  },
  type: {
    type: String,
    default: 'text',
  },
  size: {
    type: String,
    default: 'sm', // xs, sm, md
  },
  icon: {
    type: String,
    default: null,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  clearable: {
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

