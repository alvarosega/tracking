<template>
  <div
    class="bg-transparent font-sans text-ink flex flex-col md:flex-row antialiased selection:bg-app-accent selection:text-white"
    :class="fullBleed ? 'h-[100dvh] md:h-screen overflow-hidden' : 'min-h-screen'"
  >
    <!-- Header Mobile (Sticky) -->
    <header class="md:hidden apple-glass-topbar h-12 px-3 flex items-center justify-between sticky top-0 z-40 safe-top shrink-0">
      <div class="flex items-center space-x-2 min-w-0">
        <button
          type="button"
          @click="isMobileMenuOpen = true"
          class="w-8 h-8 flex items-center justify-center text-slate-600 hover:text-slate-900 active:scale-95 rounded-lg hover:bg-slate-100 transition-all cursor-pointer shrink-0"
          aria-label="Abrir menú"
        >
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
          </svg>
        </button>
        <div class="flex items-center space-x-1.5 truncate">
          <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse shrink-0"></span>
          <span class="text-xs font-bold tracking-wider text-slate-900 font-mono shrink-0">{{ navigationConfig.appName }}</span>
          <span v-if="currentModule" class="text-[10px] font-mono text-slate-400 shrink-0">/</span>
          <span v-if="currentModule" class="text-xs font-semibold text-sky-700 truncate font-mono">
            {{ currentModule.shortName || currentModule.name }}
          </span>
        </div>
      </div>
      <div class="flex items-center space-x-1.5 shrink-0">
        <span class="text-[10px] font-mono text-slate-500 bg-slate-100 px-2 py-0.5 rounded border border-slate-200">
          {{ $page.props.auth?.user?.username || 'Supervisor' }}
        </span>
      </div>
    </header>

    <!-- Backdrop Mobile Drawer -->
    <div
      v-if="isMobileMenuOpen"
      @click="isMobileMenuOpen = false"
      class="fixed inset-0 bg-slate-900/50 z-50 md:hidden backdrop-blur-xs transition-opacity duration-200"
    ></div>

    <!-- Sidebar Principal / Drawer Móvil -->
    <aside
      class="fixed md:sticky top-0 left-0 bottom-0 z-50 w-72 md:w-64 apple-glass-sidebar flex flex-col justify-between transition-transform duration-200 ease-in-out md:translate-x-0 h-screen shadow-xl md:shadow-none"
      :class="isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full'"
    >
      <div class="flex flex-col flex-1 min-h-0 safe-top">
        <!-- Logo / Header de Marca -->
        <div class="h-14 px-4 border-b border-white/60 flex items-center justify-between shrink-0 bg-white/40">
          <div class="flex items-center space-x-2.5">
            <div class="w-7 h-7 rounded-lg bg-[#0071E3] text-white flex items-center justify-center font-mono text-xs font-bold shadow-xs">
              SV
            </div>
            <div>
              <div class="flex items-center space-x-1.5">
                <span class="text-xs font-bold tracking-tight text-ink font-mono">{{ navigationConfig.appName }}</span>
                <span class="text-[9px] font-mono font-semibold px-1.5 py-0.2 rounded bg-sky-50 text-sky-700 border border-sky-200">
                  {{ navigationConfig.badge }}
                </span>
              </div>
              <p class="text-[10px] text-ink-tertiary font-mono">Panel Central v2.0</p>
            </div>
          </div>

          <button
            type="button"
            @click="isMobileMenuOpen = false"
            class="md:hidden text-slate-400 hover:text-slate-700 p-1.5 rounded-lg active:scale-95 cursor-pointer"
            aria-label="Cerrar menú"
          >
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Lista de Navegación Dinámica basada en Config -->
        <nav class="p-3 space-y-1 overflow-y-auto flex-1 scroll-touch custom-scroll">
          <Link
            v-for="item in navigationConfig.mainMenu"
            :key="item.id"
            :href="getRouteHref(item)"
            class="group flex items-center justify-between px-3 py-2.5 md:py-2 text-xs font-medium rounded-lg transition-all active:scale-[0.99]"
            :class="isItemActive(item)
              ? 'bg-[#0071E3] text-white shadow-[0_2px_8px_rgba(0,113,227,0.35)] font-semibold'
              : 'text-ink-secondary hover:text-ink hover:bg-white/60'"
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
                <!-- Cumplimiento Unilever / Matrices -->
                <path v-else-if="item.id === 'cumplimiento'" stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z" />
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
                <!-- Módulos & Ajustes -->
                <path v-else-if="item.id === 'modulos'" stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" />
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
      <div class="p-3 border-t border-white/60 bg-white/30 shrink-0 safe-bottom">
        <div class="apple-control-pill rounded-card p-2.5 flex items-center justify-between">
          <div class="overflow-hidden pr-2">
            <div class="flex items-center space-x-1.5">
              <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
              <p class="text-xs font-semibold text-ink truncate font-mono">
                {{ $page.props.auth?.user?.username || 'Supervisor' }}
              </p>
            </div>
            <p class="text-[10px] text-ink-tertiary font-mono mt-0.5">Operador Activo</p>
          </div>
          <Link
            href="/logout"
            method="post"
            as="button"
            class="p-2 text-ink-tertiary hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer active:scale-95"
            title="Cerrar Sesión"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
            </svg>
          </Link>
        </div>
      </div>
    </aside>

    <!-- Contenedor Principal -->
    <main
      class="flex-1 min-w-0 flex flex-col"
      :class="fullBleed ? 'min-h-0 overflow-hidden pb-14 md:pb-0' : 'overflow-x-hidden pb-20 md:pb-0'"
    >
      <div v-if="fullBleed" class="flex-1 w-full flex flex-col min-h-0 overflow-hidden">
        <slot />
      </div>
      <div v-else class="flex-1 p-3.5 sm:p-6 lg:p-7 max-w-[1600px] w-full mx-auto">
        <slot />
      </div>
    </main>

    <!-- Barra de Navegación Inferior Móvil (Bottom Navigation Bar) -->
    <nav class="md:hidden fixed bottom-0 left-0 right-0 z-40 bg-white/95 backdrop-blur-md border-t border-slate-200/90 safe-bottom">
      <div class="grid grid-cols-5 h-14">
        <!-- 1. Inicio -->
        <Link
          :href="route('supervisor.index')"
          class="flex flex-col items-center justify-center py-1 px-1 text-[10px] font-medium transition-all active:scale-95 select-none relative"
          :class="isPageActive('supervisor.index', 'Supervisor/Index') ? 'text-sky-600 font-semibold' : 'text-slate-500 hover:text-slate-800'"
        >
          <span v-if="isPageActive('supervisor.index', 'Supervisor/Index')" class="absolute top-0 w-8 h-0.5 rounded-full bg-sky-600"></span>
          <svg class="w-5 h-5 mb-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
          </svg>
          <span class="truncate">Inicio</span>
        </Link>

        <!-- 2. Ruteo -->
        <Link
          :href="route('supervisor.ruteo.index')"
          class="flex flex-col items-center justify-center py-1 px-1 text-[10px] font-medium transition-all active:scale-95 select-none relative"
          :class="isPageActive('supervisor.ruteo.index', 'Supervisor/Ruteo') ? 'text-sky-600 font-semibold' : 'text-slate-500 hover:text-slate-800'"
        >
          <span v-if="isPageActive('supervisor.ruteo.index', 'Supervisor/Ruteo')" class="absolute top-0 w-8 h-0.5 rounded-full bg-sky-600"></span>
          <svg class="w-5 h-5 mb-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934a1.12 1.12 0 01-1.006 0L9.503 3.31a1.12 1.12 0 00-1.006 0L3.623 5.748A1.125 1.125 0 003 6.754v12.37c0 .836.88 1.38 1.628 1.006l3.869-1.934a1.12 1.12 0 011.006 0l4.994 2.497a1.12 1.12 0 001.006 0z" />
          </svg>
          <span class="truncate">Ruteo</span>
        </Link>

        <!-- 3. Tracking -->
        <Link
          :href="route('supervisor.tracking.index')"
          class="flex flex-col items-center justify-center py-1 px-1 text-[10px] font-medium transition-all active:scale-95 select-none relative"
          :class="isPageActive('supervisor.tracking.index', 'Supervisor/Tracking') ? 'text-sky-600 font-semibold' : 'text-slate-500 hover:text-slate-800'"
        >
          <span v-if="isPageActive('supervisor.tracking.index', 'Supervisor/Tracking')" class="absolute top-0 w-8 h-0.5 rounded-full bg-sky-600"></span>
          <svg class="w-5 h-5 mb-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0zM19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
          </svg>
          <span class="truncate">Tracking</span>
        </Link>

        <!-- 4. Altas -->
        <Link
          :href="route('supervisor.altas-ediciones.index')"
          class="flex flex-col items-center justify-center py-1 px-1 text-[10px] font-medium transition-all active:scale-95 select-none relative"
          :class="isPageActive('supervisor.altas-ediciones.index', 'Supervisor/AltasEdiciones') ? 'text-sky-600 font-semibold' : 'text-slate-500 hover:text-slate-800'"
        >
          <span v-if="isPageActive('supervisor.altas-ediciones.index', 'Supervisor/AltasEdiciones')" class="absolute top-0 w-8 h-0.5 rounded-full bg-sky-600"></span>
          <svg class="w-5 h-5 mb-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.765z" />
          </svg>
          <span class="truncate">Altas</span>
        </Link>

        <!-- 5. Menú Más -->
        <button
          type="button"
          @click="isMobileMenuOpen = true"
          class="flex flex-col items-center justify-center py-1 px-1 text-[10px] font-medium transition-all active:scale-95 select-none relative cursor-pointer"
          :class="isMasActive ? 'text-sky-600 font-semibold' : 'text-slate-500 hover:text-slate-800'"
        >
          <span v-if="isMasActive" class="absolute top-0 w-8 h-0.5 rounded-full bg-sky-600"></span>
          <svg class="w-5 h-5 mb-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
          </svg>
          <span class="truncate">Más</span>
        </button>
      </div>
    </nav>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { navigationConfig } from '@/Config/navigation';

defineProps({
  fullBleed: {
    type: Boolean,
    default: false,
  },
});

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

const isPageActive = (routeName, prefix) => {
  if (prefix && page.component.startsWith(prefix)) {
    return true;
  }
  try {
    return route().current(routeName);
  } catch {
    return false;
  }
};

const isMasActive = computed(() => {
  return page.component.startsWith('Supervisor/Visitas') ||
         page.component.startsWith('Supervisor/Preventas') ||
         page.component.startsWith('Supervisor/Jornadas') ||
         page.component.startsWith('Supervisor/Horarios') ||
         page.component.startsWith('Supervisor/Modulos');
});

const currentModule = computed(() => {
  return navigationConfig.mainMenu.find(item => isItemActive(item));
});
</script>
