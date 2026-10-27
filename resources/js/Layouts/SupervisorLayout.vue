<template>
  <div
    class="bg-transparent font-sans text-ink flex flex-col md:flex-row antialiased selection:bg-[#0071E3] selection:text-white"
    :class="fullBleed ? 'h-[100dvh] md:h-screen overflow-hidden' : 'min-h-[100dvh]'"
  >
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- 1. Header Mobile / Tablet (Sticky Topbar)                       -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <header class="md:hidden apple-glass-topbar h-13 px-3.5 flex items-center justify-between sticky top-0 z-40 safe-top shrink-0 select-none">
      <div class="flex items-center space-x-2.5 min-w-0">
        <!-- Botón Hamburguesa Apple Glass -->
        <button
          type="button"
          @click="isMobileMenuOpen = true"
          class="w-9 h-9 flex items-center justify-center text-[#1D1D1F] active:scale-95 rounded-[10px] apple-control-pill transition-all cursor-pointer shrink-0 shadow-xs"
          aria-label="Abrir menú de navegación"
        >
          <svg class="w-5 h-5 text-[#1D1D1F]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
          </svg>
        </button>

        <!-- Identidad de Marca y Módulo Activo -->
        <div class="flex items-center space-x-1.5 truncate">
          <div class="w-6 h-6 rounded-[7px] bg-[#0071E3] text-white flex items-center justify-center font-mono text-[10px] font-black shadow-xs shrink-0">
            SV
          </div>
          <span class="text-xs font-bold tracking-tight text-[#1D1D1F] font-mono shrink-0">
            {{ navigationConfig.appName }}
          </span>
          <span v-if="currentModule" class="text-[10px] font-mono text-[#86868B] shrink-0">/</span>
          <span v-if="currentModule" class="text-xs font-semibold text-[#0071E3] truncate font-mono">
            {{ currentModule.shortName || currentModule.name }}
          </span>
        </div>
      </div>

      <!-- Estado y Operador -->
      <div class="flex items-center space-x-2 shrink-0">
        <div class="flex items-center gap-1.5 px-2.5 py-1 rounded-[8px] apple-control-pill text-[11px] font-mono text-[#1D1D1F]">
          <span
            class="w-1.5 h-1.5 rounded-full shrink-0"
            :class="$page.props.auth?.user?.is_superadmin ? 'bg-purple-500' : 'bg-emerald-500 animate-pulse'"
          ></span>
          <span class="font-semibold truncate max-w-[100px]">
            {{ $page.props.auth?.user?.username || 'Usuario' }}
          </span>
          <span
            v-if="$page.props.auth?.user?.is_superadmin"
            class="text-[9px] uppercase font-bold px-1.5 py-0.2 rounded bg-purple-50 text-purple-700 border border-purple-200"
          >
            Superadmin
          </span>
        </div>
      </div>
    </header>

    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- 2. Backdrop Drawer Móvil                                        -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <transition
      enter-active-class="transition-opacity duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition-opacity duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="isMobileMenuOpen"
        @click="isMobileMenuOpen = false"
        class="fixed inset-0 bg-slate-900/40 z-50 md:hidden backdrop-blur-xs"
        aria-hidden="true"
      ></div>
    </transition>

    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- 3. Sidebar Principal (Desktop y Drawer Móvil)                   -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <aside
      class="fixed md:sticky top-0 left-0 bottom-0 z-50 apple-glass-sidebar flex flex-col justify-between transition-all duration-200 ease-in-out h-screen shadow-2xl md:shadow-none"
      :class="[
        // Drawer móvil
        isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0',
        // Ancho responsivo
        isSidebarCollapsed ? 'w-72 md:w-[72px]' : 'w-72 md:w-64'
      ]"
    >
      <div class="flex flex-col flex-1 min-h-0 safe-top">
        <!-- Logo / Header de Marca del Sidebar -->
        <div
          class="h-14 border-b border-white/60 flex items-center justify-between shrink-0 bg-white/40"
          :class="isSidebarCollapsed ? 'px-3.5 md:px-2 md:justify-center' : 'px-3.5'"
        >
          <!-- Título y Badge (visible en móvil y desktop expandido) -->
          <div
            class="flex items-center space-x-2.5 truncate"
            :class="isSidebarCollapsed ? 'md:hidden' : 'flex'"
          >
            <div class="w-7 h-7 rounded-lg bg-[#0071E3] text-white flex items-center justify-center font-mono text-xs font-bold shadow-xs shrink-0">
              SV
            </div>
            <div class="truncate">
              <div class="flex items-center space-x-1.5">
                <span class="text-xs font-bold tracking-tight text-ink font-mono">{{ navigationConfig.appName }}</span>
                <span class="text-[9px] font-mono font-semibold px-1.5 py-0.2 rounded bg-sky-50 text-sky-700 border border-sky-200">
                  {{ navigationConfig.badge }}
                </span>
              </div>
              <p class="text-[10px] text-ink-tertiary font-mono">Panel Central v2.0</p>
            </div>
          </div>

          <!-- Si está colapsado en desktop: icono centrado que expande al click -->
          <div v-if="isSidebarCollapsed" class="hidden md:flex items-center justify-center">
            <button
              type="button"
              @click="toggleSidebarCollapse"
              class="w-8 h-8 rounded-lg bg-[#0071E3] text-white flex items-center justify-center font-mono text-xs font-bold shadow-xs hover:scale-105 active:scale-95 transition-all cursor-pointer"
              title="Expandir barra lateral"
            >
              SV
            </button>
          </div>

          <!-- Botón de colapsar / expandir en desktop (cuando está expandido) -->
          <button
            v-if="!isSidebarCollapsed"
            type="button"
            @click="toggleSidebarCollapse"
            class="hidden md:flex items-center justify-center w-7 h-7 rounded-[8px] apple-control-pill text-[#6E6E73] hover:text-[#1D1D1F] active:scale-95 transition-all cursor-pointer shrink-0"
            title="Colapsar barra lateral"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M18.75 19.5l-7.5-7.5 7.5-7.5m-6 15L5.25 12l7.5-7.5" />
            </svg>
          </button>

          <!-- Botón de cerrar en móvil (Drawer) -->
          <button
            type="button"
            @click="isMobileMenuOpen = false"
            class="md:hidden text-[#6E6E73] hover:text-[#1D1D1F] p-1.5 rounded-lg active:scale-95 cursor-pointer apple-control-pill"
            aria-label="Cerrar menú"
          >
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>

        <!-- Lista de Navegación del Sidebar -->
        <nav class="p-2 md:p-2.5 space-y-1 overflow-y-auto flex-1 scroll-touch custom-scroll">
          <template v-for="item in visibleMainMenu" :key="item.id">
            <div>
              <div
                class="group flex items-center justify-between text-xs font-medium rounded-lg transition-all"
                :class="[
                  isSidebarCollapsed ? 'px-3 py-2 md:px-0 md:py-2.5 md:justify-center' : 'px-3 py-2',
                  isItemActive(item)
                    ? 'bg-[#0071E3] text-white shadow-[0_2px_8px_rgba(0,113,227,0.35)] font-semibold'
                    : 'text-ink-secondary hover:text-ink hover:bg-white/60'
                ]"
              >
                <!-- Enlace Principal -->
                <Link
                  :href="getRouteHref(item)"
                  class="flex items-center space-x-2.5 flex-1 min-w-0"
                  :class="isSidebarCollapsed ? 'md:justify-center md:space-x-0' : ''"
                  @click="isMobileMenuOpen = false"
                  :title="item.name"
                >
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
                    <!-- Avance -->
                    <path v-else-if="item.id === 'avance'" stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941" />
                    <!-- Preventas -->
                    <path v-else-if="item.id === 'preventas'" stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
                    <!-- Tracking -->
                    <path v-else-if="item.id === 'tracking'" stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0zM19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                    <!-- Visitas -->
                    <path v-else-if="item.id === 'visitas'" stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316zM16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z" />
                    <!-- Altas/Ediciones -->
                    <path v-else-if="item.id === 'altas-ediciones'" stroke-linecap="round" stroke-linejoin="round" d="M19 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zM4 19.235v-.11a6.375 6.375 0 0112.75 0v.109A12.318 12.318 0 0110.374 21c-2.331 0-4.512-.645-6.374-1.765z" />
                    <!-- Saneamiento Padrón -->
                    <path v-else-if="item.id === 'saneamiento'" stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                    <!-- Jornadas -->
                    <path v-else-if="item.id === 'jornadas'" stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                    <!-- Horarios -->
                    <path v-else-if="item.id === 'horarios'" stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                    <!-- Módulos & Ajustes -->
                    <path v-else-if="item.id === 'modulos'" stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 11-3 0m3 0a1.5 1.5 0 10-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m-9.75 0h9.75" />
                    <!-- Centro de Ingesta -->
                    <path v-else-if="item.id === 'ingesta'" stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                    <!-- Permisos Supervisores (Admin) -->
                    <path v-else-if="item.id === 'admin-permisos'" stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
                    <!-- Fallback -->
                    <path v-else stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                  </svg>

                  <!-- Texto del Item (oculto solo cuando desktop está colapsado) -->
                  <span
                    class="truncate"
                    :class="isSidebarCollapsed ? 'md:hidden' : 'inline'"
                  >
                    {{ item.name }}
                  </span>
                </Link>

                <!-- Badge y Chevron de Submódulos (ocultos en desktop colapsado) -->
                <div
                  class="flex items-center space-x-1 shrink-0 ml-1"
                  :class="isSidebarCollapsed ? 'md:hidden' : 'flex'"
                >
                  <span
                    v-if="item.badge"
                    class="text-[9px] font-mono font-bold px-1.5 py-0.2 rounded"
                    :class="isItemActive(item) ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-600 border border-slate-200'"
                  >
                    {{ item.badge }}
                  </span>

                  <!-- Botón Desplegable para Submódulos -->
                  <button
                    v-if="getVisibleSubmodules(item).length > 0"
                    type="button"
                    @click.stop="toggleSubmenu(item.id)"
                    class="p-0.5 rounded hover:bg-black/10 transition-transform duration-200 cursor-pointer"
                    :class="isSubmenuExpanded(item.id) ? 'rotate-180' : ''"
                    aria-label="Alternar submódulos"
                  >
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                  </button>
                </div>
              </div>

              <!-- Lista de Submódulos Desplegables (lateral/drawer) -->
              <div
                v-if="getVisibleSubmodules(item).length > 0 && isSubmenuExpanded(item.id)"
                class="pl-6 pr-1 py-1 space-y-0.5 border-l-2 border-black/[0.08] ml-4 mt-0.5"
                :class="isSidebarCollapsed ? 'md:hidden' : 'block'"
              >
                <template v-for="(sub, subIdx) in getVisibleSubmodules(item)" :key="sub.id || sub.route">
                  <!-- Separador de grupo si corresponde -->
                  <div
                    v-if="sub.group && (subIdx === 0 || getVisibleSubmodules(item)[subIdx - 1]?.group !== sub.group)"
                    class="text-[9px] font-bold uppercase tracking-wider text-[#86868B] px-2.5 pt-2 pb-0.5 font-mono select-none"
                  >
                    {{ sub.group }}
                  </div>
                  <Link
                    :href="resolveSubRoute(sub)"
                    class="flex items-center justify-between px-2.5 py-1.5 text-[11px] rounded-[7px] transition-colors"
                    :class="isSubActive(sub)
                      ? 'bg-[#0071E3]/10 text-[#0071E3] font-bold'
                      : 'text-[#6E6E73] hover:text-[#1D1D1F] hover:bg-white/50 font-medium'"
                    @click="isMobileMenuOpen = false"
                  >
                    <span class="truncate">{{ sub.name }}</span>
                    <span v-if="isSubActive(sub)" class="w-1.5 h-1.5 rounded-full bg-[#0071E3] shrink-0"></span>
                  </Link>
                </template>
              </div>
            </div>
          </template>
        </nav>
      </div>

      <!-- Footer de Sesión Minimalista -->
      <div class="p-2.5 md:p-3 border-t border-white/60 bg-white/30 shrink-0 safe-bottom">
        <div
          class="apple-control-pill rounded-card p-2 flex items-center justify-between"
          :class="isSidebarCollapsed ? 'md:p-1.5 md:justify-center' : 'p-2'"
        >
          <!-- Datos de usuario (ocultos en desktop colapsado) -->
          <div
            class="overflow-hidden pr-2"
            :class="isSidebarCollapsed ? 'md:hidden' : 'block'"
          >
            <div class="flex items-center space-x-1.5">
              <span
                class="w-1.5 h-1.5 rounded-full shrink-0"
                :class="$page.props.auth?.user?.is_superadmin ? 'bg-purple-500' : 'bg-emerald-500'"
              ></span>
              <p class="text-xs font-semibold text-ink truncate font-mono">
                {{ $page.props.auth?.user?.username || 'Usuario' }}
              </p>
            </div>
            <div class="flex items-center gap-1 mt-0.5">
              <span
                v-if="$page.props.auth?.user?.is_superadmin"
                class="text-[9px] uppercase font-bold tracking-wider px-1.5 py-0.2 rounded bg-purple-100 text-purple-700 border border-purple-200 font-mono"
              >
                Superadmin
              </span>
              <span
                v-else
                class="text-[10px] text-ink-tertiary font-mono"
              >
                Supervisor Activo
              </span>
            </div>
          </div>

          <!-- Botón de Cerrar Sesión -->
          <Link
            href="/logout"
            method="post"
            as="button"
            class="p-2 text-ink-tertiary hover:text-rose-600 hover:bg-rose-50 rounded-lg transition-colors cursor-pointer active:scale-95 shrink-0"
            title="Cerrar Sesión"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15M12 9l-3 3m0 0l3 3m-3-3h12.75" />
            </svg>
          </Link>
        </div>
      </div>
    </aside>

    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- 4. Contenedor Principal (Main)                                 -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <main
      class="flex-1 min-w-0 flex flex-col"
      :class="fullBleed ? 'min-h-0 overflow-hidden pb-[60px] md:pb-0' : 'overflow-x-hidden pb-20 md:pb-8'"
    >
      <!-- Flash Alert Banner (Inertia Session Flash) -->
      <div v-if="page.props.flash?.warning || page.props.flash?.error" class="px-2.5 sm:px-4 lg:px-6 pt-3 max-w-[1750px] w-full mx-auto">
        <div
          v-if="page.props.flash?.warning"
          class="p-3 rounded-[12px] bg-amber-50 text-amber-800 border border-amber-200 text-[11px] font-semibold shadow-sm flex items-center justify-between"
        >
          <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <span>{{ page.props.flash.warning }}</span>
          </div>
        </div>
        <div
          v-if="page.props.flash?.error"
          class="p-3 rounded-[12px] bg-red-50 text-red-700 border border-red-200 text-[11px] font-semibold shadow-sm flex items-center justify-between"
        >
          <div class="flex items-center gap-2">
            <svg class="w-4 h-4 text-red-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <span>{{ page.props.flash.error }}</span>
          </div>
        </div>
      </div>

      <div v-if="fullBleed" class="flex-1 w-full flex flex-col min-h-0 overflow-hidden">
        <slot />
      </div>
      <div v-else class="flex-1 p-2.5 sm:p-4 lg:p-6 max-w-[1750px] w-full mx-auto">
        <slot />
      </div>
    </main>

    <!-- ═══════════════════════════════════════════════════════════════ -->
    <!-- 5. Barra de Navegación Inferior Móvil (Dock Táctil)             -->
    <!-- ═══════════════════════════════════════════════════════════════ -->
    <nav
      class="md:hidden fixed bottom-0 left-0 right-0 z-40 apple-glass-topbar h-[60px] px-1 flex items-center justify-around safe-bottom select-none border-t border-black/[0.08] shadow-[0_-4px_24px_rgba(0,0,0,0.08)]"
      aria-label="Navegación rápida móvil"
    >
      <Link
        v-for="dockItem in mobileDockItems"
        :key="dockItem.id"
        :href="getRouteHref(dockItem)"
        class="flex flex-col items-center justify-center flex-1 h-full py-1 px-0.5 transition-all active:scale-95 text-center min-w-0"
        :class="isItemActive(dockItem) ? 'text-[#0071E3] font-bold' : 'text-[#86868B] hover:text-[#1D1D1F]'"
      >
        <div class="relative flex items-center justify-center">
          <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path v-if="dockItem.id === 'inicio'" stroke-linecap="round" stroke-linejoin="round" d="M2.25 12l8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
            <path v-else-if="dockItem.id === 'preventas'" stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
            <path v-else-if="dockItem.id === 'tracking'" stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0zM19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
            <path v-else-if="dockItem.id === 'ruteo'" stroke-linecap="round" stroke-linejoin="round" d="M9 6.75V15m6-6v8.25m.503 3.498l4.875-2.437c.381-.19.622-.58.622-1.006V4.82c0-.836-.88-1.38-1.628-1.006l-3.869 1.934a1.12 1.12 0 01-1.006 0L9.503 3.31a1.12 1.12 0 00-1.006 0L3.623 5.748A1.125 1.125 0 003 6.754v12.37c0 .836.88 1.38 1.628 1.006l3.869-1.934a1.12 1.12 0 011.006 0l4.994 2.497a1.12 1.12 0 001.006 0z" />
            <path v-else-if="dockItem.id === 'ingesta'" stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
            <path v-else-if="dockItem.id === 'avance'" stroke-linecap="round" stroke-linejoin="round" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941" />
            <path v-else stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
          </svg>
          <span v-if="isItemActive(dockItem)" class="absolute -bottom-1 w-1 h-1 rounded-full bg-[#0071E3]"></span>
        </div>
        <span class="text-[9.5px] mt-0.5 tracking-tight truncate max-w-full font-mono">
          {{ dockItem.shortName || dockItem.name }}
        </span>
      </Link>

      <!-- Botón Más / Menú -->
      <button
        type="button"
        @click="isMobileMenuOpen = true"
        class="flex flex-col items-center justify-center flex-1 h-full py-1 px-0.5 transition-all active:scale-95 text-center min-w-0 cursor-pointer"
        :class="isMobileMenuOpen ? 'text-[#0071E3] font-bold' : 'text-[#86868B] hover:text-[#1D1D1F]'"
      >
        <div class="relative flex items-center justify-center">
          <svg class="w-5 h-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
          </svg>
        </div>
        <span class="text-[9.5px] mt-0.5 tracking-tight truncate max-w-full font-mono">
          Menú
        </span>
      </button>
    </nav>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted, onUnmounted } from 'vue';
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
const isSidebarCollapsed = ref(false);
const expandedSubmenus = reactive({});

