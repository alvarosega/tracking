<template>
  <SupervisorLayout full-bleed>
    <div class="flex-1 flex flex-col min-h-0 overflow-hidden bg-slate-900">
      <!-- Pestañas de Submódulo Visitas -->
      <div class="bg-white/95 backdrop-blur-md border-b border-slate-200/90 px-3 sm:px-4 h-11 flex items-center justify-between shrink-0 relative z-30">
        <div class="flex items-center space-x-2">
          <SubNavTabs :tabs="visitasTabs" />
        </div>

        <div class="hidden sm:flex items-center space-x-2 text-[11px] font-mono text-slate-500">
          <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
          <span>Auditoría en Terreno</span>
        </div>
      </div>

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

const visitasMenu = navigationConfig.mainMenu.find(m => m.id === 'visitas');
const visitasTabs = visitasMenu ? visitasMenu.submodules : [];
</script>
