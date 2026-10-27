<template>
  <SupervisorLayout title="Gestión de Permisos de Supervisores">
    <div class="space-y-4 max-w-7xl mx-auto pb-12 font-sans">
      <!-- Navegación de Submódulos Admin (Permisos & Usuarios) -->
      <AdminSubTabs active-tab="permisos" />

      <!-- Encabezado de la Vista -->
      <div class="apple-glass-card rounded-[18px] p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-sm border border-black/[0.04]">
        <div>
          <div class="flex items-center gap-2">
            <h1 class="text-[17px] font-bold text-[#1D1D1F] tracking-tight">
              Gestión de Permisos
            </h1>
            <span class="text-[9px] font-mono font-bold px-2 py-0.5 rounded bg-purple-50 text-purple-700 border border-purple-200">
              ROL SUPERADMIN
            </span>
          </div>
          <p class="text-[11px] text-[#86868B] mt-0.5">
            Configuración y control granular de acceso a módulos y submódulos (incluyendo Centro de Ingesta) por supervisor y administrador
          </p>
        </div>

        <div class="flex items-center gap-2">
          <button
            type="button"
            @click="recargar"
            :disabled="guardando"
            class="px-3 py-1.5 text-[11px] font-semibold rounded-[10px] bg-white text-[#1D1D1F] border border-black/[0.12] hover:bg-black/[0.03] transition-all cursor-pointer flex items-center gap-1.5 shadow-sm"
          >
            <span>↻</span>
            <span>Recargar Supervisores</span>
          </button>
        </div>
      </div>

      <!-- Alertas de Éxito / Error -->
      <div
        v-if="mensajeExito"
        class="p-3.5 rounded-[14px] bg-emerald-50 text-emerald-800 border border-emerald-200 text-[11px] font-semibold shadow-sm flex items-center justify-between"
      >
        <span>{{ mensajeExito }}</span>
        <button type="button" @click="mensajeExito = null" class="text-emerald-800 hover:opacity-70 p-0.5">✕</button>
      </div>

      <div
        v-if="mensajeError"
        class="p-3.5 rounded-[14px] bg-red-50 text-red-700 border border-red-200 text-[11px] font-semibold shadow-sm flex items-center justify-between"
      >
        <span>{{ mensajeError }}</span>
        <button type="button" @click="mensajeError = null" class="text-red-700 hover:opacity-70 p-0.5">✕</button>
      </div>

      <!-- Contenedor Maestro: Selector de Supervisor + Matriz de Permisos -->
      <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 items-start">
        <!-- Columna Izquierda: Lista de Supervisores (4 cols en desktop, ocultable en móvil si hay uno seleccionado) -->
        <div
          class="lg:col-span-4 space-y-3"
          :class="supervisorSeleccionado ? 'hidden lg:block' : 'block'"
        >
          <div class="apple-glass-card rounded-[18px] p-3 shadow-sm border border-black/[0.04]">
            <div class="flex items-center justify-between mb-2.5 px-1">
              <span class="text-[12px] font-bold text-[#1D1D1F]">Usuarios Operativos</span>
              <span class="text-[10px] font-mono font-semibold text-[#86868B] bg-black/[0.04] px-2 py-0.5 rounded-full">
                {{ supervisoresFiltrados.length }} usuarios
              </span>
            </div>

            <!-- Buscador -->
            <div class="relative mb-2.5">
              <input
                v-model="busquedaSupervisor"
                type="text"
                placeholder="Buscar por usuario, rol o ruta..."
                class="w-full px-3 py-1.5 pl-8 text-[11px] rounded-[10px] border border-black/[0.12] bg-white focus:outline-none focus:border-[#0071E3] font-sans"
              />
              <svg class="w-3.5 h-3.5 text-[#86868B] absolute left-2.5 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </div>

            <!-- Lista de Supervisores -->
            <div class="space-y-1.5 max-h-[580px] overflow-y-auto custom-scroll pr-0.5">
              <div
                v-if="supervisoresFiltrados.length === 0"
                class="py-8 text-center text-[#86868B] text-[11px]"
              >
                No se encontraron usuarios.
              </div>

              <div
                v-for="sup in supervisoresFiltrados"
                :key="sup.id"
                @click="seleccionarSupervisor(sup)"
                class="p-2.5 rounded-[12px] transition-all cursor-pointer border select-none"
                :class="supervisorSeleccionado?.id === sup.id
                  ? 'bg-[#0071E3] text-white border-[#0071E3] shadow-[0_2px_8px_rgba(0,113,227,0.35)]'
                  : 'bg-white/80 hover:bg-white border-black/[0.05] text-[#1D1D1F]'"
              >
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-1.5 truncate">
                    <span class="font-bold text-[12px] truncate">{{ sup.username }}</span>
                    <span
                      v-if="sup.role && sup.role !== 'supervisor'"
                      class="text-[8.5px] font-mono font-bold px-1.5 py-0.2 rounded"
                      :class="supervisorSeleccionado?.id === sup.id
                        ? 'bg-white/20 text-white'
                        : 'bg-purple-50 text-purple-700 border border-purple-200'"
                    >
                      {{ sup.role }}
                    </span>
                  </div>
                  <span
                    class="text-[9px] font-mono font-bold px-1.5 py-0.2 rounded"
                    :class="supervisorSeleccionado?.id === sup.id
                      ? 'bg-white/20 text-white'
                      : (sup.is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-red-50 text-red-700 border border-red-200')"
                  >
                    {{ sup.is_active ? 'Activo' : 'Inactivo' }}
                  </span>
                </div>

                <div class="flex items-center justify-between mt-1 text-[10px]" :class="supervisorSeleccionado?.id === sup.id ? 'text-white/80' : 'text-[#86868B]'">
                  <span>{{ sup.ruta ? 'Ruta: ' + sup.ruta : (sup.role || 'Sin ruta') }}</span>
                  <span v-if="sup.permissions_map?.is_all_allowed" class="italic">Acceso Global</span>
                  <span v-else-if="sup.total_custom_rules === 0" class="italic font-medium" :class="supervisorSeleccionado?.id === sup.id ? 'text-white/90' : 'text-amber-600'">Sin permisos</span>
                  <span v-else class="font-semibold">{{ sup.total_custom_rules }} reglas asignadas</span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Columna Derecha: Matriz de Permisos (8 cols en desktop, visible en móvil solo si hay uno seleccionado) -->
        <div
          class="lg:col-span-8 space-y-3"
          :class="!supervisorSeleccionado ? 'hidden lg:block' : 'block'"
        >
          <!-- Botón Móvil: Volver a la Lista de Supervisores -->
          <div v-if="supervisorSeleccionado" class="lg:hidden flex items-center justify-between p-2.5 rounded-[14px] bg-white border border-black/[0.08] shadow-xs">
            <button
              type="button"
              @click="supervisorSeleccionado = null"
              class="text-[11px] font-bold text-[#0071E3] flex items-center gap-1.5 active:scale-95 cursor-pointer"
            >
              <span>←</span>
              <span>Cambiar de supervisor</span>
            </button>
            <div class="flex items-center gap-1.5 text-[10px] font-mono">
              <span class="text-[#86868B]">Editando:</span>
              <span class="font-bold text-[#1D1D1F]">{{ supervisorSeleccionado.username }}</span>
            </div>
          </div>
          <div v-if="!supervisorSeleccionado" class="apple-glass-card rounded-[18px] p-12 text-center text-[#86868B] shadow-sm border border-black/[0.04]">
            <svg class="w-12 h-12 mx-auto text-[#86868B]/40 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z" />
            </svg>
            <div class="text-[13px] font-bold text-[#1D1D1F]">Selecciona un supervisor</div>
            <div class="text-[11px] text-[#86868B] mt-0.5">Elige un supervisor de la lista izquierda para editar sus permisos de módulos.</div>
          </div>

          <div v-else class="space-y-3">
            <!-- Barra de Acciones del Supervisor Seleccionado -->
            <div class="apple-glass-card rounded-[18px] p-3.5 shadow-sm border border-black/[0.04] bg-white/90 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
              <div>
                <div class="flex items-center gap-2">
                  <span class="text-[13px] font-bold text-[#1D1D1F]">
                    Permisos de {{ supervisorSeleccionado.username }}
                  </span>
                  <span
                    v-if="hayCambiosSinGuardar"
                    class="px-2 py-0.5 rounded-full text-[9px] font-bold bg-amber-50 text-amber-700 border border-amber-200 animate-pulse"
                  >
                    Cambios sin guardar
                  </span>
                </div>
                <p class="text-[10px] text-[#86868B] mt-0.5">
                  Alterna módulos completos o submódulos individuales y guarda los cambios.
                </p>
              </div>

              <!-- Botones Rápidos Preset -->
              <div class="flex items-center gap-1.5 flex-wrap">
                <button
                  type="button"
                  @click="aplicarPreset('enable_all')"
                  :disabled="guardando"
                  class="px-2.5 py-1 text-[10px] font-semibold rounded-[8px] bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 transition-all cursor-pointer"
                  title="Habilitar todos los módulos y submódulos"
                >
                  Habilitar Todo
                </button>
                <button
                  type="button"
                  @click="aplicarPreset('disable_all')"
                  :disabled="guardando"
                  class="px-2.5 py-1 text-[10px] font-semibold rounded-[8px] bg-red-50 text-red-700 border border-red-200 hover:bg-red-100 transition-all cursor-pointer"
                  title="Bloquear todos los módulos"
                >
                  Deshabilitar Todo
                </button>
                <button
                  type="button"
                  @click="aplicarPreset('reset_default')"
                  :disabled="guardando"
                  class="px-2.5 py-1 text-[10px] font-semibold rounded-[8px] bg-black/[0.04] text-[#6E6E73] border border-black/[0.08] hover:bg-black/[0.08] transition-all cursor-pointer"
                  title="Restablecer al comportamiento global por defecto"
                >
                  Por Defecto
                </button>
                <button
                  type="button"
                  @click="abrirModalClonar"
                  :disabled="guardando"
                  class="px-2.5 py-1 text-[10px] font-semibold rounded-[8px] bg-purple-50 text-purple-700 border border-purple-200 hover:bg-purple-100 transition-all cursor-pointer"
                  title="Copiar permisos de otro supervisor"
                >
                  Clonar de...
                </button>
                <button
                  type="button"
                  @click="guardarMatrizCompleta"
                  :disabled="guardando"
                  class="w-full sm:w-auto justify-center px-4 py-2 sm:py-1 text-[11px] font-bold rounded-[8px] bg-[#0071E3] text-white hover:bg-[#0077ED] active:scale-95 transition-all shadow-[0_2px_8px_rgba(0,113,227,0.35)] cursor-pointer flex items-center gap-1.5 disabled:opacity-50 mt-1 sm:mt-0"
                >
                  <span v-if="guardando" class="animate-spin text-[10px]">↻</span>
                  <span>Guardar Permisos</span>
                </button>
              </div>
            </div>

            <!-- Acordeón / Cards de Módulos del Catálogo -->
            <div class="space-y-2.5">
              <div
                v-for="modulo in catalog"
                :key="modulo.id"
                class="apple-glass-card rounded-[16px] p-3.5 shadow-sm border transition-all"
                :class="esModuloHabilitado(modulo.id)
                  ? 'bg-white/90 border-black/[0.06]'
                  : 'bg-black/[0.02] border-black/[0.04] opacity-80'"
              >
                <!-- Fila Principal del Módulo -->
                <div class="flex items-center justify-between gap-3">
                  <div class="flex items-center gap-2.5 flex-1 min-w-0">
                    <!-- Switch Maestro Módulo -->
                    <label class="relative inline-flex items-center cursor-pointer shrink-0">
                      <input
                        type="checkbox"
                        :checked="esModuloHabilitado(modulo.id)"
                        @change="toggleModuloPrincipal(modulo.id, $event.target.checked)"
                        class="sr-only peer"
                      />
                      <div class="w-8 h-4.5 bg-neutral-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-3.5 after:w-3.5 after:transition-all peer-checked:bg-[#0071E3]"></div>
                    </label>

                    <div class="truncate">
                      <div class="flex items-center gap-2 flex-wrap">
                        <span class="text-[12px] font-bold text-[#1D1D1F]">{{ modulo.name }}</span>
                        <span class="text-[9px] font-mono px-1.5 py-0.2 rounded bg-black/[0.04] text-[#86868B] border border-black/[0.06]">
                          {{ modulo.id }}
                        </span>
                        <span
                          v-if="modulo.submodules && modulo.submodules.length > 0"
                          class="text-[9px] font-semibold text-[#86868B]"
                        >
                          ({{ contarSubmodulosActivos(modulo) }}/{{ modulo.submodules.length }} submódulos)
                        </span>
                        <span
                          v-if="modulo.permission_modules && modulo.permission_modules.length > 1"
                          class="text-[8.5px] font-mono font-bold px-1.5 py-0.2 rounded bg-blue-50 text-blue-700 border border-blue-200"
                        >
                          {{ modulo.permission_modules.join(' + ') }}
                        </span>
                      </div>
                      <p class="text-[10px] text-[#86868B] truncate mt-0.5">{{ modulo.description }}</p>
                    </div>
                  </div>

                  <!-- Botón Desplegar Submódulos si tiene -->
                  <div v-if="modulo.submodules && modulo.submodules.length > 0" class="shrink-0 flex items-center gap-2">
                    <button
                      type="button"
                      @click="toggleExpandir(modulo.id)"
                      class="px-2 py-0.5 text-[10px] font-semibold rounded-[6px] text-[#6E6E73] hover:text-[#1D1D1F] hover:bg-black/[0.04] transition-all cursor-pointer flex items-center gap-1"
                    >
                      <span>{{ modulosExpandidos[modulo.id] ? 'Ocultar' : 'Detalles' }}</span>
                      <svg class="w-3 h-3 transition-transform" :class="modulosExpandidos[modulo.id] ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                      </svg>
                    </button>
                  </div>
                </div>

                <!-- Submódulos Desplegables -->
                <div
                  v-if="modulo.submodules && modulo.submodules.length > 0 && modulosExpandidos[modulo.id]"
                  class="mt-3 pt-3 border-t border-black/[0.05] sm:pl-3 space-y-3"
                >
                  <div class="flex items-center justify-between pb-1">
                    <span class="text-[10px] font-bold uppercase tracking-wider text-[#86868B]">
                      Submódulos específicos
                    </span>
                    <div class="flex items-center gap-2 text-[9px] text-[#0071E3] font-semibold">
                      <button
                        type="button"
                        @click="setTodosSubmodulos(modulo, true)"
                        class="hover:underline cursor-pointer"
                        :disabled="!esModuloHabilitado(modulo.id)"
                      >
                        Marcar todos
                      </button>
                      <span>·</span>
                      <button
                        type="button"
                        @click="setTodosSubmodulos(modulo, false)"
                        class="hover:underline cursor-pointer"
                        :disabled="!esModuloHabilitado(modulo.id)"
                      >
                        Desmarcar todos
                      </button>
                    </div>
                  </div>

                  <!-- CASO A: MÓDULO ORGANIZADO POR GRUPOS (Módulos Unificados) -->
                  <div v-if="moduloTieneGrupos(modulo)" class="space-y-3">
                    <div
                      v-for="grupo in obtenerGruposModulo(modulo)"
                      :key="grupo.nombre"
                      class="p-2.5 rounded-[12px] bg-black/[0.015] border border-black/[0.05] space-y-2"
                    >
                      <!-- Encabezado del Grupo -->
                      <div class="flex items-center justify-between px-1">
                        <div class="flex items-center gap-1.5">
                          <span class="w-1.5 h-1.5 rounded-full bg-[#0071E3]"></span>
                          <span class="text-[11px] font-bold text-[#1D1D1F]">{{ grupo.nombre }}</span>
                          <span class="text-[9px] font-mono font-semibold text-[#86868B] bg-white px-1.5 py-0.2 rounded border border-black/[0.06]">
                            {{ contarSubmodulosGrupoActivos(modulo, grupo.submodules) }}/{{ grupo.submodules.length }}
                          </span>
                        </div>
                        <div class="flex items-center gap-1.5 text-[9px] text-[#0071E3] font-semibold">
                          <button
                            type="button"
                            @click="setGrupoSubmodulos(modulo, grupo.submodules, true)"
                            class="hover:underline cursor-pointer"
                            :disabled="!esModuloHabilitado(modulo.id)"
                          >
                            Activar grupo
                          </button>
                          <span>·</span>
                          <button
                            type="button"
                            @click="setGrupoSubmodulos(modulo, grupo.submodules, false)"
                            class="hover:underline cursor-pointer"
                            :disabled="!esModuloHabilitado(modulo.id)"
                          >
                            Desactivar
                          </button>
                        </div>
                      </div>

                      <!-- Submódulos del Grupo -->
                      <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <div
                          v-for="sub in grupo.submodules"
                          :key="getSubKey(modulo, sub)"
                          class="p-2 rounded-[10px] border flex items-center justify-between gap-2 transition-all"
                          :class="[
                            esSubmoduloHabilitado(modulo, sub) && esModuloHabilitado(modulo.id)
                              ? 'bg-white border-black/[0.08] shadow-2xs'
                              : 'bg-black/[0.02] border-transparent opacity-60',
                            !esModuloHabilitado(modulo.id) ? 'pointer-events-none' : ''
                          ]"
                        >
                          <div class="truncate">
                            <span class="text-[11px] font-medium text-[#1D1D1F] truncate block">{{ sub.name }}</span>
                            <span v-if="sub.module_key" class="text-[8.5px] font-mono text-[#86868B]">
                              {{ sub.module_key }}.{{ sub.id }}
                            </span>
                          </div>

                          <label class="relative inline-flex items-center cursor-pointer shrink-0">
                            <input
                              type="checkbox"
                              :disabled="!esModuloHabilitado(modulo.id)"
                              :checked="esSubmoduloHabilitado(modulo, sub) && esModuloHabilitado(modulo.id)"
                              @change="toggleSubmodulo(modulo, sub, $event.target.checked)"
                              class="sr-only peer"
                            />
                            <div class="w-7 h-4 bg-neutral-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-[#0071E3]"></div>
                          </label>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- CASO B: MÓDULO ESTÁNDAR (SIN GRUPOS) -->
                  <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <div
                      v-for="sub in modulo.submodules"
                      :key="getSubKey(modulo, sub)"
                      class="p-2 rounded-[10px] border flex items-center justify-between gap-2 transition-all"
                      :class="[
                        esSubmoduloHabilitado(modulo, sub) && esModuloHabilitado(modulo.id)
                          ? 'bg-white border-black/[0.08]'
                          : 'bg-black/[0.02] border-transparent opacity-60',
                        !esModuloHabilitado(modulo.id) ? 'pointer-events-none' : ''
                      ]"
                    >
                      <div class="truncate">
                        <span class="text-[11px] font-medium text-[#1D1D1F] truncate block">{{ sub.name }}</span>
                        <span class="text-[8.5px] font-mono text-[#86868B]">
                          {{ modulo.id }}.{{ sub.id }}
                        </span>
                      </div>

                      <label class="relative inline-flex items-center cursor-pointer shrink-0">
                        <input
                          type="checkbox"
                          :disabled="!esModuloHabilitado(modulo.id)"
                          :checked="esSubmoduloHabilitado(modulo, sub) && esModuloHabilitado(modulo.id)"
                          @change="toggleSubmodulo(modulo, sub, $event.target.checked)"
                          class="sr-only peer"
                        />
                        <div class="w-7 h-4 bg-neutral-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-3 after:w-3 after:transition-all peer-checked:bg-[#0071E3]"></div>
                      </label>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Modal de Clonación de Permisos -->
      <div
        v-if="mostrarModalClonar"
        class="fixed inset-0 z-50 bg-black/40 backdrop-blur-sm flex items-center justify-center p-4 font-sans select-none"
      >
        <div class="apple-glass-card rounded-[20px] bg-white p-5 max-w-md w-full shadow-2xl border border-black/[0.08] space-y-4">
          <div class="flex items-center justify-between border-b border-black/[0.06] pb-3">
            <div>
              <h3 class="text-[14px] font-bold text-[#1D1D1F]">Clonar Configuración de Permisos</h3>
              <p class="text-[10px] text-[#86868B] mt-0.5">
                Copiará exactamente la matriz de permisos de otro supervisor hacia {{ supervisorSeleccionado?.username }}.
              </p>
            </div>
            <button
              type="button"
              @click="mostrarModalClonar = false"
              class="p-1 rounded-full text-[#86868B] hover:text-[#1D1D1F] hover:bg-black/[0.05]"
            >
              ✕
            </button>
          </div>

          <div>
            <label class="text-[11px] font-bold text-[#1D1D1F] block mb-1.5">
              Supervisor de Origen (Plantilla):
            </label>
            <select
              v-model="supervisorOrigenId"
              class="w-full px-3 py-2 text-[11px] rounded-[10px] border border-black/[0.12] bg-white font-sans focus:outline-none focus:border-[#0071E3]"
            >
              <option :value="null" disabled>Selecciona un supervisor...</option>
              <option
                v-for="sup in otrosSupervisores"
                :key="sup.id"
                :value="sup.id"
              >
                {{ sup.username }} ({{ sup.ruta || 'Sin ruta' }}) - {{ sup.total_custom_rules }} reglas
              </option>
            </select>
          </div>

          <div class="flex items-center justify-end gap-2 pt-2 border-t border-black/[0.06]">
            <button
              type="button"
              @click="mostrarModalClonar = false"
              class="px-3 py-1.5 text-[11px] font-semibold rounded-[10px] bg-white text-[#6E6E73] border border-black/[0.12] hover:bg-black/[0.03]"
            >
              Cancelar
            </button>
            <button
              type="button"
              @click="ejecutarClonacion"
              :disabled="!supervisorOrigenId || guardando"
              class="px-4 py-1.5 text-[11px] font-bold rounded-[10px] bg-purple-600 text-white hover:bg-purple-700 transition-all shadow-sm cursor-pointer disabled:opacity-50"
            >
              Confirmar Clonación
            </button>
          </div>
        </div>
      </div>
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, computed, reactive, watch } from 'vue'
import { router } from '@inertiajs/vue3'
import axios from 'axios'
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue'
import AdminSubTabs from './Components/AdminSubTabs.vue'

