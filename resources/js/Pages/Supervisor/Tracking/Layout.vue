<template>
  <SupervisorLayout>
    <!-- Contenedor Viewport Fullscreen para Dashboards de Mapa -->
    <div class="h-[calc(100vh-3rem)] md:h-[calc(100vh-3.5rem)] flex flex-col -m-4 sm:-m-6 lg:-m-7 overflow-hidden bg-slate-900">
      <!-- Barra Superior de Sub-Navegación Futurista -->
      <nav class="bg-white/95 backdrop-blur-md border-b border-slate-200/90 px-3 sm:px-4 h-11 flex items-center justify-between shrink-0 relative z-30">
        <div class="flex items-center space-x-2">
          <SubNavTabs :tabs="trackingTabs" />
        </div>

        <div class="hidden sm:flex items-center space-x-2 text-[11px] font-mono text-slate-500">
          <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
          <span>Telemetría GPS en Vivo</span>
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
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue';
import SubNavTabs from '@/Components/UI/SubNavTabs.vue';
import { navigationConfig } from '@/Config/navigation';

const trackingMenu = navigationConfig.mainMenu.find(m => m.id === 'tracking');
const trackingTabs = trackingMenu ? trackingMenu.submodules : [];
</script>