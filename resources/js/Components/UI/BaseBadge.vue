<template>
  <span
    :class="[
      'inline-flex items-center font-medium select-none border transition-colors',
      sizeClasses[size] || sizeClasses.sm,
      variantClasses[variant] || variantClasses.neutral,
      roundedClass
    ]"
  >
    <!-- Dot indicator opcional -->
    <span
      v-if="dot"
      :class="[
        'rounded-full shrink-0 mr-1.5',
        dotSizes[size] || 'w-1.5 h-1.5',
        dotClasses[variant] || 'bg-slate-400',
        { 'animate-pulse': pulse }
      ]"
    ></span>

    <slot />
  </span>
</template>

<script setup>
const props = defineProps({
  variant: {
    type: String,
    default: 'neutral', // success, warning, danger, info, purple, neutral, dark
  },
  size: {
    type: String,
    default: 'sm', // xs, sm, md
  },
  dot: {
    type: Boolean,
    default: false,
  },
  pulse: {
    type: Boolean,
    default: false,
  },
  rounded: {
    type: String,
    default: 'md', // sm, md, lg, full
  }
});

const roundedClass = props.rounded === 'full' ? 'rounded-full' : `rounded-${props.rounded}`;

const sizeClasses = {
  xs: 'px-1.5 py-0.5 text-[10px] leading-tight font-mono',
  sm: 'px-2 py-0.5 text-[11px] leading-snug',
  md: 'px-2.5 py-1 text-xs',
};

const dotSizes = {
  xs: 'w-1 h-1',
  sm: 'w-1.5 h-1.5',
  md: 'w-2 h-2',
};

const variantClasses = {
  success: 'bg-emerald-50 text-emerald-700 border-emerald-200/80',
  warning: 'bg-amber-50 text-amber-700 border-amber-200/80',
  danger: 'bg-rose-50 text-rose-700 border-rose-200/80',
  info: 'bg-sky-50 text-sky-700 border-sky-200/80',
  purple: 'bg-purple-50 text-purple-700 border-purple-200/80',
  neutral: 'bg-slate-50 text-slate-600 border-slate-200/80',
  dark: 'bg-slate-900 text-slate-100 border-slate-800',
};

const dotClasses = {
  success: 'bg-emerald-500',
  warning: 'bg-amber-500',
  danger: 'bg-rose-500',
  info: 'bg-sky-500',
  purple: 'bg-purple-500',
  neutral: 'bg-slate-400',
  dark: 'bg-white',
};
</script>