const props = defineProps({
  supervisores: {
    type: Array,
    default: () => [],
  },
  catalog: {
    type: Array,
    default: () => [],
  },
  submoduloActivo: {
    type: String,
    default: 'permisos',
  },
})

const supervisoresList = ref([...props.supervisores])
const supervisorSeleccionado = ref(null)
const busquedaSupervisor = ref('')
const modulosExpandidos = reactive({})
const hayCambiosSinGuardar = ref(false)
const guardando = ref(false)
const mensajeExito = ref(null)
const mensajeError = ref(null)

// Estado local de permisos para el supervisor seleccionado
const localPermisos = reactive({
  modules: {},
  submodules: {},
  can_write: {},
  expires_at: {},
})

// Modal clonar
const mostrarModalClonar = ref(false)
const supervisorOrigenId = ref(null)

const otrosSupervisores = computed(() => {
  if (!supervisorSeleccionado.value) return []
  return supervisoresList.value.filter(s => s.id !== supervisorSeleccionado.value.id)
})

const supervisoresFiltrados = computed(() => {
  const query = busquedaSupervisor.value.toLowerCase().trim()
  if (!query) return supervisoresList.value
  return supervisoresList.value.filter(s =>
    s.username.toLowerCase().includes(query)
    || (s.ruta && s.ruta.toLowerCase().includes(query))
    || (s.role && s.role.toLowerCase().includes(query))
  )
})

