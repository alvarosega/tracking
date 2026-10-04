<template>
  <SupervisorLayout>
    <!-- Contenedor Viewport Fullscreen para Dashboards con Mapas -->
    <div class="h-[calc(100vh-3rem)] md:h-[calc(100vh-3.5rem)] flex flex-col -m-5 md:-m-8 overflow-hidden bg-[#F5F5F7]">
      
      <!-- Barra Superior de Sub-Navegación Apple Pro -->
      <nav class="bg-white border-b border-[#E5E5EA] px-3 md:px-4 h-10 flex items-center justify-between shrink-0 relative z-30">
        <div class="flex items-center space-x-1 overflow-x-auto no-scrollbar py-1">
          
          <!-- Pestaña 1: Patrones Históricos -->
          <Link
            :href="route('supervisor.tracking.historico')"
            class="px-2.5 py-1 text-xs rounded-[6px] transition-colors whitespace-nowrap cursor-pointer flex items-center gap-1.5"
            :class="$page.component === 'Supervisor/Tracking/Historico'
              ? 'bg-[#F2F2F7] text-[#1D1D1F] font-semibold shadow-2xs'
              : 'text-[#6E6E73] hover:text-[#1D1D1F] hover:bg-[#FBFBFD]'"
          >
            <span class="w-1.5 h-1.5 rounded-full" :class="$page.component === 'Supervisor/Tracking/Historico' ? 'bg-[#AF52DE]' : 'bg-[#86868B]'"></span>
            <span>Patrones Históricos</span>
          </Link>

          <!-- Pestaña 2: Recorrido del Día -->
          <Link
            :href="route('supervisor.tracking.index')"
            class="px-2.5 py-1 text-xs rounded-[6px] transition-colors whitespace-nowrap cursor-pointer flex items-center gap-1.5"
            :class="$page.component === 'Supervisor/Tracking/Index'
              ? 'bg-[#F2F2F7] text-[#1D1D1F] font-semibold shadow-2xs'
              : 'text-[#6E6E73] hover:text-[#1D1D1F] hover:bg-[#FBFBFD]'"
          >
            <span class="w-1.5 h-1.5 rounded-full" :class="$page.component === 'Supervisor/Tracking/Index' ? 'bg-[#0071E3]' : 'bg-[#86868B]'"></span>
            <span>Recorrido del Día</span>
          </Link>
        </div>

        <div class="hidden sm:flex items-center text-[11px] font-mono text-[#86868B]">
          Telemetría GPS & Trayectorias
        </div>
      </nav>

      <!-- Contenedor del Submódulo Activo -->
      <div class="flex-1 flex flex-col min-h-0 overflow-hidden relative">
        <slot />
      </div>
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue';
</script>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
.no-scrollbar {
  -ms-overflow-style: none;
  scrollbar-width: none;
}
</style>