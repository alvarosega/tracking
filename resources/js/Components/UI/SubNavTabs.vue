<template>
  <div class="inline-flex p-1 bg-slate-200/60 rounded-xl border border-slate-200/90 overflow-x-auto max-w-full">
    <Link
      v-for="tab in tabs"
      :key="tab.id || tab.route"
      :href="tab.route ? route(tab.route) : tab.href"
      class="px-3 py-1.5 text-xs font-medium rounded-lg transition-all flex items-center gap-1.5 whitespace-nowrap select-none"
      :class="isTabActive(tab)
        ? 'bg-white text-slate-900 shadow-2xs font-semibold'
        : 'text-slate-600 hover:text-slate-900 hover:bg-white/40'"
    >
      <!-- Icono si viene provisto -->
      <span v-if="tab.icon" class="w-3.5 h-3.5 shrink-0 opacity-70">
        <component :is="tab.icon" />
      </span>

      <span>{{ tab.name || tab.label }}</span>

      <!-- Badge de conteo si existe -->
      <span
        v-if="tab.count !== undefined"
        class="px-1.5 py-0.2 rounded-full text-[10px] font-mono tabular-nums"
        :class="isTabActive(tab) ? 'bg-slate-900 text-white' : 'bg-slate-300 text-slate-700'"
      >
        {{ tab.count }}
      </span>
    </Link>
  </div>
</template>

<script setup>
import { Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
  tabs: {
    type: Array,
    required: true,
  },
  currentTab: {
    type: String,
    default: null,
  }
});

const page = usePage();

const isTabActive = (tab) => {
  if (props.currentTab) {
    return props.currentTab === tab.id;
  }
  if (tab.component) {
    return page.component === tab.component;
  }
  if (tab.componentPrefix) {
    return page.component.startsWith(tab.componentPrefix);
  }
  if (tab.route) {
    try {
      return route().current(tab.route);
    } catch {
      return false;
    }
  }
  return false;
};
</script>

