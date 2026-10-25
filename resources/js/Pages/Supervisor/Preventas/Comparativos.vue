<template>
  <SupervisorLayout full-bleed>
    <div class="flex-1 flex flex-col min-h-0 overflow-y-auto custom-scroll font-sans text-ink space-y-3 p-3 sm:p-5 select-none">
      
      <!-- Barra Superior de Navegación -->
      <header class="apple-glass-card rounded-[16px] p-2 sm:p-2.5 border border-white/60 flex flex-wrap items-center justify-between gap-2 shrink-0 z-30">
        <SubNavTabs :tabs="preventasSubTabs" currentTab="comparativos" />
        <span class="text-[11px] font-mono text-[#6E6E73] font-bold">Módulo Comparativos Preventa</span>
      </header>

      <!-- Tarjeta Apple Liquid Glass "En Construcción" -->
      <div class="apple-glass-card rounded-[16px] p-12 text-center backdrop-blur-xl border border-white/60 select-none font-sans max-w-2xl mx-auto my-auto shadow-glass-card">
        <div class="w-16 h-16 mx-auto rounded-2xl bg-white/70 border border-[#E5E5EA] flex items-center justify-center text-[#8B5CF6] mb-4 shadow-sm">
          <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="1.8"
              d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"
            />
          </svg>
        </div>
        <h3 class="text-[14px] font-bold text-[#1D1D1F] uppercase tracking-wider">En construcción</h3>
        <p class="text-[11px] text-[#6E6E73] font-medium mt-2 max-w-md mx-auto leading-relaxed">
          Evolución diaria del mes, comparativos vs ayer, vs mismo día semana anterior, vs mes anterior y vs LY.
        </p>

        <div class="mt-6 flex justify-center">
          <Link
            :href="route('supervisor.preventas.cumplimiento')"
            class="px-4 py-2 rounded-[10px] text-xs font-semibold text-white bg-[#0071E3] hover:bg-[#0077ED] active:scale-[0.98] transition-all duration-150 shadow-[0_2px_8px_rgba(0,113,227,0.35)] flex items-center gap-1.5"
          >
            <span>Ver Cumplimiento y Ranking Unilever</span>
            <span>&rarr;</span>
          </Link>
        </div>
      </div>

    </div>
  </SupervisorLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue';
import SubNavTabs from '@/Components/UI/SubNavTabs.vue';

defineProps({
  catalogo_rutas: { type: Array, default: () => [] },
  canales: { type: Array, default: () => [] },
  fecha_default: { type: String, default: () => new Date().toISOString().split('T')[0] },
});

const preventasSubTabs = [
  { id: 'cumplimiento', name: 'Cumplimiento Unilever', route: 'supervisor.preventas.cumplimiento' },
  { id: 'diaria', name: 'Preventa Diaria', route: 'supervisor.preventas.diaria' },
  { id: 'productos', name: 'Productos', route: 'supervisor.preventas.productos' },
  { id: 'auditoria', name: 'Auditoría', route: 'supervisor.preventas.auditoria' },
  { id: 'comparativos', name: 'Comparativos', route: 'supervisor.preventas.comparativos' },
  { id: 'mapa', name: 'Auditoría en Terreno', route: 'supervisor.preventas.index' },
];
</script>