function seleccionarSupervisor(sup) {
  if (hayCambiosSinGuardar.value) {
    const conf = confirm('Tienes cambios sin guardar para este supervisor. ¿Deseas descartarlos y cambiar?')
    if (!conf) return
  }

  supervisorSeleccionado.value = sup
  hayCambiosSinGuardar.value = false
  mensajeExito.value = null
  mensajeError.value = null

  // Inicializar estado local de permisos
  cargarEstadoPermisos(sup)
}

function getSubKey(modulo, sub) {
  const modKey = sub.module_key || modulo.id
  return `${modKey}.${sub.id}`
}

function moduloTieneGrupos(modulo) {
  return Array.isArray(modulo.submodules) && modulo.submodules.some(s => !!s.group)
}

function obtenerGruposModulo(modulo) {
  if (!modulo.submodules) return []
  const grupos = []
  const mapa = new Map()

  modulo.submodules.forEach(sub => {
    const nombreGrupo = sub.group || 'General'
    if (!mapa.has(nombreGrupo)) {
      const g = { nombre: nombreGrupo, submodules: [] }
      mapa.set(nombreGrupo, g)
      grupos.push(g)
    }
    mapa.get(nombreGrupo).submodules.push(sub)
  })

  return grupos
}

function contarSubmodulosGrupoActivos(modulo, submodules) {
  return submodules.filter(sub => esSubmoduloHabilitado(modulo, sub)).length
}