onMounted(() => {
  try {
    const saved = localStorage.getItem('supervisor_sidebar_collapsed');
    if (saved !== null) {
      isSidebarCollapsed.value = saved === 'true';
    }
  } catch {
    // localStorage no disponible en contexto restringido
  }

  window.addEventListener('keydown', handleKeyDown);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleKeyDown);
});

function handleKeyDown(e) {
  if (e.key === 'Escape' && isMobileMenuOpen.value) {
    isMobileMenuOpen.value = false;
  }
}

function toggleSidebarCollapse() {
  isSidebarCollapsed.value = !isSidebarCollapsed.value;
  try {
    localStorage.setItem('supervisor_sidebar_collapsed', String(isSidebarCollapsed.value));
  } catch {
    // ignore
  }
}

function toggleSubmenu(itemId) {
  expandedSubmenus[itemId] = !expandedSubmenus[itemId];
}

function isSubmenuExpanded(itemId) {
  return Boolean(expandedSubmenus[itemId]);
}

function getRouteHref(item) {
  if (item.submodules && item.submodules.length > 0 && !isSuperAdmin.value) {
    const allowedSub = getVisibleSubmodules(item);
    if (allowedSub.length > 0) {
      return resolveSubRoute(allowedSub[0]);
    }
  }
  try {
    return route(item.route);
  } catch {
    return item.route.startsWith('/') ? item.route : `/${item.route.replace('.', '/')}`;
  }
}

