<template>
  <div
    :class="[
      'bg-white border rounded-xl transition-all duration-200',
      glass ? 'bg-white/80 backdrop-blur-md border-slate-200/80 shadow-xs' : 'border-slate-200/90 shadow-2xs',
      hover ? 'hover:border-slate-300 hover:shadow-xs' : '',
      paddingClasses[padding] || paddingClasses.md
    ]"
  >
    <!-- Card Header opcional -->
    <div
      v-if="$slots.header || title"
      class="flex items-center justify-between pb-3 mb-3 border-b border-slate-100"
    >
      <div>
        <h3 v-if="title" class="text-xs font-semibold text-slate-900 tracking-tight">
          {{ title }}
        </h3>
        <p v-if="subtitle" class="text-[11px] text-slate-500 mt-0.5">
          {{ subtitle }}
        </p>
      </div>
      <div v-if="$slots.headerActions" class="flex items-center space-x-2">
        <slot name="headerActions" />
      </div>
    </div>

    <!-- Contenido Principal -->
    <slot />

    <!-- Card Footer opcional -->
    <div
      v-if="$slots.footer"
      class="pt-3 mt-3 border-t border-slate-100 text-xs text-slate-500"
    >
      <slot name="footer" />
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: {
    type: String,
    default: null,
  },
  subtitle: {
    type: String,
    default: null,
  },
  padding: {
    type: String,
    default: 'md', // none, sm, md, lg
  },
  glass: {
    type: Boolean,
    default: false,
  },
  hover: {
    type: Boolean,
    default: false,
  }
});

const paddingClasses = {
  none: 'p-0',
  xs: 'p-2.5',
  sm: 'p-3 sm:p-3.5',
  md: 'p-4 sm:p-5',
  lg: 'p-5 sm:p-6',
};
</script>

