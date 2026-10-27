<template>
  <div v-if="allAllowedTabs.length > 0" :class="compact ? 'space-y-1.5 select-none font-sans' : 'space-y-2 mb-3.5 select-none font-sans'">
    <!-- NIVEL 1: CONMUTADOR DE SECCIÓN (APPLE LIQUID GLASS SEGMENTED CONTROL) -->
    <div
      v-if="accessibleSectionsCount > 1"
      class="apple-glass-card rounded-[14px] p-1 flex items-center justify-between gap-2 max-w-full overflow-x-auto no-scrollbar border border-white/80 shadow-2xs"
    >
      <div class="flex items-center gap-1 min-w-max">
        <!-- Segmento 1: Auditoría de Ruteo -->
        <Link
          v-if="hasAuditoriaAccess"
          :href="firstAuditoriaRoute"
          class="flex items-center gap-2 px-3 py-1.5 rounded-[10px] text-xs font-semibold transition-all duration-150 cursor-pointer"
          :class="currentSection === 'auditoria'
            ? 'bg-[#0071E3] text-white shadow-[0_2px_8px_rgba(0,113,227,0.35)]'
            : 'text-[#6E6E73] hover:text-[#1D1D1F] hover:bg-black/[0.04]'"
        >
          <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
          </svg>
          <span>Auditoría de Ruteo</span>
          <span
            class="text-[10px] font-mono px-1.5 py-0.2 rounded-full font-bold"
            :class="currentSection === 'auditoria' ? 'bg-white/25 text-white' : 'bg-black/[0.06] text-[#6E6E73]'"
          >
            {{ allowedAuditoriaTabs.length }}
          </span>
        </Link>

        <!-- Segmento 2: Saneamiento Padrón -->
        <Link
          v-if="hasSaneamientoAccess"
          :href="firstSaneamientoRoute"
          class="flex items-center gap-2 px-3 py-1.5 rounded-[10px] text-xs font-semibold transition-all duration-150 cursor-pointer"
          :class="currentSection === 'saneamiento'
            ? 'bg-[#0071E3] text-white shadow-[0_2px_8px_rgba(0,113,227,0.35)]'
            : 'text-[#6E6E73] hover:text-[#1D1D1F] hover:bg-black/[0.04]'"
        >
          <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689A1.125 1.125 0 003 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z" />
          </svg>
          <span>Saneamiento Padrón</span>
          <span
            class="text-[10px] font-mono px-1.5 py-0.2 rounded-full font-bold"
            :class="currentSection === 'saneamiento' ? 'bg-white/25 text-white' : 'bg-black/[0.06] text-[#6E6E73]'"
          >
            {{ allowedSaneamientoTabs.length }}
          </span>
        </Link>

        <!-- Segmento 3: Ruteo & Cobertura -->
        <Link
          v-if="hasRuteoAccess"
          :href="firstRuteoRoute"
          class="flex items-center gap-2 px-3 py-1.5 rounded-[10px] text-xs font-semibold transition-all duration-150 cursor-pointer"
          :class="currentSection === 'ruteo'
            ? 'bg-[#0071E3] text-white shadow-[0_2px_8px_rgba(0,113,227,0.35)]'
            : 'text-[#6E6E73] hover:text-[#1D1D1F] hover:bg-black/[0.04]'"
        >
          <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0zM19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
          </svg>
          <span>Ruteo & Cobertura</span>
          <span
            class="text-[10px] font-mono px-1.5 py-0.2 rounded-full font-bold"
            :class="currentSection === 'ruteo' ? 'bg-white/25 text-white' : 'bg-black/[0.06] text-[#6E6E73]'"
          >
            {{ allowedRuteoTabs.length }}
          </span>
        </Link>
      </div>

      <div class="hidden sm:flex items-center text-[10px] font-mono text-[#86868B] px-2 font-semibold">
        Módulo Unificado • {{ sectionSubtitle }}
      </div>
    </div>

    <!-- NIVEL 2: PESTAÑAS DEL SUBMÓDULO ACTIVO -->
    <div
      v-if="currentTabsList.length > 0"
      class="apple-glass-card rounded-[14px] p-1.5 overflow-x-auto no-scrollbar scroll-touch border border-white/70 shadow-2xs"
    >
      <div class="flex items-center gap-1 min-w-max">
        <Link
          v-for="tab in currentTabsList"
          :key="tab.id"
          :href="route(tab.route)"
          class="flex items-center gap-1.5 px-3 py-1.5 rounded-[9px] text-[11px] font-semibold transition-all duration-150 cursor-pointer"
          :class="activeTab === tab.id
            ? 'bg-[#0071E3] text-white shadow-[0_2px_8px_rgba(0,113,227,0.35)]'
            : 'text-[#6E6E73] hover:text-[#1D1D1F] hover:bg-black/[0.04]'"
        >
          <!-- 1. Preventas -->
          <svg v-if="tab.id === 'preventas'" class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
          </svg>

          <!-- 2. Inactivados -->
          <svg v-else-if="tab.id === 'inactivados'" class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M22 10.5h-6m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.765z" />
          </svg>

          <!-- 3. Cambios de Ruta -->
          <svg v-else-if="tab.id === 'cambios-ruta'" class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5" />
          </svg>

          <!-- 4. Modificación de Datos -->
          <svg v-else-if="tab.id === 'modificacion-datos'" class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
          </svg>

          <!-- 5. Cambios de Día -->
          <svg v-else-if="tab.id === 'cambios-dia'" class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
          </svg>

          <!-- 6. Altas y Reactivaciones -->
          <svg v-else-if="tab.id === 'altas'" class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.765z" />
          </svg>

          <!-- 7. Histórico Resoluciones -->
          <svg v-else-if="tab.id === 'historico'" class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>

          <!-- 8. Fronteras e Intrusos -->
          <svg v-else-if="tab.id === 'intrusos'" class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934c-.317.159-.69.159-1.006 0L9.503 3.252a1.125 1.125 0 00-1.006 0L3.622 5.689A1.125 1.125 0 003 6.695V19.18c0 .836.88 1.38 1.628 1.006l3.869-1.934c.317-.159.69-.159 1.006 0l4.994 2.497c.317.158.69.158 1.006 0z" />
          </svg>

          <!-- 9. Clientes Duplicados -->
          <svg v-else-if="tab.id === 'duplicados'" class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
          </svg>

          <!-- 10. Días Desalineados -->
          <svg v-else-if="tab.id === 'dias'" class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
          </svg>

          <!-- 11. Calidad de Datos -->
          <svg v-else-if="tab.id === 'calidad'" class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>

          <!-- 12. Matriz BD -->
          <svg v-else-if="tab.id === 'bd'" class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 5.625c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125m16.5 5.625c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
          </svg>

          <!-- 13. Historial de Propuestas -->
          <svg v-else-if="tab.id === 'propuestas'" class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M11.35 3.836c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m8.9-4.414c.376.023.75.05 1.124.08 1.131.09 1.976 1.052 1.976 2.188V8.25M8.25 8.25H6.75A2.25 2.25 0 004.5 10.5v9A2.25 2.25 0 006.75 21.75h10.5A2.25 2.25 0 0019.5 19.5v-9A2.25 2.25 0 0017.25 8.25H15.75m-7.5 0h7.5m-7.5 3h7.5m-7.5 3h5.25" />
          </svg>

          <!-- 14. Visor y Fronteras -->
          <svg v-else-if="tab.id === 'visor'" class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934a1.12 1.12 0 01-1.006 0L9.503 3.31a1.12 1.12 0 00-1.006 0L3.623 5.748A1.125 1.125 0 003 6.754v12.37c0 .836.88 1.38 1.628 1.006l3.869-1.934a1.12 1.12 0 011.006 0l4.994 2.497a1.12 1.12 0 001.006 0z" />
          </svg>

          <!-- 15. Clientes Cercanos -->
          <svg v-else class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 100-18 9 9 0 000 18zm0-6a3 3 0 100-6 3 3 0 000 6z" />
          </svg>

          <span>{{ tab.name }}</span>
        </Link>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { auditoriaSubTabs, saneamientoSubTabs, ruteoSubTabs } from '@/Config/navigation';

