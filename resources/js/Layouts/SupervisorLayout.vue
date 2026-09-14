<template>
  <div class="flex h-screen bg-gray-100 font-sans text-gray-900">
    <!-- Sidebar -->
    <aside class="w-64 bg-slate-900 text-white flex flex-col shadow-xl z-20">
      <div class="p-5 border-b border-slate-800">
        <h1 class="text-xl font-bold tracking-wider text-amber-500">SUPERVISIÓN</h1>
        <p class="text-xs text-slate-400 mt-1">Control de Telemetría y Rutas</p>
      </div>

      <nav class="flex-1 px-3 py-6 space-y-2">
        <Link
          :href="route('supervisor.index')"
          class="flex items-center px-4 py-3 rounded-lg text-sm font-semibold transition"
          :class="$page.component === 'Supervisor/Index' ? 'bg-amber-600 text-white' : 'text-slate-300 hover:bg-slate-800'"
        >
          <span>Principal / Monitoreo</span>
        </Link>

        <Link
          :href="route('supervisor.ruteo.index')"
          class="flex items-center px-4 py-3 rounded-lg text-sm font-semibold transition"
          :class="$page.component === 'Supervisor/Ruteo' ? 'bg-amber-600 text-white' : 'text-slate-300 hover:bg-slate-800'"
        >
          <span>Plan de Ruteo</span>
        </Link>

        <Link
          :href="route('supervisor.tracking.index')"
          class="flex items-center px-4 py-3 rounded-lg text-sm font-semibold transition"
          :class="$page.component === 'Supervisor/Tracking' ? 'bg-amber-600 text-white' : 'text-slate-300 hover:bg-slate-800'"
        >
          <span>Tracking & Rutinas</span>
        </Link>

        <Link
          :href="route('supervisor.visitas.index')"
          class="flex items-center px-4 py-3 rounded-lg text-sm font-semibold transition"
          :class="$page.component === 'Supervisor/Visitas' ? 'bg-amber-600 text-white' : 'text-slate-300 hover:bg-slate-800'"
        >
          <span>Auditoría de Visitas</span>
        </Link>
      </nav>

      <div class="p-4 border-t border-slate-800">
        <Link
          href="/logout"
          method="post"
          as="button"
          class="w-full text-left px-4 py-2 text-sm text-red-400 hover:bg-slate-800 rounded-lg transition"
        >
          Cerrar Sesión
        </Link>
      </div>
    </aside>

    <!-- Main Content -->
    <div class="flex-1 flex flex-col overflow-hidden">
      <header class="bg-white border-b border-gray-200 h-16 flex items-center justify-between px-6 z-10">
        <div class="flex items-center space-x-3">
          <span class="inline-block w-2.5 h-2.5 rounded-full bg-green-500 animate-pulse"></span>
          <h2 class="text-lg font-bold text-gray-800">Panel Central de Operaciones</h2>
        </div>
        <div class="flex items-center space-x-4">
          <div class="text-right">
            <p class="text-sm font-bold text-gray-700">{{ $page.props.auth?.user?.username || 'Supervisor' }}</p>
            <p class="text-xs text-gray-400">Rol: Supervisor</p>
          </div>
        </div>
      </header>

      <main class="flex-1 overflow-y-auto p-6">
        <slot />
      </main>
    </div>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
</script>