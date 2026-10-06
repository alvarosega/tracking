<template>
  <div class="min-h-screen bg-[#F8FAFC] font-sans text-slate-900 flex flex-col md:flex-row antialiased selection:bg-sky-500 selection:text-white">
    <!-- Header Mobile (Sticky) -->
    <header class="md:hidden bg-white/95 backdrop-blur-md border-b border-slate-200/90 h-12 px-4 flex items-center justify-between sticky top-0 z-40">
      <div class="flex items-center space-x-2.5">
        <button
          type="button"
          @click="isMobileMenuOpen = true"
          class="p-1.5 -ml-1.5 text-slate-500 hover:text-slate-900 rounded-lg hover:bg-slate-100 transition-colors"
          aria-label="Abrir menú"
        >
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
          </svg>
        </button>
        <div class="flex items-center space-x-1.5">
          <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
          <span class="text-xs font-bold tracking-wider text-slate-900 font-mono">{{ navigationConfig.appName }}</span>
        </div>
      </div>
      <div class="flex items-center space-x-2">
        <span class="text-[11px] font-mono text-slate-500 bg-slate-100 px-2 py-0.5 rounded-md border border-slate-200">
          {{ $page.props.auth?.user?.username || 'Supervisor' }}
        </span>
      </div>
    </header>

    <!-- Backdrop Mobile Drawer -->
    <div
      v-if="isMobileMenuOpen"
      @click="isMobileMenuOpen = false"
      class="fixed inset-0 bg-slate-900/40 z-40 md:hidden backdrop-blur-xs transition-opacity"
    ></div>

    <!-- Sidebar Principal -->
    <aside
      class="fixed md:sticky top-0 left-0 bottom-0 z-50 w-64 bg-white/95 backdrop-blur-md border-r border-slate-200/90 flex flex-col justify-between transition-transform duration-200 ease-in-out md:translate-x-0 h-screen"
      :class="isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <div class="flex flex-col flex-1 min-h-0">
        <!-- Logo / Header de Marca -->
        <div class="h-14 px-4 border-b border-slate-200/80 flex items-center justify-between shrink-0 bg-slate-50/40">
          <div class="flex items-center space-x-2.5">
            <div class="w-7 h-7 rounded-lg bg-slate-900 text-white flex items-center justify-center font-mono text-xs font-bold shadow-xs">
              SV
            </div>
            <div>
              <div class="flex items-center space-x-1.5">
                <span class="text-xs font-bold tracking-tight text-slate-900 font-mono">{{ navigationConfig.appName }}</span>
                <span class="text-[9px] font-mono font-semibold px-1.5 py-0.2 rounded bg-sky-50 text-sky-700 border border-sky-200">
                  {{ navigationConfig.badge }}
                </span>
              </div>
              <p class="text-[10px] text-slate-400 font-mono">Panel Central v2.0</p>
            </div>
          </div>

          <button
            type="button"
            @click="isMobileMenuOpen = false"
            class="md:hidden text-slate-400 hover:text-slate-700 p-1 rounded-md"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Lista de Navegación Dinámica basada en Config -->
        <nav class="p-3 space-y-1 overflow-y-auto flex-1">
          <Link
            v-for="item in navigationConfig.mainMenu"
            :key="item.id"
            :href="getRouteHref(item)"
            class="group flex items-center justify-between px-3 py-2 text-xs font-medium rounded-lg transition-all"
            :class="isItemActive(item)
              ? 'bg-slate-900 text-white shadow-xs font-semibold'
              : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/70'"
            @click="isMobileMenuOpen = false"
          >
            <div class="flex items-center space-x-2.5 truncate">
              <!-- Iconos SVG minimalistas -->
              <svg
                class="w-4 h-4 shrink-0 transition-colors"
                :class="isItemActive(item) ? 'text-white' : 'text-slate-400 group-hover:text-slate-700'"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="1.8"
              >
                <!-- Inicio -->
                <path v-if="item.id === 'inicio'" stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                <!-- Ruteo -->
                <path v-else-if="item.id === 'ruteo'" stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934a1.12 1.12 0 01-1.006 0L9.503 3.31a1.12 1.12 0 00-1.006 0L3.623 5.748A1.125 1.125 0 003 6.754v12.37c0 .836.88 1.38 1.628 1.006l3.869-1.934a1.12 1.12 0 011.006 0l4.994 2.497a1.12 1.12 0 001.006 0z" />
                <!-- Preventas -->
                <path v-else-if="item.id === 'preventas'" stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
                <!-- Tracking -->
                <path v-else-if="item.id === 'tracking'" stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0zM19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                <!-- Visitas -->
                <path v-else-if="item.id === 'visitas'" stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316zM16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z" />
                <!-- Altas/Ediciones -->
                <path v-else-if="item.id === 'altas-ediciones'" stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.765z" />
                <!-- Jornadas -->
                <path v-else-if="item.id === 'jornadas'" stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                <!-- Horarios -->
                <path v-else-if="item.id === 'horarios'" stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                <!-- Default -->
                <path v-else stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
              </svg>
              <span class="truncate">{{ item.name }}</span>
            </div>

            <!-- Badge si aplica -->
            <span
              v-if="item.badge"
              class="text-[9px] font-mono font-bold px-1.5 py-0.2 rounded"
              :class="isItemActive(item) ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600 border border-slate-200'"
            >
              {{ item.badge }}
            </span>
          </Link>
        </nav>
      </div>

      <!-- Footer de Sesión Minimalista -->
      <div class="p-3 border-t border-slate-200/80 bg-slate-50/50 shrink-0">
        <div class="px-3 py-2.5 rounded-xl bg-white border border-slate-200/90 shadow-2xs flex items-center justify-between">
          <div class="overflow-hidden pr-2">
            <div class="flex items-center space-x-1.5">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
              <p class="text-xs font-semibold text-slate-900 truncate font-mono">
                {{ $page.props.auth?.user?.username || 'Supervisor' }}
              </p>
            </div>
            <p class="text-[10px] text-slate-400 font-mono mt-0.5">Operador Activo</p>
          </div>
          <Link
            href="/logout"
            method="post"
            as="button"
            class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer"
            title="Cerrar Sesión"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
            </svg>
          </Link>
        </div>
      </div>
    </aside>

    <!-- Contenedor Principal Ultra-Limpio -->
    <main class="flex-1 min-w-0 flex flex-col overflow-x-hidden">
      <div class="flex-1 p-4 sm:p-6 lg:p-7 max-w-[1600px] w-full mx-auto">
        <slot />
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { navigationConfig } from '@/Config/navigation';

const page = usePage();
const isMobileMenuOpen = ref(false);

const getRouteHref = (item) => {
  try {
    return route(item.route);
  } catch {
    return item.route.startsWith('/') ? item.route : `/${item.route.replace('.', '/')}`;
  }
};

const isItemActive = (item) => {
  if (item.componentPrefix) {
    return page.component.startsWith(item.componentPrefix);
  }
  try {
    return route().current(item.route);
  } catch {
    return false;
  }
};
</script>