function setGrupoSubmodulos(modulo, submodules, estado) {
  submodules.forEach(sub => {
    const subKey = getSubKey(modulo, sub)
    localPermisos.submodules[subKey] = estado
  })
  if (estado) {
    localPermisos.modules[modulo.id] = true
  } else {
    const anyActive = modulo.submodules?.some(s => localPermisos.submodules[getSubKey(modulo, s)])
    if (!anyActive) {
      localPermisos.modules[modulo.id] = false
    }
  }
  hayCambiosSinGuardar.value = true
}

function cargarEstadoPermisos(sup) {
  const map = sup.permissions_map || {}
  const isAll = !!map.is_all_allowed

  // Limpiar
  for (const k in localPermisos.modules) delete localPermisos.modules[k]
  for (const k in localPermisos.submodules) delete localPermisos.submodules[k]
  for (const k in localPermisos.can_write) delete localPermisos.can_write[k]
  for (const k in localPermisos.expires_at) delete localPermisos.expires_at[k]

  props.catalog.forEach(mod => {
    const pKeys = mod.permission_modules || [mod.id]

    if (isAll) {
      localPermisos.modules[mod.id] = true
      pKeys.forEach(k => {
        localPermisos.modules[k] = true
        localPermisos.can_write[k] = true
        localPermisos.expires_at[k] = null
      })
    } else {
      const anyKeyActive = pKeys.some(k => map.modules && map.modules[k] === true)
      localPermisos.modules[mod.id] = anyKeyActive
      pKeys.forEach(k => {
        localPermisos.modules[k] = map.modules ? (map.modules[k] === true) : false
        localPermisos.can_write[k] = map.can_write ? (map.can_write[k] !== false) : true
        localPermisos.expires_at[k] = map.expires_at ? map.expires_at[k] : null
      })
    }

    if (mod.submodules && mod.submodules.length > 0) {
      let anySubActive = false
      mod.submodules.forEach(sub => {
        const subKey = getSubKey(mod, sub)
        if (isAll) {
          localPermisos.submodules[subKey] = true
          localPermisos.can_write[subKey] = true
          localPermisos.expires_at[subKey] = null
          anySubActive = true
        } else {
          const isSubActive = map.submodules ? (map.submodules[subKey] === true) : false
          localPermisos.submodules[subKey] = isSubActive
          localPermisos.can_write[subKey] = map.can_write ? (map.can_write[subKey] !== false) : true
          localPermisos.expires_at[subKey] = map.expires_at ? map.expires_at[subKey] : null
          if (isSubActive) anySubActive = true
        }
      })

      if (anySubActive) {
        localPermisos.modules[mod.id] = true
      }
    }
  })
}

