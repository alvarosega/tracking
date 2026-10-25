<template>
  <div class="inline-flex p-1 apple-control-pill rounded-[12px] overflow-x-auto no-scrollbar scroll-touch max-w-full">
    <Link
      v-for="tab in tabs"
      :key="tab.id || tab.route"
      :href="tab.route ? route(tab.route) : tab.href"
      class="px-3 py-1 text-xs font-medium rounded-[9px] transition-all flex items-center gap-1.5 whitespace-nowrap select-none active:scale-95"
      :class="isTabActive(tab)
        ? 'glass-active-pill font-bold shadow-xs'
        : 'text-[#6E6E73] hover:text-[#1D1D1F] hover:bg-white/50'"
    >
      <!-- Icono si viene provisto -->
      <span v-if="tab.icon" class="w-3.5 h-3.5 shrink-0 opacity-70">
        <component :is="tab.icon" />
      </span>

      <span>{{ tab.name || tab.label }}</span>

      <!-- Badge de conteo si existe -->
      <span
        v-if="tab.count !== undefined"
        class="px-1.5 py-0.2 rounded-full text-[10px] font-num"
        :class="isTabActive(tab) ? 'bg-[#0071E3] text-white' : 'bg-black/[0.06] text-[#6E6E73]'"
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