function resolveSubRoute(sub) {
  if (sub.href) return sub.href;
  if (!sub.route) return '#';
  try {
    return route(sub.route);
  } catch {
    return sub.route.startsWith('/') ? sub.route : `/${sub.route.replace('.', '/')}`;
  }
}

function isItemActive(item) {
  if (item.componentPrefix) {
    if (Array.isArray(item.componentPrefix)) {
      return item.componentPrefix.some(p => page.component.startsWith(p));
    }
    return page.component.startsWith(item.componentPrefix);
  }
  try {
    return route().current(item.route);
  } catch {
    return false;
  }
}

function isSubActive(sub) {
  if (sub.component) {
    return page.component === sub.component;
  }
  if (sub.route) {
    try {
      return route().current(sub.route);
    } catch {
      return false;
    }
  }
  return false;
}

// Control de permisos de acceso a módulos
const authUser = computed(() => page.props.auth?.user);
const isSuperAdmin = computed(() => !!authUser.value?.is_superadmin);
const userPermissions = computed(() => authUser.value?.permissions);

function isModuleAllowed(item) {
  if (item.superAdminOnly) {
    return isSuperAdmin.value;
  }
  if (isSuperAdmin.value) {
    return true;
  }
  // El Panel General siempre está disponible para el supervisor
  if (item.id === 'inicio') {
    return true;
  }
  const perms = userPermissions.value;
  if (!perms) {
    return false;
  }
  if (perms.is_all_allowed) {
    return true;
  }
  // Soporte para módulos unificados (permissionModules)
  const requiredModules = item.permissionModules || [item.id];
  const hasAnyMod = requiredModules.some(m => perms.modules && perms.modules[m] === true);
  if (!hasAnyMod) {
    return false;
  }
  if (item.submodules && item.submodules.length > 0) {
    return getVisibleSubmodules(item).length > 0;
  }
  return true;
}

