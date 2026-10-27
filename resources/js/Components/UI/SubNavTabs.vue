<template>
  <div v-if="filteredTabs.length > 0" class="inline-flex p-1 apple-control-pill rounded-[12px] overflow-x-auto no-scrollbar scroll-touch max-w-full">
    <Link
      v-for="tab in filteredTabs"
      :key="tab.id || tab.route"
      :href="resolveTabHref(tab)"
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
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

const props = defineProps({
  tabs: {
    type: Array,
    required: true,
  },
  currentTab: {
    type: String,
    default: null,
  },
  moduleId: {
    type: String,
    default: null,
  },
});

const page = usePage();

const user = computed(() => page.props.auth?.user);
const isSuperAdmin = computed(() => !!user.value?.is_superadmin);
const perms = computed(() => user.value?.permissions);

function isTabAllowed(tab) {
  if (isSuperAdmin.value) return true;
  if (!perms.value) return false;
  if (perms.value.is_all_allowed) return true;

  let moduleKey = props.moduleId || tab.module;
  let submoduleKey = tab.id;

  if (!moduleKey && tab.route) {
    const parts = tab.route.split('.');
    if (parts.length >= 2 && parts[0] === 'supervisor') {
      moduleKey = parts[1];
      if (!submoduleKey && parts.length >= 3) {
        submoduleKey = parts[2];
      }
    }
  }

  if (!moduleKey) return true;

  // 1. Módulo principal debe estar activo
  if (!perms.value.modules || perms.value.modules[moduleKey] !== true) {
    return false;
  }

  // 2. Submódulo específico: si está definido, debe estar explícitamente en true
  if (submoduleKey) {
    const fullKey = `${moduleKey}.${submoduleKey}`;
    if (perms.value.submodules && Object.prototype.hasOwnProperty.call(perms.value.submodules, fullKey)) {
      return perms.value.submodules[fullKey] === true;
    }
    return false;
  }

  return true;
}

const filteredTabs = computed(() => {
  return props.tabs.filter(tab => isTabAllowed(tab));
});

const resolveTabHref = (tab) => {
  if (tab.href) return tab.href;
  if (!tab.route) return '#';
  try {
    if (typeof route === 'function') {
      return route(tab.route);
    }
  } catch (e) {
    console.warn(`[SubNavTabs] Error resolviendo ruta '${tab.route}':`, e);
  }
  return '#';
};

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

