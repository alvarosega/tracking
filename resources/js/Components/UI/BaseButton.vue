<template>
  <component
    :is="as"
    :type="as === 'button' ? type : undefined"
    :disabled="disabled || loading"
    :href="href"
    :class="[
      'inline-flex items-center justify-center font-medium transition-all duration-150 select-none cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed disabled:pointer-events-none active:scale-[0.98]',
      sizeClasses[size] || sizeClasses.sm,
      variantClasses[variant] || variantClasses.secondary,
      roundedClass,
      { 'w-full': block }
    ]"
    v-bind="$attrs"
  >
    <!-- Spinner de Carga -->
    <svg
      v-if="loading"
      class="animate-spin -ml-0.5 mr-2 h-3.5 w-3.5"
      xmlns="http://www.w3.org/2000/svg"
      fill="none"
      viewBox="0 0 24 24"
    >
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
    </svg>

    <!-- Slot para icono a la izquierda -->
    <span v-if="$slots.icon && !loading" class="mr-1.5 shrink-0">
      <slot name="icon" />
    </span>

    <slot />

    <!-- Slot para icono a la derecha -->
    <span v-if="$slots.iconRight" class="ml-1.5 shrink-0">
      <slot name="iconRight" />
    </span>
  </component>
</template>

<script setup>
const props = defineProps({
  variant: {
    type: String,
    default: 'secondary', // primary, secondary, danger, ghost, glass, dark
  },
  size: {
    type: String,
    default: 'sm', // xs, sm, md, lg
  },
  type: {
    type: String,
    default: 'button',
  },
  as: {
    type: [String, Object],
    default: 'button',
  },
  href: {
    type: String,
    default: null,
  },
  disabled: {
    type: Boolean,
    default: false,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  block: {
    type: Boolean,
    default: false,
  },
  rounded: {
    type: String,
    default: 'lg',
  }
});

const roundedClass = `rounded-${props.rounded}`;

const sizeClasses = {
  xs: 'h-7 px-2.5 text-[11px] gap-1',
  sm: 'h-8 px-3 text-xs gap-1.5',
  md: 'h-9 px-4 text-xs gap-2',
  lg: 'h-10 px-5 text-sm gap-2',
};

const variantClasses = {
  primary: 'bg-slate-900 hover:bg-slate-800 text-white shadow-xs hover:shadow border border-slate-900',
  secondary: 'bg-white hover:bg-slate-50 text-slate-700 border border-slate-200/90 shadow-2xs hover:border-slate-300',
  accent: 'bg-sky-600 hover:bg-sky-500 text-white shadow-xs glow-accent border border-sky-600',
  danger: 'bg-rose-600 hover:bg-rose-500 text-white shadow-xs border border-rose-600',
  dangerGhost: 'bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200',
  ghost: 'bg-transparent hover:bg-slate-100/70 text-slate-600 hover:text-slate-900 border border-transparent',
  glass: 'bg-white/80 hover:bg-white backdrop-blur-xs text-slate-800 border border-slate-200/80 shadow-2xs',
};
</script>

