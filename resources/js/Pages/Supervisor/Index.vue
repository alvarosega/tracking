<template>
  <SupervisorLayout>
    <div class="space-y-6">
      <!-- Encabezado de Bienvenida -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-5 border-b border-[#E5E5EA] gap-2">
        <div>
          <h1 class="text-xl font-semibold tracking-tight text-[#1D1D1F]">
            Panel de Control
          </h1>
          <p class="text-xs text-[#6E6E73] mt-0.5">
            Sesión iniciada como {{ $page.props.auth?.user?.username || 'Supervisor' }}
          </p>
        </div>
        <div class="flex items-center space-x-2 text-xs font-mono text-[#6E6E73]">
          <span class="px-2 py-1 bg-white border border-[#E5E5EA] rounded-[6px]">
            {{ contexto.dia_semana }}, {{ contexto.fecha }}
          </span>
          <span class="px-2 py-1 bg-white border border-[#E5E5EA] rounded-[6px] tabular-nums">
            {{ contexto.hora }}
          </span>
        </div>
      </div>

      <!-- Estado Operativo de Plataforma -->
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="bg-white border border-[#E5E5EA] rounded-[8px] p-4">
          <p class="text-[11px] font-semibold tracking-wide uppercase text-[#86868B]">Fuerza Comercial</p>
          <div class="mt-2 flex items-baseline space-x-2">
            <span class="text-2xl font-semibold text-[#1D1D1F] tabular-nums">{{ contexto.total_fuerza_ventas }}</span>
            <span class="text-xs text-[#6E6E73]">vendedores activos</span>
          </div>
        </div>

        <div class="bg-white border border-[#E5E5EA] rounded-[8px] p-4">
          <p class="text-[11px] font-semibold tracking-wide uppercase text-[#86868B]">Conexión Primaria</p>
          <div class="mt-2 flex items-center space-x-2">
            <span class="w-2 h-2 rounded-full bg-[#248A3D]"></span>
            <span class="text-xs font-mono font-medium text-[#1D1D1F]">u967339252_alva</span>
          </div>
          <p class="text-[10px] text-[#86868B] mt-1">Hostinger srv1894.hstgr.io (Producción)</p>
        </div>

        <div class="bg-white border border-[#E5E5EA] rounded-[8px] p-4">
          <p class="text-[11px] font-semibold tracking-wide uppercase text-[#86868B]">Conexión Supervisión</p>
          <div class="mt-2 flex items-center space-x-2">
            <span class="w-2 h-2 rounded-full bg-[#248A3D]"></span>
            <span class="text-xs font-mono font-medium text-[#1D1D1F]">u967339252_supervisor</span>
          </div>
          <p class="text-[10px] text-[#86868B] mt-1">Hostinger srv1894.hstgr.io (Transaccional)</p>
        </div>
      </div>

      <!-- Accesos Directos a Submódulos Operativos -->
      <div>
        <h2 class="text-xs font-semibold uppercase tracking-wider text-[#86868B] mb-3">Módulos de Operación</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
          <Link
            :href="route('supervisor.ruteo.index')"
            class="bg-white border border-[#E5E5EA] rounded-[8px] p-4 hover:border-[#86868B] transition-colors flex flex-col justify-between"
          >
            <div>
              <p class="text-sm font-semibold text-[#1D1D1F]">Ruteo</p>
              <p class="text-xs text-[#6E6E73] mt-1">Planes de visita, clientes asignados y cobertura.</p>
            </div>
            <span class="text-xs font-medium text-[#1D1D1F] mt-4 flex items-center gap-1">
              Abrir módulo &rarr;
            </span>
          </Link>

          <Link
            :href="route('supervisor.tracking.index')"
            class="bg-white border border-[#E5E5EA] rounded-[8px] p-4 hover:border-[#86868B] transition-colors flex flex-col justify-between"
          >
            <div>
              <p class="text-sm font-semibold text-[#1D1D1F]">Tracking</p>
              <p class="text-xs text-[#6E6E73] mt-1">Histórico de trayectorias, distancias y puntos GPS.</p>
            </div>
            <span class="text-xs font-medium text-[#1D1D1F] mt-4 flex items-center gap-1">
              Abrir módulo &rarr;
            </span>
          </Link>

          <Link
            :href="route('supervisor.visitas.index')"
            class="bg-white border border-[#E5E5EA] rounded-[8px] p-4 hover:border-[#86868B] transition-colors flex flex-col justify-between"
          >
            <div>
              <p class="text-sm font-semibold text-[#1D1D1F]">Visitas</p>
              <p class="text-xs text-[#6E6E73] mt-1">Auditoría fotográfica y registro de visitas en terreno.</p>
            </div>
            <span class="text-xs font-medium text-[#1D1D1F] mt-4 flex items-center gap-1">
              Abrir módulo &rarr;
            </span>
          </Link>

          <Link
            :href="route('supervisor.jornadas.index')"
            class="bg-white border border-[#E5E5EA] rounded-[8px] p-4 hover:border-[#86868B] transition-colors flex flex-col justify-between"
          >
            <div>
              <p class="text-sm font-semibold text-[#1D1D1F]">Jornadas</p>
              <p class="text-xs text-[#6E6E73] mt-1">Control de turnos, aperturas y cierres de fuerza de ventas.</p>
            </div>
            <span class="text-xs font-medium text-[#1D1D1F] mt-4 flex items-center gap-1">
              Abrir módulo &rarr;
            </span>
          </Link>
        </div>
      </div>
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue';

defineProps({
  contexto: {
    type: Object,
    required: true,
  },
});
</script>