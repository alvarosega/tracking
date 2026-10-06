<template>
  <SupervisorLayout>
    <div class="space-y-5">
      <!-- Pestañas de Submódulo Visitas -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-slate-200/80">
        <SubNavTabs :tabs="visitasTabs" />

        <div class="hidden sm:flex items-center space-x-2 text-[11px] font-mono text-slate-500">
          <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
          <span>Auditoría en Terreno</span>
        </div>
      </div>

      <div class="min-h-0">
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