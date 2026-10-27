<template>
  <div class="apple-glass-card rounded-[16px] p-1.5 sm:p-2 mb-4 select-none font-sans flex flex-col sm:flex-row sm:items-center justify-between gap-2 shadow-xs border border-black/[0.05]">
    <!-- Segmented Navigation -->
    <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar scroll-touch">
      <!-- Tab 1: Permisos Supervisores -->
      <Link
        :href="route('supervisor.admin.permisos.index')"
        class="flex items-center gap-2 px-3.5 py-1.5 rounded-[10px] text-[11px] font-semibold transition-all duration-150 cursor-pointer whitespace-nowrap active:scale-95"
        :class="activeTab === 'permisos'
          ? 'bg-[#0071E3] text-white shadow-[0_2px_8px_rgba(0,113,227,0.35)] font-bold'
          : 'text-[#6E6E73] hover:text-[#1D1D1F] hover:bg-black/[0.04]'"
      >
        <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
        </svg>
        <span>Permisos Supervisores</span>
      </Link>

      <!-- Tab 2: Administración de Usuarios -->
      <Link
        :href="route('supervisor.admin.usuarios.index')"
        class="flex items-center gap-2 px-3.5 py-1.5 rounded-[10px] text-[11px] font-semibold transition-all duration-150 cursor-pointer whitespace-nowrap active:scale-95"
        :class="activeTab === 'usuarios'
          ? 'bg-[#0071E3] text-white shadow-[0_2px_8px_rgba(0,113,227,0.35)] font-bold'
          : 'text-[#6E6E73] hover:text-[#1D1D1F] hover:bg-black/[0.04]'"
      >
        <svg class="w-3.5 h-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z" />
        </svg>
        <span>Administración de Usuarios</span>
      </Link>
    </div>

    <!-- Indicador Rol Exclusivo -->
    <div class="hidden sm:flex items-center gap-1.5 text-[10px] font-mono text-[#86868B] px-2 font-semibold">
      <span class="w-1.5 h-1.5 rounded-full bg-purple-600 animate-pulse"></span>
      <span>Acceso Exclusivo Superadministrador</span>
    </div>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'

defineProps({
  activeTab: {
    type: String,
    required: true,
    validator: val => ['permisos', 'usuarios'].includes(val),
  },
})
</script>

