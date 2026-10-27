<template>
  <SupervisorLayout title="Panel General">
    <div class="space-y-5 max-w-7xl mx-auto pb-12 font-sans">
      <!-- Encabezado de Bienvenida / Contexto -->
      <PageHeader
        title="Panel de Control & Supervisión"
        :subtitle="`Sesión activa: ${$page.props.auth?.user?.username || 'Supervisor'} | Operación comercial en tiempo real`"
        badge="SISTEMA CENTRAL"
        badgeColor="slate"
      >
        <template #actions>
          <div class="flex items-center space-x-2 text-xs font-mono">
            <span class="px-2.5 py-1 bg-white border border-slate-200/90 rounded-lg text-slate-700 shadow-2xs">
              {{ contexto.dia_semana }}, {{ contexto.fecha }}
            </span>
            <span class="px-2.5 py-1 bg-white border border-slate-200/90 rounded-lg font-semibold text-slate-900 shadow-2xs tabular-nums">
              {{ contexto.hora }}
            </span>
          </div>
        </template>
      </PageHeader>

      <!-- Módulos de Operación en General -->
      <div class="space-y-3">
        <div class="flex items-center justify-between px-1">
          <div class="flex items-center gap-2">
            <h2 class="text-xs font-bold uppercase tracking-wider text-[#86868B] font-mono">
              Módulos del Sistema
            </h2>
            <span class="text-[10px] font-mono font-semibold text-[#86868B] bg-black/[0.04] px-2 py-0.5 rounded-full">
              {{ operationModules.length }} disponibles
            </span>
          </div>
          <span class="text-[11px] text-[#86868B] font-mono hidden sm:inline">
            Acceso Directo
          </span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5 sm:gap-4">
          <Link
            v-for="module in operationModules"
            :key="module.id"
            :href="getRoute(module)"
            class="group bg-white border border-slate-200/90 hover:border-[#0071E3]/50 rounded-[18px] p-4 sm:p-5 transition-all duration-200 shadow-2xs hover:shadow-md flex flex-col justify-between cursor-pointer"
          >
            <div>
              <div class="flex items-center justify-between mb-3">
                <!-- Icono del Módulo -->
                <div class="w-9 h-9 rounded-[12px] bg-[#0071E3]/10 text-[#0071E3] flex items-center justify-center shrink-0 group-hover:bg-[#0071E3] group-hover:text-white transition-all shadow-2xs">
                  <!-- Auditoría / Saneamiento / Ruteo -->
                  <svg v-if="module.id === 'auditoria' || module.id === 'saneamiento' || module.id === 'ruteo'" class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                  </svg>
                  <!-- Avance -->
                  <svg v-else-if="module.id === 'avance'" class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941" />
                  </svg>
                  <!-- Preventas -->
                  <svg v-else-if="module.id === 'preventas'" class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25z" />
                  </svg>
                  <!-- Tracking -->
                  <svg v-else-if="module.id === 'tracking' || module.id === 'visitas'" class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0zM19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                  </svg>
                  <!-- Altas & Ediciones -->
                  <svg v-else-if="module.id === 'altas-ediciones'" class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.765z" />
                  </svg>
                  <!-- Jornadas / Horarios / Modulos -->
                  <svg v-else-if="module.id === 'jornadas' || module.id === 'horarios' || module.id === 'modulos'" class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                  </svg>
                  <!-- Ingesta -->
                  <svg v-else-if="module.id === 'ingesta'" class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                  </svg>
                  <!-- Permisos / Admin -->
                  <svg v-else-if="module.id === 'admin-permisos'" class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
                  </svg>
                  <svg v-else class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                  </svg>
                </div>

                <div class="flex items-center gap-1.5">
                  <span
                    v-if="module.badge"
                    class="text-[9.5px] font-mono font-bold px-2 py-0.5 rounded bg-black/[0.04] text-[#1D1D1F] border border-black/[0.06] group-hover:bg-[#0071E3]/10 group-hover:text-[#0071E3] group-hover:border-[#0071E3]/20 transition-colors"
                  >
                    {{ module.badge }}
                  </span>
                  <span class="text-slate-400 group-hover:text-[#0071E3] group-hover:translate-x-0.5 transition-all text-sm font-bold">
                    &rarr;
                  </span>
                </div>
              </div>

              <h3 class="text-sm font-bold text-[#1D1D1F] tracking-tight group-hover:text-[#0071E3] transition-colors">
                {{ module.name }}
              </h3>
              <p class="text-[11.5px] text-[#86868B] mt-1 leading-relaxed">
                {{ module.description }}
              </p>
            </div>

            <div class="mt-4 pt-3 border-t border-black/[0.05] flex items-center justify-between text-[11px] text-[#86868B]">
              <span class="font-medium text-[#1D1D1F] group-hover:text-[#0071E3] transition-colors">
                Ingresar al módulo
              </span>
              <span v-if="module.submodules && module.submodules.length > 0" class="font-mono text-[10px] text-[#86868B]">
                {{ module.submodules.length }} submódulos
              </span>
              <span v-else class="font-mono text-[10px] text-[#86868B]">
                Módulo directo
              </span>
            </div>
          </Link>
        </div>
      </div>
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import { navigationConfig } from '@/Config/navigation';

defineProps({
  contexto: {
    type: Object,
    required: true,
  },
});

const page = usePage();
const authUser = computed(() => page.props.auth?.user);
const isSuperAdmin = computed(() => !!authUser.value?.is_superadmin);
const perms = computed(() => authUser.value?.permissions);

function isModuleAllowed(item) {
  if (item.superAdminOnly) {
    return isSuperAdmin.value;
  }
  if (isSuperAdmin.value) return true;
  if (!perms.value) return false;
  if (perms.value.is_all_allowed) return true;

  const requiredModules = item.permissionModules || [item.id];
  const hasAnyMod = requiredModules.some(m => perms.value.modules && perms.value.modules[m] === true);
  if (!hasAnyMod) return false;

  if (item.submodules && item.submodules.length > 0) {
    return item.submodules.some(sub => {
      const parentMod = sub.module || item.id;
      return perms.value.modules?.[parentMod] === true && perms.value.submodules?.[`${parentMod}.${sub.id}`] === true;
    });
  }
  return true;
}

const operationModules = computed(() => {
  return navigationConfig.mainMenu.filter(m => m.id !== 'inicio' && isModuleAllowed(m));
});

const getRoute = (module) => {
  if (module.submodules && module.submodules.length > 0) {
    if (isSuperAdmin.value || perms.value?.is_all_allowed) {
      return route(module.submodules[0].route);
    }
    const allowedSub = module.submodules.find(sub => {
      const parentMod = sub.module || module.id;
      return perms.value?.modules?.[parentMod] === true && perms.value?.submodules?.[`${parentMod}.${sub.id}`] === true;
    });
    if (allowedSub) {
      return route(allowedSub.route);
    }
  }
  try {
    return route(module.route);
  } catch {
    return `/${module.route.replace('.', '/')}`;
  }
};
</script>