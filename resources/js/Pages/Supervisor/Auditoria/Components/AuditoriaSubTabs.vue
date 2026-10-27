<template>
  <div v-if="filteredTabs.length > 0" class="apple-glass-card rounded-[16px] p-1.5 sm:p-2 mb-3 select-none font-sans overflow-x-auto no-scrollbar scroll-touch">
    <div class="flex items-center gap-1 min-w-max">
      <Link
        v-for="tab in filteredTabs"
        :key="tab.id"
        :href="route(tab.route)"
        class="flex items-center gap-2 px-3 py-1.5 rounded-[10px] text-[11px] font-semibold transition-all duration-150 cursor-pointer"
        :class="activeTab === tab.id
          ? 'bg-[#0071E3] text-white shadow-[0_2px_8px_rgba(0,113,227,0.35)]'
          : 'text-[#6E6E73] hover:text-[#1D1D1F] hover:bg-black/[0.04]'"
      >
        <!-- Preventas -->
        <svg v-if="tab.id === 'preventas'" class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
        </svg>

        <!-- Inactivados -->
        <svg v-else-if="tab.id === 'inactivados'" class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M22 10.5h-6m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.765z" />
        </svg>

        <!-- Cambios de Ruta -->
        <svg v-else-if="tab.id === 'cambios-ruta'" class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
        </svg>

        <!-- Modificación de Datos -->
        <svg v-else-if="tab.id === 'modificacion-datos'" class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
        </svg>

        <!-- Cambios de Día -->
        <svg v-else-if="tab.id === 'cambios-dia'" class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
        </svg>

        <!-- Altas y Reactivaciones -->
        <svg v-else-if="tab.id === 'altas'" class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.765z" />
        </svg>

        <!-- Histórico Resoluciones -->
        <svg v-else class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>

        <span>{{ tab.name }}</span>
      </Link>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';

defineProps({
  activeTab: {
    type: String,
    required: true,
  },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const isSuperAdmin = computed(() => !!user.value?.is_superadmin);
const permissions = computed(() => user.value?.permissions);

const allTabs = [
  { id: 'preventas', name: 'Preventas Intra-día', route: 'supervisor.auditoria.preventas' },
  { id: 'inactivados', name: 'Bajas e Inactivados', route: 'supervisor.auditoria.inactivados' },
  { id: 'cambios-ruta', name: 'Cambios de Ruta', route: 'supervisor.auditoria.cambios-ruta' },
  { id: 'modificacion-datos', name: 'Modificación Datos', route: 'supervisor.auditoria.modificacion-datos' },
  { id: 'cambios-dia', name: 'Cambios de Día', route: 'supervisor.auditoria.cambios-dia' },
  { id: 'altas', name: 'Altas y Reactivaciones', route: 'supervisor.auditoria.altas' },
  { id: 'historico', name: 'Histórico Resoluciones', route: 'supervisor.auditoria.historico' },
];

function isTabAllowed(tabId) {
  if (isSuperAdmin.value) return true;
  if (!permissions.value) return false;
  if (permissions.value.is_all_allowed) return true;

  const allowedMods = permissions.value.allowed_modules;
  if (!Array.isArray(allowedMods)) return true;

  const audEntry = allowedMods.find((m) => m === 'auditoria' || (typeof m === 'object' && m.id === 'auditoria'));
  if (!audEntry) return false;

  if (typeof audEntry === 'string') return true;
  if (!Array.isArray(audEntry.submodules) || audEntry.submodules.length === 0) return true;

  return audEntry.submodules.includes(tabId);
}

const filteredTabs = computed(() => allTabs.filter((tab) => isTabAllowed(tab.id)));
</script>