function esModuloHabilitado(modId) {
  return localPermisos.modules[modId] === true
}

function esSubmoduloHabilitado(moduloOrId, subOrId) {
  if (typeof moduloOrId === 'string' && typeof subOrId === 'string') {
    if (localPermisos.submodules[`${moduloOrId}.${subOrId}`] !== undefined) {
      return localPermisos.submodules[`${moduloOrId}.${subOrId}`] === true
    }
    const mod = props.catalog.find(m => m.id === moduloOrId)
    const sub = mod?.submodules?.find(s => s.id === subOrId)
    if (mod && sub) {
      return localPermisos.submodules[getSubKey(mod, sub)] === true
    }
    return localPermisos.submodules[`${moduloOrId}.${subOrId}`] === true
  }
  const modulo = typeof moduloOrId === 'object' ? moduloOrId : props.catalog.find(m => m.id === moduloOrId)
  const sub = typeof subOrId === 'object' ? subOrId : modulo?.submodules?.find(s => s.id === subOrId)
  if (!modulo || !sub) return false
  return localPermisos.submodules[getSubKey(modulo, sub)] === true
}

function contarSubmodulosActivos(modulo) {
  if (!modulo.submodules) return 0
  return modulo.submodules.filter(sub => esSubmoduloHabilitado(modulo, sub)).length
}

