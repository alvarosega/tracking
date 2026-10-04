<template>
  <div class="min-h-screen bg-[#F5F5F7] font-sans text-[#1D1D1F] flex flex-col md:flex-row antialiased">
    <!-- Header Mobile (md:hidden) -->
    <header class="md:hidden bg-white border-b border-[#E5E5EA] h-12 px-4 flex items-center justify-between sticky top-0 z-40">
      <div class="flex items-center space-x-2.5">
        <button
          type="button"
          @click="isMobileMenuOpen = true"
          class="p-1.5 -ml-1.5 text-[#6E6E73] hover:text-[#1D1D1F] focus:outline-none"
          aria-label="Abrir menú"
        >
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
          </svg>
        </button>
        <span class="text-xs font-semibold tracking-tight text-[#1D1D1F]">SUPERVISIÓN</span>
      </div>
      <div class="flex items-center space-x-2">
        <span class="w-2 h-2 rounded-full bg-[#248A3D]"></span>
        <span class="text-[11px] font-mono text-[#6E6E73]">{{ $page.props.auth?.user?.username || 'Supervisor' }}</span>
      </div>
    </header>

    <!-- Backdrop Mobile Drawer -->
    <div
      v-if="isMobileMenuOpen"
      @click="isMobileMenuOpen = false"
      class="fixed inset-0 bg-black/25 z-40 md:hidden backdrop-blur-xs transition-opacity"
    ></div>

    <!-- Sidebar (Fijo en Desktop / Off-Canvas Drawer en Mobile) -->
    <aside
      class="fixed md:sticky top-0 left-0 bottom-0 z-50 w-64 bg-white border-r border-[#E5E5EA] flex flex-col justify-between transition-transform duration-200 ease-in-out md:translate-x-0"
      :class="isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <!-- Cabecera Sidebar -->
      <div>
        <div class="h-14 px-5 border-b border-[#E5E5EA] flex items-center justify-between">
          <div class="flex items-center space-x-2.5">
            <span class="w-2 h-2 rounded-full bg-[#248A3D]"></span>
            <span class="text-[13px] font-semibold tracking-tight text-[#1D1D1F]">SUPERVISIÓN</span>
            <span class="text-[10px] font-medium tracking-wide bg-[#F2F2F7] text-[#6E6E73] px-1.5 py-0.5 rounded-[4px] border border-[#E5E5EA]">GPS</span>
          </div>
          <button
            type="button"
            @click="isMobileMenuOpen = false"
            class="md:hidden text-[#86868B] hover:text-[#1D1D1F] p-1"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Grupo de Navegación -->
        <nav class="p-3 space-y-1">
          <Link
            :href="route('supervisor.index')"
            class="group flex items-center px-3 py-2 text-[13px] font-medium rounded-[8px] transition-colors"
            :class="$page.component === 'Supervisor/Index'
              ? 'bg-[#F2F2F7] text-[#1D1D1F] font-semibold'
              : 'text-[#6E6E73] hover:text-[#1D1D1F] hover:bg-[#FBFBFD]'"
            @click="isMobileMenuOpen = false"
          >
            <svg class="w-4 h-4 mr-3 stroke-[1.8]" :class="$page.component === 'Supervisor/Index' ? 'text-[#1D1D1F]' : 'text-[#86868B] group-hover:text-[#1D1D1F]'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
            </svg>
            Inicio
          </Link>

          <Link
            :href="route('supervisor.ruteo.index')"
            class="group flex items-center px-3 py-2 text-[13px] font-medium rounded-[8px] transition-colors"
            :class="$page.component.startsWith('Supervisor/Ruteo')
              ? 'bg-[#F2F2F7] text-[#1D1D1F] font-semibold'
              : 'text-[#6E6E73] hover:text-[#1D1D1F] hover:bg-[#FBFBFD]'"
            @click="isMobileMenuOpen = false"
          >
            <svg class="w-4 h-4 mr-3 stroke-[1.8]" :class="$page.component.startsWith('Supervisor/Ruteo') ? 'text-[#1D1D1F]' : 'text-[#86868B] group-hover:text-[#1D1D1F]'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934a1.12 1.12 0 01-1.006 0L9.503 3.31a1.12 1.12 0 00-1.006 0L3.623 5.748A1.125 1.125 0 003 6.754v12.37c0 .836.88 1.38 1.628 1.006l3.869-1.934a1.12 1.12 0 011.006 0l4.994 2.497a1.12 1.12 0 001.006 0z" />
            </svg>
            Ruteo
          </Link>

          <Link
            :href="route('supervisor.tracking.index')"
            class="group flex items-center px-3 py-2 text-[13px] font-medium rounded-[8px] transition-colors"
            :class="$page.component.startsWith('Supervisor/Tracking')
              ? 'bg-[#F2F2F7] text-[#1D1D1F] font-semibold'
              : 'text-[#6E6E73] hover:text-[#1D1D1F] hover:bg-[#FBFBFD]'"
            @click="isMobileMenuOpen = false"
          >
            <svg class="w-4 h-4 mr-3 stroke-[1.8]" :class="$page.component.startsWith('Supervisor/Tracking') ? 'text-[#1D1D1F]' : 'text-[#86868B] group-hover:text-[#1D1D1F]'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
            </svg>
            Tracking
          </Link>

          <Link
            :href="route('supervisor.visitas.index')"
            class="group flex items-center px-3 py-2 text-[13px] font-medium rounded-[8px] transition-colors"
            :class="$page.component.startsWith('Supervisor/Visitas')
              ? 'bg-[#F2F2F7] text-[#1D1D1F] font-semibold'
              : 'text-[#6E6E73] hover:text-[#1D1D1F] hover:bg-[#FBFBFD]'"
            @click="isMobileMenuOpen = false"
          >
            <svg class="w-4 h-4 mr-3 stroke-[1.8]" :class="$page.component.startsWith('Supervisor/Visitas') ? 'text-[#1D1D1F]' : 'text-[#86868B] group-hover:text-[#1D1D1F]'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Visitas
          </Link>

          <Link
            :href="route('supervisor.jornadas.index')"
            class="group flex items-center px-3 py-2 text-[13px] font-medium rounded-[8px] transition-colors"
            :class="$page.component.startsWith('Supervisor/Jornadas')
              ? 'bg-[#F2F2F7] text-[#1D1D1F] font-semibold'
              : 'text-[#6E6E73] hover:text-[#1D1D1F] hover:bg-[#FBFBFD]'"
            @click="isMobileMenuOpen = false"
          >
            <svg class="w-4 h-4 mr-3 stroke-[1.8]" :class="$page.component.startsWith('Supervisor/Jornadas') ? 'text-[#1D1D1F]' : 'text-[#86868B] group-hover:text-[#1D1D1F]'" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            Jornadas
          </Link>
        </nav>
      </div>

      <!-- Footer de Sesión del Sidebar -->
      <div class="p-3 border-t border-[#E5E5EA]">
        <div class="px-3 py-2.5 rounded-[8px] bg-[#FBFBFD] border border-[#E5E5EA] flex items-center justify-between">
          <div class="overflow-hidden pr-2">
            <p class="text-[12px] font-semibold text-[#1D1D1F] truncate leading-tight">
              {{ $page.props.auth?.user?.username || 'Supervisor' }}
            </p>
            <p class="text-[10px] text-[#86868B] font-mono leading-tight mt-0.5">Operador Activo</p>
          </div>
          <Link
            href="/logout"
            method="post"
            as="button"
            class="p-1.5 text-[#86868B] hover:text-[#C9342C] hover:bg-[#FDF0EF] rounded-[6px] transition-colors"
            title="Cerrar Sesión"
          >
            <svg class="w-4 h-4 stroke-[1.8]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
            </svg>
          </Link>
        </div>
      </div>
    </aside>

    <!-- Contenedor Principal de Contenido -->
    <main class="flex-1 min-w-0 flex flex-col">
      <div class="flex-1 p-5 md:p-8 max-w-6xl w-full mx-auto">
        <slot />
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';

const isMobileMenuOpen = ref(false);
</script>