<template>
  <SupervisorLayout>
    <div class="h-[calc(100vh-3rem)] md:h-[calc(100vh-2rem)] flex flex-col -m-5 md:-m-8 overflow-hidden bg-[#F5F5F7]">
      <!-- Pestañas de Submódulo -->
      <div class="bg-white border-b border-[#E5E5EA] px-4 pt-2.5 pb-0 flex items-center justify-between shrink-0">
        <div class="flex items-center space-x-1">
          <Link
            :href="route('supervisor.ruteo.index')"
            class="px-3 py-1.5 text-xs font-medium border-b-2 transition-colors flex items-center gap-1.5"
            :class="$page.component === 'Supervisor/Ruteo/Index'
              ? 'border-[#1D1D1F] text-[#1D1D1F] font-semibold'
              : 'border-transparent text-[#6E6E73] hover:text-[#1D1D1F]'"
          >
            <svg class="w-3.5 h-3.5 stroke-[1.8]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934a1.12 1.12 0 01-1.006 0L9.503 3.31a1.12 1.12 0 00-1.006 0L3.623 5.748A1.125 1.125 0 003 6.754v12.37c0 .836.88 1.38 1.628 1.006l3.869-1.934a1.12 1.12 0 011.006 0l4.994 2.497a1.12 1.12 0 001.006 0z" />
            </svg>
            Visor y Fronteras
          </Link>

          <Link
            :href="route('supervisor.ruteo.cercanos')"
            class="px-3 py-1.5 text-xs font-medium border-b-2 transition-colors flex items-center gap-1.5"
            :class="$page.component === 'Supervisor/Ruteo/Cercanos'
              ? 'border-[#1D1D1F] text-[#1D1D1F] font-semibold'
              : 'border-transparent text-[#6E6E73] hover:text-[#1D1D1F]'"
          >
            <svg class="w-3.5 h-3.5 stroke-[1.8]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
            </svg>
            Clientes Cercanos
          </Link>
        </div>

        <span class="text-[10px] font-mono text-[#86868B] pb-1.5 hidden sm:inline">
          MODULO DE RUTEO
        </span>
      </div>

      <!-- Contenido Específico -->
      <div class="flex-1 flex flex-col min-h-0 overflow-hidden">
        <slot />
      </div>
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue';
</script>