function toggleExpandir(modId) {
  modulosExpandidos[modId] = !modulosExpandidos[modId]
}

function toggleModuloPrincipal(modId, checked) {
  localPermisos.modules[modId] = checked
  hayCambiosSinGuardar.value = true

  const mod = props.catalog.find(m => m.id === modId)
  if (!mod || !mod.submodules) return

  if (!checked) {
    mod.submodules.forEach(sub => {
      localPermisos.submodules[getSubKey(mod, sub)] = false
    })
  } else {
    // Si se enciende el módulo principal, activar todos sus submódulos
    mod.submodules.forEach(sub => {
      localPermisos.submodules[getSubKey(mod, sub)] = true
    })
  }
}

function toggleSubmodulo(moduloOrId, subOrId, checked) {
  const modulo = typeof moduloOrId === 'object' ? moduloOrId : props.catalog.find(m => m.id === moduloOrId)
  const sub = typeof subOrId === 'object' ? subOrId : modulo?.submodules?.find(s => s.id === subOrId)
  if (!modulo || !sub) return

  const subKey = getSubKey(modulo, sub)
  localPermisos.submodules[subKey] = checked
  hayCambiosSinGuardar.value = true

  if (checked) {
    localPermisos.modules[modulo.id] = true
  } else {
    const anyActive = modulo.submodules?.some(s => localPermisos.submodules[getSubKey(modulo, s)])
    if (!anyActive) {
      localPermisos.modules[modulo.id] = false
    }
  }
}

