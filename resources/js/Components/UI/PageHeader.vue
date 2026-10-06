<template>
  <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-4 sm:pb-5 border-b border-slate-200/80 gap-3">
    <div class="space-y-0.5">
      <div class="flex items-center space-x-2">
        <span
          v-if="badge"
          class="text-[10px] font-mono font-semibold px-2 py-0.5 rounded-md uppercase tracking-wider border"
          :class="badgeColorClass"
        >
          {{ badge }}
        </span>
        <h1 class="text-lg sm:text-xl font-bold tracking-tight text-slate-900">
          {{ title }}
        </h1>
      </div>
      <p v-if="subtitle" class="text-xs text-slate-500">
        {{ subtitle }}
      </p>
    </div>

    <!-- Acciones y Chips en el extremo derecho -->
    <div class="flex items-center flex-wrap gap-2">
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  title: {
    type: String,
    required: true,
  },
  subtitle: {
    type: String,
    default: null,
  },
  badge: {
    type: String,
    default: null,
  },
  badgeColor: {
    type: String,
    default: 'slate', // slate, emerald, sky, amber, rose
  }
});

const badgeColorClass = computed(() => {
  const map = {
    slate: 'bg-slate-100 text-slate-700 border-slate-200',
    emerald: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    sky: 'bg-sky-50 text-sky-700 border-sky-200',
    amber: 'bg-amber-50 text-amber-700 border-amber-200',
    rose: 'bg-rose-50 text-rose-700 border-rose-200',
  };
  return map[props.badgeColor] || map.slate;
});
</script>