const props = defineProps({
  activeTab: {
    type: String,
    required: true,
  },
  compact: {
    type: Boolean,
    default: false,
  },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const isSuperAdmin = computed(() => !!user.value?.is_superadmin);
const perms = computed(() => user.value?.permissions);

function isSubAllowed(moduleKey, submoduleId) {
  if (isSuperAdmin.value) return true;
  if (!perms.value) return false;
  if (perms.value.is_all_allowed) return true;

  if (!perms.value.modules || perms.value.modules[moduleKey] !== true) {
    return false;
  }
  const fullKey = `${moduleKey}.${submoduleId}`;
  if (perms.value.submodules && Object.prototype.hasOwnProperty.call(perms.value.submodules, fullKey)) {
    return perms.value.submodules[fullKey] === true;
  }
  return false;
}

const allowedAuditoriaTabs = computed(() => {
  return auditoriaSubTabs.filter((tab) => isSubAllowed('auditoria', tab.id));
});

const allowedSaneamientoTabs = computed(() => {
  return saneamientoSubTabs.filter((tab) => isSubAllowed('saneamiento', tab.id));
});

const allowedRuteoTabs = computed(() => {
  return ruteoSubTabs.filter((tab) => isSubAllowed('ruteo', tab.id));
});

const hasAuditoriaAccess = computed(() => allowedAuditoriaTabs.value.length > 0);
const hasSaneamientoAccess = computed(() => allowedSaneamientoTabs.value.length > 0);
const hasRuteoAccess = computed(() => allowedRuteoTabs.value.length > 0);

const accessibleSectionsCount = computed(() => {
  return (hasAuditoriaAccess.value ? 1 : 0) +
         (hasSaneamientoAccess.value ? 1 : 0) +
         (hasRuteoAccess.value ? 1 : 0);
});

const allAllowedTabs = computed(() => [
  ...allowedAuditoriaTabs.value,
  ...allowedSaneamientoTabs.value,
  ...allowedRuteoTabs.value,
]);

const currentSection = computed(() => {
  const isAuditoriaTab = auditoriaSubTabs.some((t) => t.id === props.activeTab);
  if (isAuditoriaTab && hasAuditoriaAccess.value) return 'auditoria';

  const isSaneamientoTab = saneamientoSubTabs.some((t) => t.id === props.activeTab);
  if (isSaneamientoTab && hasSaneamientoAccess.value) return 'saneamiento';

  const isRuteoTab = ruteoSubTabs.some((t) => t.id === props.activeTab);
  if (isRuteoTab && hasRuteoAccess.value) return 'ruteo';

  if (hasAuditoriaAccess.value) return 'auditoria';
  if (hasSaneamientoAccess.value) return 'saneamiento';
  if (hasRuteoAccess.value) return 'ruteo';
  return 'auditoria';
});

const sectionSubtitle = computed(() => {
  if (currentSection.value === 'auditoria') return 'Auditoría Intra-día';
  if (currentSection.value === 'saneamiento') return 'Calidad & GIS';
  return 'Motor GIS & Cobertura';
});

const currentTabsList = computed(() => {
  if (currentSection.value === 'auditoria') return allowedAuditoriaTabs.value;
  if (currentSection.value === 'saneamiento') return allowedSaneamientoTabs.value;
  return allowedRuteoTabs.value;
});

const firstAuditoriaRoute = computed(() => {
  if (allowedAuditoriaTabs.value.length > 0) {
    return route(allowedAuditoriaTabs.value[0].route);
  }
  return route('supervisor.auditoria.preventas');
});

const firstSaneamientoRoute = computed(() => {
  if (allowedSaneamientoTabs.value.length > 0) {
    return route(allowedSaneamientoTabs.value[0].route);
  }
  return route('supervisor.saneamiento.intrusos');
});

const firstRuteoRoute = computed(() => {
  if (allowedRuteoTabs.value.length > 0) {
    return route(allowedRuteoTabs.value[0].route);
  }
  return route('supervisor.saneamiento.intrusos');
});
</script>