function setTodosSubmodulos(modulo, estado) {
  modulo.submodules.forEach(sub => {
    localPermisos.submodules[getSubKey(modulo, sub)] = estado
  })
  localPermisos.modules[modulo.id] = estado
  hayCambiosSinGuardar.value = true
}

async function guardarMatrizCompleta() {
  if (!supervisorSeleccionado.value) return
  guardando.value = true
  mensajeExito.value = null
  mensajeError.value = null

  try {
    const permissionsPayload = []

    props.catalog.forEach(mod => {
      const modAllowed = esModuloHabilitado(mod.id)
      const pKeys = mod.permission_modules || [mod.id]

      if (!modAllowed) {
        // Módulo apagado: apagar raíces de permisos y todos sus submódulos
        pKeys.forEach(k => {
          permissionsPayload.push({
            module_key: k,
            submodule_key: '',
            is_allowed: false,
            can_write: false,
            expires_at: null,
          })
        })

        if (mod.submodules && mod.submodules.length > 0) {
          mod.submodules.forEach(sub => {
            const mKey = sub.module_key || mod.id
            const subKey = getSubKey(mod, sub)
            permissionsPayload.push({
              module_key: mKey,
              submodule_key: sub.id,
              is_allowed: false,
              can_write: localPermisos.can_write[subKey] !== false,
              expires_at: localPermisos.expires_at[subKey] || null,
            })
          })
        }
      } else {
        // Módulo encendido:
        // 1. Guardar estado exacto de cada submódulo
        if (mod.submodules && mod.submodules.length > 0) {
          mod.submodules.forEach(sub => {
            const mKey = sub.module_key || mod.id
            const subKey = getSubKey(mod, sub)
            const isSubAllowed = esSubmoduloHabilitado(mod, sub)
            permissionsPayload.push({
              module_key: mKey,
              submodule_key: sub.id,
              is_allowed: isSubAllowed,
              can_write: localPermisos.can_write[subKey] !== false,
              expires_at: localPermisos.expires_at[subKey] || null,
            })
          })
        }

        // 2. Guardar raíces de módulos de permisos requeridas por middleware
        pKeys.forEach(k => {
          const submodulesInK = mod.submodules ? mod.submodules.filter(s => (s.module_key || mod.id) === k) : []
          const isKAllowed = submodulesInK.length > 0
            ? submodulesInK.some(s => esSubmoduloHabilitado(mod, s))
            : true

          permissionsPayload.push({
            module_key: k,
            submodule_key: '',
            is_allowed: isKAllowed,
            can_write: localPermisos.can_write[k] !== false,
            expires_at: localPermisos.expires_at[k] || null,
          })
        })
      }
    })

    const url = route('supervisor.admin.permisos.batch', { userId: supervisorSeleccionado.value.id })
    const { data } = await axios.post(url, { permissions: permissionsPayload })

    mensajeExito.value = `Se guardaron los permisos para ${supervisorSeleccionado.value.username}.`
    hayCambiosSinGuardar.value = false

    // Actualizar datos del supervisor en la lista
    supervisorSeleccionado.value.permissions_map = data.permissions_map
    supervisorSeleccionado.value.total_custom_rules = permissionsPayload.length
  } catch (err) {
    mensajeError.value = err.response?.data?.message || err.message || 'Error al guardar la matriz de permisos.'
  } finally {
    guardando.value = false
  }
}

