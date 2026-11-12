<template>
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 select-none font-sans">
    <!-- Pestañas Apple Segmented Control -->
    <div class="apple-glass-card rounded-[12px] p-0.5 flex items-center gap-1 border border-black/[0.06] shadow-2xs self-start sm:self-auto">
      <!-- Submódulo 1: Nuevas Altas -->
      <Link
        :href="route('supervisor.altas-ediciones.altas')"
        class="flex items-center gap-1.5 px-3 py-1.5 rounded-[9px] text-xs font-semibold transition-all duration-150 cursor-pointer"
        :class="activeTab === 'altas'
          ? 'bg-[#248A3D] text-white shadow-[0_2px_8px_rgba(36,138,61,0.35)]'
          : 'text-[#6E6E73] hover:text-[#1D1D1F] hover:bg-black/[0.04]'"
      >
        <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.765z" />
        </svg>
        <span>Nuevas Altas</span>
      </Link>

      <!-- Submódulo 2: Modificaciones -->
      <Link
        :href="route('supervisor.altas-ediciones.modificaciones')"
        class="flex items-center gap-1.5 px-3 py-1.5 rounded-[9px] text-xs font-semibold transition-all duration-150 cursor-pointer"
        :class="activeTab === 'modificaciones'
          ? 'bg-[#0071E3] text-white shadow-[0_2px_8px_rgba(0,113,227,0.35)]'
          : 'text-[#6E6E73] hover:text-[#1D1D1F] hover:bg-black/[0.04]'"
      >
        <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10" />
        </svg>
        <span>Modificaciones de Clientes</span>
      </Link>
    </div>

    <!-- Indicador descriptivo del submódulo activo -->
    <div class="hidden sm:flex items-center gap-2 text-[11px] font-mono text-[#86868B]">
      <span v-if="activeTab === 'altas'" class="flex items-center gap-1.5">
        <span class="w-2 h-2 rounded-full bg-[#248A3D] animate-pulse"></span>
        <span>Aperturas territoriales y clientes nuevos creados en ruta</span>
      </span>
      <span v-else class="flex items-center gap-1.5">
        <span class="w-2 h-2 rounded-full bg-[#0071E3] animate-pulse"></span>
        <span>Actualización de datos comerciales y coordenadas de cartera</span>
      </span>
    </div>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
  activeTab: {
    type: String,
    default: 'altas',
  },
});
</script>
