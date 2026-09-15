<template>
  <div class="min-h-screen bg-gray-100 font-sans text-gray-900 flex flex-col justify-between">
    <!-- Header Superior Delgado -->
    <header class="bg-white border-b border-gray-200 h-14 sticky top-0 z-30 px-4 sm:px-6 flex items-center justify-between shadow-xs">
      <div class="flex items-center space-x-2 sm:space-x-3">
        <span class="inline-block w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
        <h1 class="text-sm sm:text-base font-black tracking-wider text-slate-900">
          SUPERVISIÓN <span class="text-amber-500 font-extrabold text-xs sm:text-sm">GPS</span>
        </h1>
      </div>

      <div class="flex items-center space-x-3 sm:space-x-4">
        <div class="text-right">
          <p class="text-xs sm:text-sm font-bold text-slate-800 leading-tight">
            {{ $page.props.auth?.user?.username || 'Supervisor' }}
          </p>
          <p class="text-[10px] text-slate-400 leading-none">Operador</p>
        </div>

        <!-- Botón Salir / Logout Discreto -->
        <Link
          href="/logout"
          method="post"
          as="button"
          class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-rose-50 rounded-lg transition"
          title="Cerrar Sesión"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
          </svg>
        </Link>
      </div>
    </header>

    <!-- Contenido Principal con Padding Inferior para no solapar el menú -->
    <main class="flex-1 p-3 sm:p-5 lg:p-6 pb-24 sm:pb-20 max-w-7xl w-full mx-auto">
      <slot />
    </main>

    <!-- Barra de Navegación Inferior Fija (Bottom Navigation Bar) -->
    <nav class="fixed bottom-0 inset-x-0 bg-slate-900/95 backdrop-blur-md border-t border-slate-800 z-40 px-2 py-1.5 shadow-2xl">
      <div class="max-w-md mx-auto grid grid-cols-4 gap-1">
        <!-- 1. Principal / Dashboard -->
        <Link
          :href="route('supervisor.index')"
          class="flex flex-col items-center justify-center py-1 rounded-xl transition relative group"
          :class="$page.component === 'Supervisor/Index' ? 'text-amber-400' : 'text-slate-400 hover:text-slate-200'"
          title="Principal"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
          </svg>
          <span class="text-[10px] font-bold mt-0.5">Inicio</span>
          <span
            v-if="$page.component === 'Supervisor/Index'"
            class="absolute bottom-0 w-8 h-0.5 bg-amber-400 rounded-full"
          ></span>
        </Link>

        <!-- 2. Plan de Ruteo -->
        <Link
          :href="route('supervisor.ruteo.index')"
          class="flex flex-col items-center justify-center py-1 rounded-xl transition relative group"
          :class="$page.component === 'Supervisor/Ruteo' ? 'text-amber-400' : 'text-slate-400 hover:text-slate-200'"
          title="Plan de Ruteo"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
          </svg>
          <span class="text-[10px] font-bold mt-0.5">Ruteo</span>
          <span
            v-if="$page.component === 'Supervisor/Ruteo'"
            class="absolute bottom-0 w-8 h-0.5 bg-amber-400 rounded-full"
          ></span>
        </Link>

        <!-- 3. Tracking & Rutinas -->
        <Link
          :href="route('supervisor.tracking.index')"
          class="flex flex-col items-center justify-center py-1 rounded-xl transition relative group"
          :class="$page.component === 'Supervisor/Tracking' ? 'text-amber-400' : 'text-slate-400 hover:text-slate-200'"
          title="Tracking En Vivo"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
          </svg>
          <span class="text-[10px] font-bold mt-0.5">Tracking</span>
          <span
            v-if="$page.component === 'Supervisor/Tracking'"
            class="absolute bottom-0 w-8 h-0.5 bg-amber-400 rounded-full"
          ></span>
        </Link>

        <!-- 4. Auditoría de Visitas -->
        <Link
          :href="route('supervisor.visitas.index')"
          class="flex flex-col items-center justify-center py-1 rounded-xl transition relative group"
          :class="$page.component === 'Supervisor/Visitas' ? 'text-amber-400' : 'text-slate-400 hover:text-slate-200'"
          title="Auditoría de Visitas"
        >
          <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          <span class="text-[10px] font-bold mt-0.5">Visitas</span>
          <span
            v-if="$page.component === 'Supervisor/Visitas'"
            class="absolute bottom-0 w-8 h-0.5 bg-amber-400 rounded-full"
          ></span>
        </Link>
      </div>
    </nav>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
</script>