async function aplicarPreset(preset) {
  if (!supervisorSeleccionado.value) return
  const nombres = {
    enable_all: 'Habilitar todos los módulos',
    disable_all: 'Deshabilitar todos los módulos',
    reset_default: 'Restablecer a valores globales por defecto',
  }

  const conf = confirm(`¿Estás seguro de aplicar el ajuste "${nombres[preset]}" para ${supervisorSeleccionado.value.username}?`)
  if (!conf) return

  guardando.value = true
  mensajeExito.value = null
  mensajeError.value = null

  try {
    const url = route('supervisor.admin.permisos.preset', { userId: supervisorSeleccionado.value.id })
    const { data } = await axios.post(url, { preset })

    supervisorSeleccionado.value.permissions_map = data.permissions_map
    cargarEstadoPermisos(supervisorSeleccionado.value)
    hayCambiosSinGuardar.value = false
    mensajeExito.value = `Ajuste "${nombres[preset]}" aplicado exitosamente.`
  } catch (err) {
    mensajeError.value = err.response?.data?.message || err.message || 'Error al aplicar acción rápida.'
  } finally {
    guardando.value = false
  }
}

function abrirModalClonar() {
  supervisorOrigenId.value = null
  mostrarModalClonar.value = true
}

async function ejecutarClonacion() {
  if (!supervisorSeleccionado.value || !supervisorOrigenId.value) return
  guardando.value = true

  try {
    const url = route('supervisor.admin.permisos.clone')
    const { data } = await axios.post(url, {
      source_user_id: supervisorOrigenId.value,
      target_user_id: supervisorSeleccionado.value.id,
    })

    supervisorSeleccionado.value.permissions_map = data.permissions_map
    cargarEstadoPermisos(supervisorSeleccionado.value)
    hayCambiosSinGuardar.value = false
    mostrarModalClonar.value = false
    mensajeExito.value = data.message || 'Permisos clonados exitosamente.'
  } catch (err) {
    alert(err.response?.data?.message || err.message || 'Error al clonar permisos.')
  } finally {
    guardando.value = false
  }
}

function recargar() {
  router.reload({ only: ['supervisores'] })
}

// Auto-seleccionar primer supervisor si existe
if (supervisoresList.value.length > 0) {
  seleccionarSupervisor(supervisoresList.value[0])
}
</script>

