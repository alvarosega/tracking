<template>
  <SupervisorLayout>
    <div class="space-y-4 sm:space-y-6">
      <!-- Navegación Unificada: Jornadas / Horarios / Días Visita / Toma de Stock / Pedidos Rechazados / Módulos -->
      <div class="flex items-center justify-between">
        <SubNavTabs :tabs="jornadasHorariosModulosTabs" currentTab="rechazos-ventana" />
      </div>

      <!-- Header Superior Apple Pro -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <div class="flex items-center gap-2">
            <h1 class="text-xl font-semibold tracking-tight text-[#1D1D1F]">Ventana Operativa de Pedidos Rechazados</h1>
            <span
              v-if="isSuperAdmin"
              class="px-2 py-0.5 text-[10px] font-mono font-bold bg-[#EBF9EF] text-[#248A3D] rounded border border-[#248A3D]/20"
            >
              Superadmin Activo
            </span>
            <span
              v-else
              class="px-2 py-0.5 text-[10px] font-mono font-medium bg-[#F2F2F7] text-[#6E6E73] rounded border border-[#E5E5EA]"
            >
              Modo Supervisión
            </span>
          </div>
          <p class="text-xs text-[#6E6E73] mt-0.5">
            Configura qué fechas de preventa se evalúan contra la facturación de ventas (ERP) para enviar los pedidos rechazados a la app móvil de los vendedores.
          </p>
        </div>

        <!-- Acciones Superiores -->
        <div class="flex items-center gap-2 shrink-0">
          <button
            type="button"
            @click="abrirPreview(null)"
            class="h-8 px-3.5 bg-[#0071E3] hover:bg-[#0077ED] text-white text-xs font-semibold rounded-[6px] inline-flex items-center justify-center transition-colors shadow-xs cursor-pointer select-none"
          >
            <svg class="w-3.5 h-3.5 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
              <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
            </svg>
            Previsualizar Pedidos App
          </button>

          <button
            type="button"
            @click="fetchData"
            :disabled="loading"
            class="h-8 px-3 bg-white border border-[#E5E5EA] hover:bg-[#F2F2F7] text-xs font-medium rounded-[6px] text-[#1D1D1F] inline-flex items-center justify-center transition-colors cursor-pointer select-none"
          >
            <svg class="w-3.5 h-3.5 mr-1.5 text-[#6E6E73]" :class="{ 'animate-spin': loading }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
            </svg>
            Recargar
          </button>
        </div>
      </div>

      <!-- CARD 1: CONTROL DE EXCEPCIONES Y FERIADOS (APPLE HERO) -->
      <div class="bg-white border border-[#E5E5EA] rounded-[12px] p-4 sm:p-5 shadow-2xs space-y-4">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
          <div class="space-y-1">
            <div class="flex items-center gap-2">
              <div
                class="w-7 h-7 rounded-[8px] flex items-center justify-center"
                :class="evalInfo.es_excepcion ? 'bg-[#FF9500]/10 text-[#FF9500]' : 'bg-[#0071E3]/10 text-[#0071E3]'"
              >
                <svg v-if="evalInfo.es_excepcion" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <svg v-else class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
              </div>

              <h2 class="text-sm font-bold text-[#1D1D1F]">
                {{ evalInfo.es_excepcion ? 'Excepción Activa (Feriado o Fecha Manual)' : 'Modo Automático Semanal' }}
              </h2>

              <span
                class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold"
                :class="evalInfo.es_excepcion
                  ? 'bg-[#FFF4E5] text-[#B25E00] border border-[#FF9500]/30'
                  : 'bg-[#EFF6FF] text-[#0071E3] border border-[#0071E3]/20'"
              >
                {{ evalInfo.modo_aplicado }}
              </span>
            </div>

            <p class="text-xs text-[#6E6E73]">
              {{ evalInfo.descripcion }}
            </p>
          </div>

          <!-- Fechas Activas Badge -->
          <div class="bg-[#F9F9FB] border border-[#E5E5EA] rounded-[8px] px-3.5 py-2 flex items-center gap-3 shrink-0">
            <div>
              <span class="text-[10px] font-medium text-[#86868B] block uppercase tracking-wider">Fechas de preventa evaluadas:</span>
              <span class="text-xs font-mono font-bold text-[#1D1D1F]">
                {{ evalInfo.fechas.join(', ') }}
              </span>
            </div>
            <span class="w-2.5 h-2.5 rounded-full" :class="evalInfo.es_excepcion ? 'bg-[#FF9500] animate-pulse' : 'bg-[#34C759]'"></span>
          </div>
        </div>

        <!-- Barra de Configuración de Excepción (Superadmin o Ajustes Globales) -->
        <div v-if="canEditGlobal" class="pt-3 border-t border-[#E5E5EA] flex flex-wrap items-center justify-between gap-3">
          <div class="flex items-center gap-2 flex-wrap">
            <span class="text-xs text-[#86868B] font-medium">Fijar Fecha de Preventa (Feriados / Excepción):</span>
            <input
              type="date"
              v-model="inputFechaExcepcion"
              class="h-8 px-2.5 text-xs font-mono text-[#1D1D1F] bg-white border border-[#E5E5EA] rounded-[6px] focus:outline-none focus:border-[#0071E3]"
            />
            <span class="text-xs text-[#86868B]">hasta</span>
            <input
              type="date"
              v-model="inputFechaExcepcionHasta"
              class="h-8 px-2.5 text-xs font-mono text-[#1D1D1F] bg-white border border-[#E5E5EA] rounded-[6px] focus:outline-none focus:border-[#0071E3]"
              placeholder="Opcional"
            />

            <button
              type="button"
              @click="guardarExcepcionFecha"
              :disabled="guardando || !inputFechaExcepcion"
              class="h-8 px-3 text-xs font-semibold text-white bg-[#0071E3] hover:bg-[#0077ED] disabled:opacity-40 rounded-[6px] shadow-xs inline-flex items-center gap-1.5 transition-colors cursor-pointer"
            >
              <span v-if="guardando" class="w-3 h-3 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
              <span>Activar Excepción</span>
            </button>

            <button
              v-if="evalInfo.es_excepcion"
              type="button"
              @click="limpiarExcepcion"
              :disabled="guardando"
              class="h-8 px-3 text-xs font-semibold text-[#1D1D1F] bg-white border border-[#E5E5EA] hover:bg-[#F2F2F7] rounded-[6px] transition-colors cursor-pointer"
            >
              Volver a Automático Semanal
            </button>
          </div>

          <!-- Toggle Mostrar Editor Matriz Semanal -->
          <div>
            <button
              type="button"
              @click="mostrarEditorMatriz = !mostrarEditorMatriz"
              class="h-8 px-3 text-xs font-medium text-[#0071E3] hover:bg-[#EFF6FF] rounded-[6px] transition-colors inline-flex items-center gap-1 cursor-pointer"
            >
              <span>{{ mostrarEditorMatriz ? 'Ocultar Matriz Semanal' : 'Personalizar Matriz Semanal' }}</span>
              <svg class="w-3.5 h-3.5 transition-transform" :class="{ 'rotate-180': mostrarEditorMatriz }" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
              </svg>
            </button>
          </div>
        </div>

        <!-- COLLAPSIBLE: EDITOR DE MATRIZ SEMANAL (LUNES A SÁBADO) -->
        <div v-if="mostrarEditorMatriz" class="mt-4 pt-4 border-t border-[#E5E5EA] space-y-3">
          <div class="flex items-center justify-between">
            <div>
              <h3 class="text-xs font-bold text-[#1D1D1F] uppercase tracking-wider">Matriz Semanal de Preventas por Día de Entrega</h3>
              <p class="text-[11px] text-[#6E6E73]">Marca qué días de preventa corresponden a cada día de trabajo del vendedor cuando no hay feriados.</p>
            </div>
            <button
              type="button"
              @click="guardarMatrizSemanal"
              :disabled="guardando"
              class="h-7 px-3 text-xs font-semibold text-white bg-[#34C759] hover:bg-[#2EB84E] rounded-[6px] transition-colors cursor-pointer"
            >
              Guardar Matriz Semanal
            </button>
          </div>

          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
            <div
              v-for="(diaData, diaKey) in diasSemanaCatalogo"
              :key="diaKey"
              class="p-3 rounded-[8px] border bg-[#FBFBFD] space-y-2"
              :class="evalInfo.dia_actual.toLowerCase() === diaData.nombre.toLowerCase() ? 'border-[#0071E3] ring-1 ring-[#0071E3]/20' : 'border-[#E5E5EA]'"
            >
              <div class="flex items-center justify-between">
                <span class="text-xs font-bold text-[#1D1D1F]">{{ diaData.nombre }}</span>
                <span
                  v-if="evalInfo.dia_actual.toLowerCase() === diaData.nombre.toLowerCase()"
                  class="px-1.5 py-0.2 rounded text-[9px] font-mono font-bold bg-[#EFF6FF] text-[#0071E3]"
                >
                  Hoy
                </span>
              </div>

              <div class="flex flex-wrap gap-1.5">
                <label
                  v-for="opcion in ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado']"
                  :key="opcion"
                  class="inline-flex items-center gap-1 px-2 py-1 rounded-[6px] text-[11px] font-mono cursor-pointer transition-colors border select-none"
                  :class="isDiaSeleccionado(diaKey, opcion)
                    ? 'bg-[#0071E3] text-white border-[#0071E3] font-bold shadow-2xs'
                    : 'bg-white text-[#6E6E73] border-[#E5E5EA] hover:border-[#86868B]'"
                >
                  <input
                    type="checkbox"
                    class="hidden"
                    :checked="isDiaSeleccionado(diaKey, opcion)"
                    @change="toggleDiaMatriz(diaKey, opcion)"
                  />
                  <span>{{ opcion.substring(0, 3) }}</span>
                </label>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- KPI METRICS (APPLE PRO) -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-white border border-[#E5E5EA] rounded-[10px] p-3 shadow-2xs">
          <span class="text-[10px] font-mono font-semibold uppercase text-[#86868B]">Total Rutas</span>
          <div class="text-xl font-bold font-mono text-[#1D1D1F] mt-0.5">{{ kpis.total_rutas || rutas.length }}</div>
          <span class="text-[10px] text-[#6E6E73] mt-0.5 block">Comerciales monitoreadas</span>
        </div>

        <div class="bg-white border border-[#E5E5EA] rounded-[10px] p-3 shadow-2xs">
          <span class="text-[10px] font-mono font-semibold uppercase text-[#86868B]">Preventas Evaluadas</span>
          <div class="text-xl font-bold font-mono text-[#0071E3] mt-0.5">{{ kpis.total_preventas_evaluadas || 0 }}</div>
          <span class="text-[10px] text-[#6E6E73] mt-0.5 block">En las fechas del corte actual</span>
        </div>

        <div class="bg-white border border-[#E5E5EA] rounded-[10px] p-3 shadow-2xs">
          <span class="text-[10px] font-mono font-semibold uppercase text-[#86868B]">Rechazos Pendientes</span>
          <div class="text-xl font-bold font-mono text-[#FF3B30] mt-0.5">{{ kpis.total_preventas_rechazadas || 0 }}</div>
          <span class="text-[10px] text-[#6E6E73] mt-0.5 block">Preventas que ve Android hoy</span>
        </div>

        <div class="bg-white border border-[#E5E5EA] rounded-[10px] p-3 shadow-2xs">
          <span class="text-[10px] font-mono font-semibold uppercase text-[#86868B]">Estado de Corte</span>
          <div class="text-xs font-bold font-mono text-[#1D1D1F] mt-1 truncate" :title="evalInfo.descripcion">
            {{ evalInfo.es_excepcion ? 'Excepción Feriado' : 'Semanal Normal' }}
          </div>
          <span class="text-[10px] text-[#86868B] mt-0.5 block">{{ evalInfo.fechas.join(', ') }}</span>
        </div>
      </div>

      <!-- TABLA MATRIZ DE RUTAS -->
      <div class="space-y-3">
        <!-- Barra de Búsqueda y Filtros -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
          <div class="flex items-center gap-2 flex-1 flex-wrap">
            <div class="relative flex-1 min-w-[200px] max-w-sm">
              <svg class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-[#86868B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
              <input
                v-model="busqueda"
                type="text"
                placeholder="Buscar por ruta o vendedor..."
                class="w-full h-8 pl-8 pr-3 text-xs bg-white border border-[#E5E5EA] rounded-[6px] focus:outline-none focus:border-[#0071E3] font-mono"
              />
            </div>

            <!-- Filtro de Canal -->
            <select
              v-model="filtroCanal"
              class="h-8 px-2.5 text-xs bg-white border border-[#E5E5EA] rounded-[6px] text-[#1D1D1F] focus:outline-none focus:border-[#0071E3]"
            >
              <option value="">Todos los Canales</option>
              <option v-for="canal in canales" :key="canal" :value="canal">{{ canal }}</option>
            </select>

            <!-- Filtro Solo con Rechazos -->
            <label class="inline-flex items-center gap-1.5 text-xs text-[#1D1D1F] cursor-pointer select-none">
              <input type="checkbox" v-model="filtroSoloRechazos" class="rounded border-[#E5E5EA] text-[#0071E3]" />
              <span>Solo con rechazos (>0)</span>
            </label>
          </div>

          <div class="text-xs text-[#86868B] font-mono">
            Mostrando <strong>{{ rutasFiltradas.length }}</strong> de {{ rutas.length }} rutas
          </div>
        </div>

        <!-- Tabla -->
        <div class="bg-white border border-[#E5E5EA] rounded-[10px] overflow-hidden shadow-2xs">
          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
              <thead>
                <tr class="bg-[#FBFBFD] border-b border-[#E5E5EA] text-[10px] font-mono uppercase text-[#86868B] tracking-wider">
                  <th class="py-2 px-3.5">Ruta Comercial</th>
                  <th class="py-2 px-3 text-center">Canal</th>
                  <th class="py-2 px-3 text-center">Clientes</th>
                  <th class="py-2 px-3 text-center">Preventas Corte</th>
                  <th class="py-2 px-3 text-center">Rechazos Pendientes</th>
                  <th class="py-2 px-3 text-center">Monto Rechazado</th>
                  <th class="py-2 px-3 text-center">Estado Módulo</th>
                  <th class="py-2 px-3 text-right">Acciones</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[#E5E5EA] text-xs">
                <tr v-if="rutasFiltradas.length === 0">
                  <td colspan="8" class="py-8 text-center text-xs text-[#86868B]">
                    No se encontraron rutas con los filtros seleccionados.
                  </td>
                </tr>

                <tr
                  v-for="r in rutasFiltradas"
                  :key="r.ruta"
                  class="hover:bg-[#FBFBFD] transition-colors"
                >
                  <!-- Ruta y Vendedor -->
                  <td class="py-2.5 px-3.5 align-middle">
                    <div class="font-bold text-[#1D1D1F] font-mono">{{ r.ruta }}</div>
                    <div class="text-[11px] text-[#6E6E73] truncate max-w-[180px]">{{ r.vendedor }}</div>
                  </td>

                  <!-- Canal -->
                  <td class="py-2.5 px-3 align-middle text-center">
                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-[#F2F2F7] text-[#1D1D1F]">
                      {{ r.canal }}
                    </span>
                  </td>

                  <!-- Clientes -->
                  <td class="py-2.5 px-3 align-middle text-center font-mono text-[#86868B]">
                    {{ r.total_clientes }}
                  </td>

                  <!-- Preventas Corte -->
                  <td class="py-2.5 px-3 align-middle text-center font-mono font-semibold text-[#1D1D1F]">
                    {{ r.total_preventas }}
                  </td>

                  <!-- Rechazos Pendientes -->
                  <td class="py-2.5 px-3 align-middle text-center font-mono">
                    <span
                      class="px-2 py-0.5 rounded-full text-xs font-bold"
                      :class="r.total_rechazos > 0 ? 'bg-[#FFECEB] text-[#FF3B30]' : 'text-[#86868B]'"
                    >
                      {{ r.total_rechazos }}
                    </span>
                  </td>

                  <!-- Monto Rechazado -->
                  <td class="py-2.5 px-3 align-middle text-center font-mono font-medium">
                    <span :class="r.monto_rechazos > 0 ? 'text-[#FF3B30] font-bold' : 'text-[#86868B]'">
                      Bs. {{ Number(r.monto_rechazos).toFixed(2) }}
                    </span>
                  </td>

                  <!-- Toggle Módulo -->
                  <td class="py-2.5 px-3 align-middle text-center">
                    <button
                      v-if="canEditMatriz"
                      type="button"
                      @click="toggleRuta(r)"
                      class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                      :class="r.modulo_activo ? 'bg-[#34C759]' : 'bg-[#E5E5EA]'"
                    >
                      <span
                        class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow-xs ring-0 transition duration-200 ease-in-out"
                        :class="r.modulo_activo ? 'translate-x-4' : 'translate-x-0'"
                      />
                    </button>
                    <span
                      v-else
                      class="px-2 py-0.5 text-[10px] rounded font-mono font-bold"
                      :class="r.modulo_activo ? 'bg-[#EBF9EF] text-[#248A3D]' : 'bg-[#F2F2F7] text-[#6E6E73]'"
                    >
                      {{ r.modulo_activo ? 'Activo' : 'Inactivo' }}
                    </span>
                  </td>

                  <!-- Acciones -->
                  <td class="py-2.5 px-3 align-middle text-right">
                    <div class="flex items-center justify-end gap-1.5">
                      <button
                        type="button"
                        @click="abrirPreview(r.ruta)"
                        class="h-7 px-2.5 bg-white border border-[#E5E5EA] hover:bg-[#F2F2F7] text-[#0071E3] text-xs font-semibold rounded-[6px] inline-flex items-center gap-1 transition-colors cursor-pointer"
                        title="Ver preventas rechazadas que ve Android"
                      >
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                        <span>Ver</span>
                      </button>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- MODAL DRAWER DE PREVISUALIZACIÓN EN VIVO (PEDIDOS QUE VE ANDROID) -->
      <div
        v-if="modalPreview.abierto"
        class="fixed inset-0 z-50 overflow-hidden bg-black/40 backdrop-blur-xs flex items-center justify-center p-4 sm:p-6"
      >
        <div class="bg-white rounded-[14px] shadow-2xl border border-[#E5E5EA] w-full max-w-4xl max-h-[90vh] overflow-hidden flex flex-col">
          <!-- Header Modal -->
          <div class="px-5 py-3.5 border-b border-[#E5E5EA] flex items-center justify-between bg-[#FBFBFD]">
            <div>
              <div class="flex items-center gap-2">
                <h3 class="text-base font-bold text-[#1D1D1F]">Previsualización de Pedidos Rechazados (Android)</h3>
                <span v-if="modalPreview.ruta" class="px-2 py-0.5 rounded text-xs font-mono font-bold bg-[#EFF6FF] text-[#0071E3]">
                  Ruta {{ modalPreview.ruta }}
                </span>
                <span v-else class="px-2 py-0.5 rounded text-xs font-mono font-bold bg-[#F2F2F7] text-[#1D1D1F]">
                  Todas las Rutas
                </span>
              </div>
              <p class="text-[11px] text-[#6E6E73] mt-0.5">
                {{ modalPreview.evalInfo?.descripcion || evalInfo.descripcion }}
              </p>
            </div>

            <button
              type="button"
              @click="modalPreview.abierto = false"
              class="w-7 h-7 rounded-full bg-[#F2F2F7] hover:bg-[#E5E5EA] flex items-center justify-center text-[#6E6E73] cursor-pointer"
            >
              ✕
            </button>
          </div>

          <!-- Body Modal con Tabla de Preventas Rechazadas -->
          <div class="p-5 overflow-y-auto flex-1 space-y-3">
            <div v-if="modalPreview.cargando" class="py-12 text-center text-xs text-[#86868B] space-y-2">
              <div class="w-6 h-6 border-2 border-[#0071E3] border-t-transparent rounded-full animate-spin mx-auto"></div>
              <div>Consultando cruce en vivo contra fact_ventas y ERP...</div>
            </div>

            <div v-else-if="modalPreview.pedidos.length === 0" class="py-12 text-center text-xs text-[#86868B] space-y-2">
              <svg class="w-8 h-8 text-[#34C759] mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
              <div class="font-bold text-[#1D1D1F]">¡No hay pedidos rechazados pendientes!</div>
              <div>Todas las preventas de estas fechas fueron completamente facturadas o ya cuentan con justificación.</div>
            </div>

            <div v-else class="space-y-3">
              <div class="flex items-center justify-between text-xs text-[#6E6E73]">
                <span>Total preventas con rechazo: <strong class="text-[#FF3B30] font-mono">{{ modalPreview.pedidos.length }}</strong></span>
              </div>

              <div
                v-for="p in modalPreview.pedidos"
                :key="p.nro_preventa"
                class="border border-[#E5E5EA] rounded-[10px] p-3.5 bg-white space-y-2.5"
              >
                <div class="flex items-center justify-between gap-2 flex-wrap">
                  <div class="flex items-center gap-2">
                    <span class="px-2 py-0.5 rounded text-[11px] font-mono font-bold bg-[#F2F2F7] text-[#1D1D1F]">
                      Prev #{{ p.nro_preventa }}
                    </span>
                    <span class="text-xs font-bold text-[#1D1D1F]">{{ p.cliente_nombre }}</span>
                    <span class="text-[10px] font-mono text-[#86868B]">({{ p.codigo_cliente }})</span>
                  </div>

                  <div class="flex items-center gap-2">
                    <span
                      class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold"
                      :class="p.tipo_sugerido === 'PARCIAL' ? 'bg-[#FFF4E5] text-[#B25E00]' : 'bg-[#FFECEB] text-[#FF3B30]'"
                    >
                      Rechazo {{ p.tipo_sugerido }}
                    </span>
                    <span class="text-xs font-mono font-bold text-[#FF3B30]">
                      Bs. {{ Number(p.monto_total_rechazado).toFixed(2) }}
                    </span>
                  </div>
                </div>

                <!-- Detalle de Ítems Rechazados -->
                <div class="bg-[#F9F9FB] rounded-[8px] p-2.5 text-xs space-y-1.5 border border-[#E5E5EA]">
                  <div class="text-[10px] font-mono font-semibold uppercase text-[#86868B]">Ítems Rechazados en la Entrega:</div>
                  <div
                    v-for="item in (p.items_rechazados || p.items)"
                    :key="item.preventa_item_id || item.producto_id"
                    class="flex items-center justify-between text-xs py-0.5"
                  >
                    <div class="truncate max-w-sm">
                      <span class="font-medium text-[#1D1D1F]">{{ item.producto_nombre }}</span>
                      <span class="text-[10px] text-[#86868B] font-mono ml-1.5">({{ item.codigo_producto }})</span>
                    </div>
                    <div class="font-mono text-right shrink-0">
                      <span class="text-[#86868B]">Prev: {{ item.cantidad_preventa }}</span>
                      <span class="text-[#34C759] ml-2">Fact: {{ item.cantidad_facturada }}</span>
                      <span class="text-[#FF3B30] font-bold ml-2">Rech: {{ item.cantidad_rechazada }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Footer Modal -->
          <div class="px-5 py-3 border-t border-[#E5E5EA] bg-[#FBFBFD] flex items-center justify-between">
            <span class="text-xs text-[#86868B]">
              Los vendedores ven estos mismos registros en su módulo de Pedidos Rechazados.
            </span>
            <button
              type="button"
              @click="modalPreview.abierto = false"
              class="h-8 px-4 text-xs font-medium text-[#1D1D1F] bg-white border border-[#E5E5EA] hover:bg-[#F2F2F7] rounded-[6px] transition-colors cursor-pointer"
            >
              Cerrar
            </button>
          </div>
        </div>
      </div>
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue';
import SubNavTabs from '@/Components/UI/SubNavTabs.vue';
import { jornadasHorariosModulosTabs } from '@/Config/navigation';

const props = defineProps({
  initialData: {
    type: Object,
    required: true,
  },
});

const page = usePage();
const user = computed(() => page.props.auth?.user);
const isSuperAdmin = computed(() => !!user.value?.is_superadmin);
const canEditGlobal = computed(() => isSuperAdmin.value || user.value?.permissions?.modules?.modulos === true);
const canEditMatriz = computed(() => isSuperAdmin.value || user.value?.permissions?.modules?.modulos === true);

// Datos reactivos del servidor
const configGlobal = ref(props.initialData.config_global || {});
const evalInfo = ref(props.initialData.eval_info || { fechas: [] });
const diasSemanaCatalogo = ref(props.initialData.dias_semana_catalogo || {});
const rutas = ref(props.initialData.rutas || []);
const canales = ref(props.initialData.canales || []);
const kpis = ref(props.initialData.kpis || {});

// Estados locales de la vista
const loading = ref(false);
const guardando = ref(false);
const mostrarEditorMatriz = ref(false);

const inputFechaExcepcion = ref(evalInfo.value?.es_excepcion ? evalInfo.value.fecha_min : '');
const inputFechaExcepcionHasta = ref(evalInfo.value?.es_excepcion && evalInfo.value.fecha_max !== evalInfo.value.fecha_min ? evalInfo.value.fecha_max : '');

// Filtros de la tabla
const busqueda = ref('');
const filtroCanal = ref('');
const filtroSoloRechazos = ref(false);

// Modal de previsualización
const modalPreview = ref({
  abierto: false,
  cargando: false,
  ruta: null,
  evalInfo: null,
  pedidos: [],
});

// Rutas filtradas
const rutasFiltradas = computed(() => {
  return rutas.value.filter((r) => {
    if (filtroCanal.value && r.canal !== filtroCanal.value) return false;
    if (filtroSoloRechazos.value && r.total_rechazos <= 0) return false;

    if (busqueda.value.trim() !== '') {
      const q = busqueda.value.toLowerCase().trim();
      const matchRuta = r.ruta.toLowerCase().includes(q);
      const matchVendedor = (r.vendedor || '').toLowerCase().includes(q);
      if (!matchRuta && !matchVendedor) return false;
    }

    return true;
  });
});

// Helper de matriz semanal
function isDiaSeleccionado(diaKey, opcion) {
  const matriz = configGlobal.value?.matriz_semanal || {};
  const lista = matriz[diaKey] || [];
  return lista.includes(opcion);
}

function toggleDiaMatriz(diaKey, opcion) {
  if (!configGlobal.value.matriz_semanal) {
    configGlobal.value.matriz_semanal = {};
  }
  if (!configGlobal.value.matriz_semanal[diaKey]) {
    configGlobal.value.matriz_semanal[diaKey] = [];
  }

  const idx = configGlobal.value.matriz_semanal[diaKey].indexOf(opcion);
  if (idx > -1) {
    configGlobal.value.matriz_semanal[diaKey].splice(idx, 1);
  } else {
    configGlobal.value.matriz_semanal[diaKey].push(opcion);
  }
}

// Acciones API
async function fetchData() {
  loading.value = true;
  try {
    const res = await axios.post('/supervisor/rechazos-ventana/data');
    configGlobal.value = res.data.config_global;
    evalInfo.value = res.data.eval_info;
    diasSemanaCatalogo.value = res.data.dias_semana_catalogo;
    rutas.value = res.data.rutas;
    canales.value = res.data.canales;
    kpis.value = res.data.kpis;
  } catch (e) {
    console.error('Error al recargar datos:', e);
  } finally {
    loading.value = false;
  }
}

async function guardarExcepcionFecha() {
  if (!inputFechaExcepcion.value) return;
  guardando.value = true;
  try {
    await axios.post('/supervisor/rechazos-ventana/global', {
      modo: 'fecha_fija',
      fecha_especifica_activa: inputFechaExcepcion.value,
      fecha_especifica_hasta: inputFechaExcepcionHasta.value || null,
    });
    await fetchData();
  } catch (e) {
    alert(e.response?.data?.message || 'Error guardando excepción');
  } finally {
    guardando.value = false;
  }
}

async function limpiarExcepcion() {
  guardando.value = true;
  try {
    await axios.post('/supervisor/rechazos-ventana/global', {
      modo: 'semanal',
      fecha_especifica_activa: null,
      fecha_especifica_hasta: null,
    });
    inputFechaExcepcion.value = '';
    inputFechaExcepcionHasta.value = '';
    await fetchData();
  } catch (e) {
    alert(e.response?.data?.message || 'Error restableciendo a modo semanal');
  } finally {
    guardando.value = false;
  }
}

async function guardarMatrizSemanal() {
  guardando.value = true;
  try {
    await axios.post('/supervisor/rechazos-ventana/global', {
      matriz_semanal: configGlobal.value.matriz_semanal,
    });
    await fetchData();
    alert('Matriz semanal guardada correctamente.');
  } catch (e) {
    alert(e.response?.data?.message || 'Error guardando matriz');
  } finally {
    guardando.value = false;
  }
}

async function toggleRuta(r) {
  try {
    const nuevoEstado = !r.modulo_activo;
    await axios.post('/supervisor/rechazos-ventana/route/toggle', {
      ruta: r.ruta,
      is_active: nuevoEstado,
    });
    r.modulo_activo = nuevoEstado;
  } catch (e) {
    alert(e.response?.data?.message || 'Error al cambiar estado de la ruta');
  }
}

async function abrirPreview(ruta = null) {
  modalPreview.value.abierto = true;
  modalPreview.value.cargando = true;
  modalPreview.value.ruta = ruta;
  modalPreview.value.pedidos = [];

  try {
    const res = await axios.post('/supervisor/rechazos-ventana/preview', { ruta });
    modalPreview.value.evalInfo = res.data.eval_info;
    modalPreview.value.pedidos = res.data.pedidos || [];
  } catch (e) {
    alert(e.response?.data?.message || 'Error cargando previsualización');
  } finally {
    modalPreview.value.cargando = false;
  }
}
</script>

