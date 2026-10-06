<template>
  <div
    :class="[
      'bg-white border rounded-xl p-3.5 sm:p-4 transition-all duration-200 shadow-2xs relative overflow-hidden group',
      borderClass || 'border-slate-200/90 hover:border-slate-300'
    ]"
  >
    <!-- Barra sutil superior de acento si se especifica -->
    <div
      v-if="accentColor"
      :class="['absolute top-0 left-0 right-0 h-[2px]', accentColorClass]"
    ></div>

    <div class="flex items-start justify-between">
      <div class="space-y-1">
        <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 font-mono">
          {{ label }}
        </span>
        <div class="flex items-baseline space-x-2">
          <span class="text-xl sm:text-2xl font-semibold text-slate-900 font-mono tracking-tight tabular-nums">
            {{ value }}
          </span>
          <span v-if="unit" class="text-xs text-slate-500 font-medium">
            {{ unit }}
          </span>
        </div>
      </div>

      <!-- Icono o badge indicador -->
      <div
        v-if="$slots.icon || iconBg"
        class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 border"
        :class="iconBg || 'bg-slate-50 border-slate-200 text-slate-600'"
      >
        <slot name="icon" />
      </div>
    </div>

    <!-- Descripción o subtexto -->
    <div v-if="description || $slots.footer" class="mt-2.5 pt-2 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
      <span>{{ description }}</span>
      <slot name="footer" />
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  label: {
    type: String,
    required: true,
  },
  value: {
    type: [String, Number],
    required: true,
  },
  unit: {
    type: String,
    default: null,
  },
  description: {
    type: String,
    default: null,
  },
  accentColor: {
    type: String,
    default: null, // sky, emerald, amber, rose, slate
  },
  iconBg: {
    type: String,
    default: null,
  },
  borderClass: {
    type: String,
    default: null,
  }
});

const accentColorClass = computed(() => {
  const map = {
    sky: 'bg-sky-500',
    emerald: 'bg-emerald-500',
    amber: 'bg-amber-500',
    rose: 'bg-rose-500',
    slate: 'bg-slate-700',
  };
  return map[props.accentColor] || 'bg-slate-900';
});
</script>

