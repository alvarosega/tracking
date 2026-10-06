<template>
  <SupervisorLayout>
    <div class="space-y-6">
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

      <!-- Métricas y Estado Operativo de Plataforma -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5 sm:gap-4">
        <StatCard
          label="Fuerza Comercial"
          :value="contexto.total_fuerza_ventas"
          unit="vendedores"
          description="Vendedores con rutas asignadas"
          accentColor="sky"
        >
          <template #icon>
            <svg class="w-4 h-4 text-sky-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
            </svg>
          </template>
        </StatCard>

        <BaseCard padding="sm" class="relative overflow-hidden group">
          <div class="space-y-1.5">
            <div class="flex items-center justify-between">
              <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 font-mono">Conexión Primaria</span>
              <BaseBadge variant="success" size="xs" dot pulse>ACTIVA</BaseBadge>
            </div>
            <div class="flex items-center space-x-2 pt-1">
              <span class="text-sm font-mono font-semibold text-slate-900">u967339252_alva</span>
            </div>
            <p class="text-[11px] text-slate-500 font-mono">Hostinger srv1894 (Producción)</p>
          </div>
        </BaseCard>

        <BaseCard padding="sm" class="relative overflow-hidden group">
          <div class="space-y-1.5">
            <div class="flex items-center justify-between">
              <span class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 font-mono">Base Supervisión</span>
              <BaseBadge variant="success" size="xs" dot pulse>ACTIVA</BaseBadge>
            </div>
            <div class="flex items-center space-x-2 pt-1">
              <span class="text-sm font-mono font-semibold text-slate-900">u967339252_supervisor</span>
            </div>
            <p class="text-[11px] text-slate-500 font-mono">Hostinger srv1894 (Transaccional)</p>
          </div>
        </BaseCard>
      </div>

      <!-- Módulos de Operación Futuristas -->
      <div class="space-y-3">
        <div class="flex items-center justify-between">
          <h2 class="text-xs font-bold uppercase tracking-wider text-slate-500 font-mono">Módulos de Operación</h2>
          <span class="text-[11px] text-slate-400 font-mono">Sede El Alto</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3.5">
          <Link
            v-for="module in operationModules"
            :key="module.id"
            :href="getRoute(module.route)"
            class="group bg-white border border-slate-200/90 hover:border-slate-400/80 rounded-xl p-4 transition-all duration-200 shadow-2xs hover:shadow-sm flex flex-col justify-between"
          >
            <div>
              <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-700 border border-slate-200 group-hover:bg-slate-900 group-hover:text-white transition-colors">
                  {{ module.badge || 'MÓDULO' }}
                </span>
                <span class="text-slate-400 group-hover:text-slate-900 group-hover:translate-x-0.5 transition-all text-sm font-bold">
                  &rarr;
                </span>
              </div>
              <h3 class="text-sm font-bold text-slate-900 tracking-tight group-hover:text-sky-600 transition-colors">
                {{ module.name }}
              </h3>
              <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                {{ module.description }}
              </p>
            </div>

            <div class="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
              <span class="font-medium text-slate-700">Abrir módulo</span>
              <span class="font-mono text-[10px] text-slate-400">Acceso Rápido</span>
            </div>
          </Link>
        </div>
      </div>
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue';
import PageHeader from '@/Components/UI/PageHeader.vue';
import StatCard from '@/Components/UI/StatCard.vue';
import BaseCard from '@/Components/UI/BaseCard.vue';
import BaseBadge from '@/Components/UI/BaseBadge.vue';
import { navigationConfig } from '@/Config/navigation';

defineProps({
  contexto: {
    type: Object,
    required: true,
  },
});

const operationModules = computed(() => {
  return navigationConfig.mainMenu.filter(m => m.id !== 'inicio');
});

const getRoute = (routeName) => {
  try {
    return route(routeName);
  } catch {
    return `/${routeName.replace('.', '/')}`;
  }
};
</script>