function getVisibleSubmodules(item) {
  if (!item.submodules || item.submodules.length === 0) {
    return [];
  }
  if (isSuperAdmin.value) {
    return item.submodules;
  }
  const perms = userPermissions.value;
  if (!perms) {
    return [];
  }
  if (perms.is_all_allowed) {
    return item.submodules;
  }
  return item.submodules.filter(sub => {
    const parentModule = sub.module || item.id;
    if (!perms.modules || perms.modules[parentModule] !== true) {
      return false;
    }
    const key = `${parentModule}.${sub.id}`;
    if (perms.submodules && Object.prototype.hasOwnProperty.call(perms.submodules, key)) {
      return perms.submodules[key] === true;
    }
    return false;
  });
}

const visibleMainMenu = computed(() => {
  return navigationConfig.mainMenu.filter(item => isModuleAllowed(item));
});

// Auto-expandir el submódulo del módulo actualmente activo
watch(
  () => page.component,
  () => {
    visibleMainMenu.value.forEach(item => {
      if (item.submodules && item.submodules.length > 0) {
        if (isItemActive(item)) {
          expandedSubmenus[item.id] = true;
        }
      }
    });
  },
  { immediate: true }
);

const currentModule = computed(() => {
  return visibleMainMenu.value.find(item => isItemActive(item));
});

const mobileDockItems = computed(() => {
  const inicio = visibleMainMenu.value.find(m => m.id === 'inicio');
  const items = [];
  if (inicio) items.push(inicio);

  const preferredKeys = ['preventas', 'auditoria', 'tracking', 'ruteo', 'ingesta', 'avance', 'modulos', 'visitas'];
  for (const key of preferredKeys) {
    if (items.length >= 4) break;
    const found = visibleMainMenu.value.find(m => m.id === key);
    if (found && !items.includes(found)) {
      items.push(found);
    }
  }

  for (const m of visibleMainMenu.value) {
    if (items.length >= 4) break;
    if (!items.includes(m) && !m.superAdminOnly) {
      items.push(m);
    }
  }

  return items;
});
</script>
