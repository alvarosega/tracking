<template>
  <SupervisorLayout>
    <!-- Contenedor que rompe el padding y max-width del layout principal para dar pantalla completa -->
    <div class="h-[calc(100vh-3rem)] md:h-[calc(100vh-3.5rem)] flex flex-col -m-5 md:-m-8 overflow-hidden bg-[#F5F5F7]">
      
      <!-- Barra de Sub-Navegación Apple Pro para Visitas -->
      <nav class="bg-white border-b border-[#E5E5EA] px-3 md:px-4 h-10 flex items-center justify-between shrink-0 relative z-30">
        <div class="flex items-center space-x-1 overflow-x-auto no-scrollbar py-1">
          <!-- Submódulo 1: Cobertura Fotográfica Histórica -->
          <Link
            :href="route('supervisor.visitas.index')"
            class="px-2.5 py-1 text-xs rounded-[6px] transition-colors whitespace-nowrap cursor-pointer flex items-center gap-1.5"
            :class="$page.component === 'Supervisor/Visitas/Index'
              ? 'bg-[#F2F2F7] text-[#1D1D1F] font-semibold shadow-2xs'
              : 'text-[#6E6E73] hover:text-[#1D1D1F] hover:bg-[#FBFBFD]'"
          >
            <span class="w-1.5 h-1.5 rounded-full" :class="$page.component === 'Supervisor/Visitas/Index' ? 'bg-[#248A3D]' : 'bg-[#86868B]'"></span>
            <span>Cobertura Fotográfica</span>
          </Link>

          <!-- Submódulo 2: Avance Diario (Ruta estática temporal para no romper Ziggy) -->
          <Link
            href="/supervisor/visitas/avance"
            class="px-2.5 py-1 text-xs rounded-[6px] transition-colors whitespace-nowrap cursor-pointer flex items-center gap-1.5"
            :class="$page.component === 'Supervisor/Visitas/Avance'
              ? 'bg-[#F2F2F7] text-[#1D1D1F] font-semibold shadow-2xs'
              : 'text-[#6E6E73] hover:text-[#1D1D1F] hover:bg-[#FBFBFD]'"
          >
            <span class="w-1.5 h-1.5 rounded-full" :class="$page.component === 'Supervisor/Visitas/Avance' ? 'bg-[#0071E3]' : 'bg-[#86868B]'"></span>
            <span>Avance Diario</span>
          </Link>
        </div>

        <div class="hidden sm:flex items-center text-[11px] font-mono text-[#86868B]">
          Auditoría de Visitas
        </div>
      </nav>

      <!-- Contenido dinámico del submódulo activo -->
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