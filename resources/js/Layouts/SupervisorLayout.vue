<template>
  <div class="h-screen w-screen flex flex-col bg-slate-950 text-slate-100 overflow-hidden font-sans">
    <!-- BARRA SUPERIOR GLOBAL -->
    <header class="h-14 bg-slate-900 border-b border-slate-800 px-6 flex items-center justify-between z-30 flex-shrink-0">
      <div class="flex items-center gap-6">
        <div class="flex items-center gap-2">
          <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
          <span class="font-bold text-sm tracking-wider uppercase text-white">Tracking Ventas</span>
        </div>

        <!-- Navegación de Módulos -->
        <nav class="flex items-center space-x-1">
          <Link
            href="/supervisor/visitas"
            :class="isActive('/supervisor/visitas') ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-400 hover:text-white hover:bg-slate-800/60'"
            class="px-3 py-1.5 rounded-md text-xs font-semibold transition"
          >
            Auditoría de Visitas
          </Link>
          <Link
            href="/supervisor/tracking"
            :class="isActive('/supervisor/tracking') ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-400 hover:text-white hover:bg-slate-800/60'"
            class="px-3 py-1.5 rounded-md text-xs font-semibold transition"
          >
            Telemetría de Recorrido
          </Link>
        </nav>
      </div>

      <!-- Usuario y Cierre de Sesión -->
      <div class="flex items-center gap-4">
        <span class="text-xs text-slate-400">
          Supervisor: <strong class="text-white">{{ $page.props.auth?.user?.username || 'Activo' }}</strong>
        </span>
        <button
          @click="logout"
          class="text-xs text-rose-400 hover:text-rose-300 font-semibold px-2.5 py-1 rounded bg-rose-950/40 border border-rose-900/60 hover:bg-rose-900/40 transition"
        >
          Salir
        </button>
      </div>
    </header>

    <!-- ÁREA DE CONTENIDO -->
    <main class="flex-1 flex overflow-hidden">
      <slot />
    </main>
  </div>
</template>

<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';

const page = usePage();

const isActive = (routePath) => {
  return page.url.startsWith(routePath);
};

const logout = () => {
  router.post('/logout');
};
</script>