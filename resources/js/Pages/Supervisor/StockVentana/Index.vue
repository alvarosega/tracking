<template>
  <SupervisorLayout>
    <div class="space-y-4 sm:space-y-6">
      <!-- Navegación Unificada: Jornadas / Horarios / Días Visita / Toma de Stock / Módulos -->
      <div class="flex items-center justify-between">
        <SubNavTabs :tabs="jornadasHorariosModulosTabs" currentTab="stock-ventana" />
      </div>

      <!-- Header Superior Apple Pro -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <div class="flex items-center gap-2">
            <h1 class="text-xl font-semibold tracking-tight text-[#1D1D1F]">Ventana Histórica para Toma de Stock</h1>
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
            Define la ventana de compras en el ERP (días hacia atrás) que la app móvil consulta al tomar inventario físico en punto de venta.
          </p>
        </div>

        <!-- Botón Recargar -->
        <div class="flex items-center gap-2 shrink-0">
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

      <!-- CARD HERO: CONFIGURACIÓN GLOBAL DE LA VENTANA (APPLE PRO) -->
      <div class="bg-white border border-[#E5E5EA] rounded-[12px] p-4 sm:p-5 shadow-2xs space-y-4">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
          <div class="space-y-1">
            <div class="flex items-center gap-2">
              <div class="w-7 h-7 rounded-[8px] bg-[#0071E3]/10 text-[#0071E3] flex items-center justify-center">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
              </div>
              <h2 class="text-sm font-bold text-[#1D1D1F]">Ventana Histórica Global</h2>
              <span class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold bg-[#EFF6FF] text-[#0071E3] border border-[#0071E3]/20">
                {{ globalDias }} días por defecto
              </span>
            </div>
            <p class="text-xs text-[#6E6E73]">
              Aplica automáticamente a cualquier ruta que no tenga una ventana personalizada asignada.
            </p>
          </div>

          <!-- Impacto en Fechas -->
          <div class="bg-[#F9F9FB] border border-[#E5E5EA] rounded-[8px] px-3.5 py-2 flex items-center gap-3 shrink-0">
            <div>
              <span class="text-[10px] font-medium text-[#86868B] block uppercase tracking-wider">Período de compras API:</span>
              <span class="text-xs font-mono font-bold text-[#1D1D1F]">
                Desde {{ fechaGlobalDesde }} hasta hoy
              </span>
            </div>
            <span class="w-2 h-2 rounded-full bg-[#34C759]"></span>
          </div>
        </div>

        <!-- Controles de Edición Global -->
        <div v-if="isSuperAdmin" class="pt-3 border-t border-[#E5E5EA] flex flex-wrap items-center justify-between gap-3">
          <!-- Presets Rápidos -->
          <div class="flex items-center gap-1.5 flex-wrap">
            <span class="text-xs text-[#86868B] font-medium mr-1">Presets:</span>
            <button
              v-for="preset in [15, 30, 45, 60, 90, 120, 180]"
              :key="preset"
              type="button"
              @click="nuevoGlobalDias = preset"
              class="px-2.5 py-1 text-xs font-mono rounded-[6px] border transition-colors cursor-pointer"
              :class="nuevoGlobalDias === preset
                ? 'bg-[#0071E3] text-white border-[#0071E3] font-bold shadow-xs'
                : 'bg-white text-[#1D1D1F] border-[#E5E5EA] hover:bg-[#F2F2F7]'"
            >
              {{ preset }}d
            </button>
          </div>

          <!-- Input y Botón Guardar -->
          <div class="flex items-center gap-2">
            <div class="relative w-28">
              <input
                v-model.number="nuevoGlobalDias"
                type="number"
                min="1"
                max="365"
                class="w-full h-8 pl-3 pr-7 text-xs font-mono text-[#1D1D1F] bg-white border border-[#E5E5EA] rounded-[6px] focus:outline-none focus:border-[#0071E3]"
              />
              <span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[11px] text-[#86868B] font-mono pointer-events-none">días</span>
            </div>

            <button
              type="button"
              @click="guardarGlobal"
              :disabled="guardandoGlobal || nuevoGlobalDias === globalDias"
              class="h-8 px-4 text-xs font-semibold text-white bg-[#0071E3] hover:bg-[#0077ED] disabled:opacity-40 disabled:cursor-not-allowed rounded-[6px] shadow-xs flex items-center gap-1.5 transition-colors cursor-pointer"
            >
              <span v-if="guardandoGlobal" class="w-3 h-3 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
              <span>Guardar Global</span>
            </button>
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
          <span class="text-[10px] font-mono font-semibold uppercase text-[#86868B]">Ventana Global</span>
          <div class="text-xl font-bold font-mono text-[#34C759] mt-0.5">{{ kpis.rutas_globales || 0 }}</div>
          <span class="text-[10px] text-[#6E6E73] mt-0.5 block">Rutas heredando {{ globalDias }} días</span>
        </div>

        <div class="bg-white border border-[#E5E5EA] rounded-[10px] p-3 shadow-2xs">
          <span class="text-[10px] font-mono font-semibold uppercase text-[#86868B]">Personalizadas</span>
          <div class="text-xl font-bold font-mono text-[#0071E3] mt-0.5">{{ kpis.rutas_personalizadas || 0 }}</div>
          <span class="text-[10px] text-[#6E6E73] mt-0.5 block">Rutas con ventana específica</span>
        </div>

        <div class="bg-white border border-[#E5E5EA] rounded-[10px] p-3 shadow-2xs">
          <span class="text-[10px] font-mono font-semibold uppercase text-[#86868B]">Corte Global</span>
          <div class="text-xs font-bold font-mono text-[#1D1D1F] mt-1 truncate" :title="fechaGlobalDesde">{{ fechaGlobalDesde }}</div>
          <span class="text-[10px] text-[#86868B] mt-0.5 block">Compras descargadas en app</span>
        </div>
      </div>

      <!-- BARRA DE FILTROS Y BÚSQUEDA -->
      <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3">
        <div class="flex items-center gap-2 flex-1 flex-wrap">
          <!-- Búsqueda -->
          <div class="relative flex-1 min-w-[200px] max-w-sm">
            <svg class="w-4 h-4 absolute left-2.5 top-1/2 -translate-y-1/2 text-[#86868B]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
              <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
            </svg>
            <input
              v-model="filtroBusqueda"
              type="text"
              placeholder="Buscar ruta o vendedor..."
              class="w-full h-8 pl-8 pr-3 text-xs bg-white border border-[#E5E5EA] rounded-[6px] text-[#1D1D1F] placeholder-[#86868B] focus:outline-none focus:border-[#0071E3]"
            />
          </div>

          <!-- Filtro Canal -->
          <select
            v-model="filtroCanal"
            class="h-8 px-2.5 bg-white border border-[#E5E5EA] rounded-[6px] text-xs text-[#1D1D1F] focus:outline-none focus:border-[#0071E3] shrink-0"
          >
            <option value="TODOS">Todos los Canales</option>
            <option v-for="c in canales" :key="c" :value="c">{{ c }}</option>
          </select>

          <!-- Filtro Estado de Ventana -->
          <select
            v-model="filtroTipo"
            class="h-8 px-2.5 bg-white border border-[#E5E5EA] rounded-[6px] text-xs text-[#1D1D1F] focus:outline-none focus:border-[#0071E3] shrink-0"
          >
            <option value="TODAS">Todos los Tipos</option>
            <option value="PERSONALIZADAS">Solo Personalizadas</option>
            <option value="GLOBALES">Solo Globales</option>
          </select>
        </div>

        <!-- Acción Masiva por Canal -->
        <div v-if="isSuperAdmin" class="flex items-center gap-2 shrink-0">
          <button
            type="button"
            @click="abrirModalMasivo"
            class="h-8 px-3 text-xs font-semibold text-[#0071E3] hover:bg-[#EFF6FF] border border-[#0071E3]/20 rounded-[6px] transition-colors cursor-pointer flex items-center gap-1.5"
          >
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
            </svg>
            <span>Aplicar en Lote</span>
          </button>
          <span class="text-xs text-[#86868B] font-mono">
            {{ rutasFiltradas.length }} de {{ rutas.length }} rutas
          </span>
        </div>
      </div>

      <!-- TABLA MATRIZ DE VENTANAS POR RUTA -->
      <div class="bg-white border border-[#E5E5EA] rounded-[10px] overflow-hidden shadow-2xs">
        <div class="overflow-x-auto">
          <table class="w-full text-left border-collapse">
            <thead>
              <tr class="bg-[#FBFBFD] border-b border-[#E5E5EA] text-[11px] font-semibold text-[#6E6E73] uppercase tracking-wider">
                <th class="py-2.5 px-3.5">Ruta / Vendedor</th>
                <th class="py-2.5 px-3 text-center">Canal</th>
                <th class="py-2.5 px-3 text-center">Clientes</th>
                <th class="py-2.5 px-3 text-center">Ventana Histórica</th>
                <th class="py-2.5 px-3 text-center">Corte de Compras en API</th>
                <th class="py-2.5 px-3 text-right">Acción</th>
              </tr>
            </thead>

            <!-- Skeleton Loader -->
            <tbody v-if="loading" class="divide-y divide-[#E5E5EA]">
              <tr v-for="n in 5" :key="n" class="animate-pulse">
                <td class="py-3 px-3.5"><div class="h-4 bg-[#EFEFF4] rounded w-36"></div></td>
                <td class="py-3 px-3 text-center"><div class="h-4 bg-[#EFEFF4] rounded w-12 mx-auto"></div></td>
                <td class="py-3 px-3 text-center"><div class="h-4 bg-[#EFEFF4] rounded w-16 mx-auto"></div></td>
                <td class="py-3 px-3 text-center"><div class="h-4 bg-[#EFEFF4] rounded w-24 mx-auto"></div></td>
                <td class="py-3 px-3 text-center"><div class="h-4 bg-[#EFEFF4] rounded w-28 mx-auto"></div></td>
                <td class="py-3 px-3 text-right"><div class="h-6 bg-[#EFEFF4] rounded w-14 ml-auto"></div></td>
              </tr>
            </tbody>

            <!-- Contenido Real -->
            <tbody v-else-if="rutasFiltradas.length > 0" class="divide-y divide-[#E5E5EA] text-xs">
              <tr
                v-for="r in rutasFiltradas"
                :key="r.ruta"
                class="hover:bg-[#F9F9FB] transition-colors"
                :class="r.es_personalizado ? 'bg-[#FBFDFF]' : ''"
              >
                <!-- Ruta / Vendedor -->
                <td class="py-2.5 px-3.5 align-middle">
                  <div class="flex items-center gap-1.5 flex-wrap">
                    <span class="font-bold text-[#1D1D1F] font-mono">{{ r.ruta }}</span>
                  </div>
                  <div class="text-[11px] text-[#6E6E73] truncate max-w-[200px]" :title="r.vendedor">
                    {{ r.vendedor }}
                  </div>
                </td>

                <!-- Canal -->
                <td class="py-2.5 px-3 align-middle text-center">
                  <span
                    class="text-[9px] font-mono font-bold px-2 py-0.5 rounded text-white"
                    :style="{ backgroundColor: getCanalColor(r.canal) }"
                  >
                    {{ r.canal }}
                  </span>
                </td>

                <!-- Clientes -->
                <td class="py-2.5 px-3 align-middle text-center font-mono text-[#86868B]">
                  {{ r.total_clientes }} cli
                </td>

                <!-- Ventana Histórica -->
                <td class="py-2.5 px-3 align-middle text-center">
                  <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-[6px] border text-xs font-mono font-semibold"
                    :class="r.es_personalizado
                      ? 'bg-[#EFF6FF] border-[#0071E3]/30 text-[#0071E3]'
                      : 'bg-[#F9F9FB] border-[#E5E5EA] text-[#6E6E73]'"
                  >
                    <span>{{ r.dias_historial }} días</span>
                    <span
                      class="text-[9px] px-1 py-0.2 rounded font-sans uppercase font-bold"
                      :class="r.es_personalizado ? 'bg-[#0071E3] text-white' : 'bg-[#E5E5EA] text-[#6E6E73]'"
                    >
                      {{ r.es_personalizado ? 'Personalizado' : 'Global' }}
                    </span>
                  </div>
                </td>

                <!-- Corte API -->
                <td class="py-2.5 px-3 align-middle text-center font-mono">
                  <div class="text-xs text-[#1D1D1F] font-medium">Desde {{ r.fecha_desde }}</div>
                  <div class="text-[10px] text-[#86868B]">descarga compras ERP</div>
                </td>

                <!-- Acciones -->
                <td class="py-2.5 px-3 align-middle text-right">
                  <div class="flex items-center justify-end gap-1.5">
                    <button
                      v-if="isSuperAdmin"
                      type="button"
                      @click="abrirModalEdicion(r)"
                      class="px-2.5 py-1 text-[11px] font-semibold text-[#0071E3] hover:bg-[#EFF6FF] border border-[#0071E3]/20 rounded-[5px] transition-colors cursor-pointer"
                    >
                      Editar
                    </button>

                    <button
                      v-if="isSuperAdmin && r.es_personalizado"
                      type="button"
                      @click="resetearRuta(r)"
                      class="p-1 text-[#86868B] hover:text-[#C9342C] hover:bg-[#FDF0EF] rounded transition-colors cursor-pointer"
                      title="Restablecer a la ventana global"
                    >
                      <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                      </svg>
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>

            <!-- Empty State -->
            <tbody v-else>
              <tr>
                <td colspan="6" class="p-8 text-center text-xs text-[#86868B]">
                  No se encontraron rutas comerciales con los filtros aplicados.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- MODAL APPLE PRO: EDICIÓN POR RUTA -->
      <div
        v-if="modalEdicion.show && modalEdicion.ruta"
        class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/40 backdrop-blur-xs"
        @click.self="cerrarModalEdicion"
      >
        <div class="bg-white rounded-[14px] shadow-2xl border border-[#E5E5EA] w-full max-w-md overflow-hidden flex flex-col">
          <div class="px-5 py-3.5 border-b border-[#E5E5EA] flex items-center justify-between bg-[#FBFBFD]">
            <div>
              <div class="flex items-center gap-2">
                <span
                  class="text-[9px] font-mono font-bold px-1.5 py-0.2 rounded text-white"
                  :style="{ backgroundColor: getCanalColor(modalEdicion.ruta.canal) }"
                >
                  {{ modalEdicion.ruta.canal }}
                </span>
                <h2 class="text-base font-bold text-[#1D1D1F] font-mono">
                  Ruta {{ modalEdicion.ruta.ruta }}
                </h2>
              </div>
              <p class="text-[11px] text-[#6E6E73] mt-0.5">{{ modalEdicion.ruta.vendedor }}</p>
            </div>

            <button
              type="button"
              @click="cerrarModalEdicion"
              class="w-7 h-7 rounded-full bg-[#E5E5EA]/60 hover:bg-[#E5E5EA] text-[#6E6E73] flex items-center justify-center text-sm font-bold cursor-pointer"
            >
              &times;
            </button>
          </div>

          <div class="p-5 space-y-4">
            <div>
              <label class="text-xs font-semibold text-[#1D1D1F] block mb-2">
                Selecciona la ventana histórica de compras:
              </label>

              <!-- Presets Rápidos -->
              <div class="grid grid-cols-4 gap-2 mb-3">
                <button
                  v-for="preset in [15, 30, 45, 60, 90, 120, 180, 365]"
                  :key="preset"
                  type="button"
                  @click="modalEdicion.dias = preset"
                  class="py-1.5 text-xs font-mono rounded-[6px] border text-center transition-all cursor-pointer"
                  :class="modalEdicion.dias === preset
                    ? 'bg-[#0071E3] text-white border-[#0071E3] font-bold shadow-xs'
                    : 'bg-white text-[#1D1D1F] border-[#E5E5EA] hover:bg-[#F2F2F7]'"
                >
                  {{ preset }}d
                </button>
              </div>

              <!-- Input Días -->
              <div class="relative">
                <input
                  v-model.number="modalEdicion.dias"
                  type="number"
                  min="1"
                  max="365"
                  class="w-full h-9 pl-3 pr-8 text-xs font-mono font-semibold text-[#1D1D1F] bg-white border border-[#E5E5EA] rounded-[6px] focus:outline-none focus:border-[#0071E3]"
                />
                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-[#86868B] font-mono pointer-events-none">días</span>
              </div>
            </div>

            <!-- Preview Resultado -->
            <div class="p-3 bg-[#F9F9FB] rounded-[8px] border border-[#E5E5EA] text-xs space-y-1">
              <div class="flex items-center justify-between text-[#86868B]">
                <span>Corte calculado:</span>
                <span class="font-mono font-bold text-[#1D1D1F]">{{ calcularFechaCorte(modalEdicion.dias) }}</span>
              </div>
              <p class="text-[11px] text-[#6E6E73] mt-1">
                Cuando el vendedor tome stock de un cliente, la app solicitará compras en ERP desde esa fecha.
              </p>
            </div>
          </div>

          <div class="px-5 py-3 border-t border-[#E5E5EA] bg-[#FBFBFD] flex items-center justify-between">
            <button
              v-if="modalEdicion.ruta.es_personalizado"
              type="button"
              @click="resetearDesdeModal"
              :disabled="modalEdicion.guardando"
              class="text-xs text-[#C9342C] hover:underline cursor-pointer"
            >
              Restablecer a Global ({{ globalDias }}d)
            </button>
            <div v-else></div>

            <div class="flex items-center gap-2">
              <button
                type="button"
                @click="cerrarModalEdicion"
                :disabled="modalEdicion.guardando"
                class="px-3 py-1.5 text-xs text-[#1D1D1F] bg-white border border-[#E5E5EA] hover:bg-[#F2F2F7] rounded-[6px] transition-colors cursor-pointer"
              >
                Cancelar
              </button>
              <button
                type="button"
                @click="guardarModalEdicion"
                :disabled="modalEdicion.guardando"
                class="px-4 py-1.5 text-xs font-semibold text-white bg-[#0071E3] hover:bg-[#0077ED] rounded-[6px] shadow-xs flex items-center gap-1.5 transition-colors cursor-pointer"
              >
                <span v-if="modalEdicion.guardando" class="w-3 h-3 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                <span>Guardar Ruta</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- MODAL APPLE PRO: APLICACIÓN EN LOTE (MASIVO) -->
      <div
        v-if="modalMasivo.show"
        class="fixed inset-0 z-50 flex items-center justify-center p-3 sm:p-4 bg-black/40 backdrop-blur-xs"
        @click.self="cerrarModalMasivo"
      >
        <div class="bg-white rounded-[14px] shadow-2xl border border-[#E5E5EA] w-full max-w-lg overflow-hidden flex flex-col">
          <div class="px-5 py-3.5 border-b border-[#E5E5EA] flex items-center justify-between bg-[#FBFBFD]">
            <div>
              <h2 class="text-base font-bold text-[#1D1D1F]">Aplicar Ventana en Lote</h2>
              <p class="text-[11px] text-[#6E6E73] mt-0.5">Asigna los días de historial a múltiples rutas a la vez</p>
            </div>
            <button
              type="button"
              @click="cerrarModalMasivo"
              class="w-7 h-7 rounded-full bg-[#E5E5EA]/60 hover:bg-[#E5E5EA] text-[#6E6E73] flex items-center justify-center text-sm font-bold cursor-pointer"
            >
              &times;
            </button>
          </div>

          <div class="p-5 space-y-4">
            <!-- Selección de Destino -->
            <div>
              <label class="text-xs font-semibold text-[#1D1D1F] block mb-1.5">Destino de la aplicación:</label>
              <div class="grid grid-cols-2 gap-2">
                <button
                  type="button"
                  @click="modalMasivo.alcance = 'CANAL'"
                  class="p-2.5 rounded-[8px] border text-left transition-all cursor-pointer"
                  :class="modalMasivo.alcance === 'CANAL'
                    ? 'bg-[#EFF6FF] border-[#0071E3] text-[#0071E3] font-semibold'
                    : 'bg-white border-[#E5E5EA] text-[#1D1D1F]'"
                >
                  <span class="text-xs block font-bold">Por Canal</span>
                  <span class="text-[10px] text-[#86868B]">Todas las rutas del canal</span>
                </button>

                <button
                  type="button"
                  @click="modalMasivo.alcance = 'FILTRADAS'"
                  class="p-2.5 rounded-[8px] border text-left transition-all cursor-pointer"
                  :class="modalMasivo.alcance === 'FILTRADAS'
                    ? 'bg-[#EFF6FF] border-[#0071E3] text-[#0071E3] font-semibold'
                    : 'bg-white border-[#E5E5EA] text-[#1D1D1F]'"
                >
                  <span class="text-xs block font-bold">Rutas Filtradas</span>
                  <span class="text-[10px] text-[#86868B]">{{ rutasFiltradas.length }} rutas actuales</span>
                </button>
              </div>
            </div>

            <!-- Selección de Canal si aplica -->
            <div v-if="modalMasivo.alcance === 'CANAL'">
              <label class="text-xs font-semibold text-[#1D1D1F] block mb-1.5">Selecciona el canal:</label>
              <select
                v-model="modalMasivo.canalSeleccionado"
                class="w-full h-8 px-2.5 bg-white border border-[#E5E5EA] rounded-[6px] text-xs text-[#1D1D1F] focus:outline-none focus:border-[#0071E3]"
              >
                <option v-for="c in canales" :key="c" :value="c">{{ c }}</option>
              </select>
            </div>

            <!-- Días de Ventana -->
            <div>
              <label class="text-xs font-semibold text-[#1D1D1F] block mb-1.5">Días de Historial a Asignar:</label>
              <div class="grid grid-cols-4 gap-2 mb-2">
                <button
                  v-for="preset in [15, 30, 45, 60, 90, 120, 180, 365]"
                  :key="preset"
                  type="button"
                  @click="modalMasivo.dias = preset"
                  class="py-1 text-xs font-mono rounded-[6px] border text-center transition-all cursor-pointer"
                  :class="modalMasivo.dias === preset
                    ? 'bg-[#0071E3] text-white border-[#0071E3] font-bold'
                    : 'bg-white text-[#1D1D1F] border-[#E5E5EA]'"
                >
                  {{ preset }}d
                </button>
              </div>
              <div class="relative">
                <input
                  v-model.number="modalMasivo.dias"
                  type="number"
                  min="1"
                  max="365"
                  class="w-full h-8 pl-3 pr-8 text-xs font-mono text-[#1D1D1F] bg-white border border-[#E5E5EA] rounded-[6px] focus:outline-none focus:border-[#0071E3]"
                />
                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-[#86868B] font-mono pointer-events-none">días</span>
              </div>
            </div>
          </div>

          <div class="px-5 py-3 border-t border-[#E5E5EA] bg-[#FBFBFD] flex items-center justify-end gap-2">
            <button
              type="button"
              @click="cerrarModalMasivo"
              :disabled="modalMasivo.guardando"
              class="px-3 py-1.5 text-xs text-[#1D1D1F] bg-white border border-[#E5E5EA] hover:bg-[#F2F2F7] rounded-[6px] transition-colors cursor-pointer"
            >
              Cancelar
            </button>
            <button
              type="button"
              @click="ejecutarAplicacionMasiva"
              :disabled="modalMasivo.guardando"
              class="px-4 py-1.5 text-xs font-semibold text-white bg-[#0071E3] hover:bg-[#0077ED] rounded-[6px] shadow-xs flex items-center gap-1.5 transition-colors cursor-pointer"
            >
              <span v-if="modalMasivo.guardando" class="w-3 h-3 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
              <span>Aplicar Masivamente</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Toast Feedback -->
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="transform translate-y-2 opacity-0"
        enter-to-class="transform translate-y-0 opacity-100"
        leave-active-class="transition duration-150 ease-in"
        leave-from-class="transform translate-y-0 opacity-100"
        leave-to-class="transform translate-y-2 opacity-0"
      >
        <div
          v-if="toast.show"
          class="fixed bottom-5 right-5 z-50 px-4 py-2.5 rounded-[8px] shadow-xl text-xs font-medium flex items-center gap-2 border transition-all"
          :class="toast.isError
            ? 'bg-[#FDF0EF] text-[#C9342C] border-[#F5C2C0]'
            : 'bg-[#1D1D1F] text-white border-white/10'"
        >
          <svg v-if="!toast.isError" class="w-4 h-4 text-[#248A3D] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
          </svg>
          <svg v-else class="w-4 h-4 text-[#C9342C] shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
          <span>{{ toast.message }}</span>
        </div>
      </Transition>
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

const page = usePage();
const authUser = computed(() => page.props.auth?.user);
const isSuperAdmin = computed(() => !!authUser.value?.is_superadmin);

const props = defineProps({
  initialData: {
    type: Object,
    default: () => ({}),
  },
});

const globalDias = ref(props.initialData?.global_dias || 30);
const nuevoGlobalDias = ref(props.initialData?.global_dias || 30);
const fechaGlobalDesde = ref(props.initialData?.fecha_global_desde || '');
const rutas = ref(props.initialData?.rutas ? [...props.initialData.rutas] : []);
const canales = ref(props.initialData?.canales ? [...props.initialData.canales] : []);
const kpis = ref(props.initialData?.kpis || {});

const loading = ref(false);
const guardandoGlobal = ref(false);

// Filtros
const filtroBusqueda = ref('');
const filtroCanal = ref('TODOS');
const filtroTipo = ref('TODAS');

const CANAL_COLORS = {
  'MAY': '#0071E3',
  'TDB': '#34C759',
  'MZO': '#FF9500',
  'PROV': '#AF52DE',
  'PRT': '#8E8E93',
};

function getCanalColor(canal) {
  return CANAL_COLORS[canal] || '#5856D6';
}

const rutasFiltradas = computed(() => {
  let list = rutas.value;

  if (filtroCanal.value !== 'TODOS') {
    list = list.filter(r => r.canal === filtroCanal.value);
  }

  if (filtroTipo.value === 'PERSONALIZADAS') {
    list = list.filter(r => r.es_personalizado);
  } else if (filtroTipo.value === 'GLOBALES') {
    list = list.filter(r => !r.es_personalizado);
  }

  if (filtroBusqueda.value.trim()) {
    const q = filtroBusqueda.value.toLowerCase().trim();
    list = list.filter(r =>
      r.ruta.toLowerCase().includes(q) ||
      (r.vendedor && r.vendedor.toLowerCase().includes(q))
    );
  }

  return list;
});

// Toast
const toast = ref({ show: false, message: '', isError: false });
function showToast(message, isError = false) {
  toast.value = { show: true, message, isError };
  setTimeout(() => {
    toast.value.show = false;
  }, 4500);
}

// Carga de datos
async function fetchData() {
  loading.value = true;
  try {
    const res = await axios.post('/supervisor/stock-ventana/data');
    globalDias.value = res.data.global_dias || 30;
    nuevoGlobalDias.value = res.data.global_dias || 30;
    fechaGlobalDesde.value = res.data.fecha_global_desde || '';
    rutas.value = res.data.rutas || [];
    canales.value = res.data.canales || [];
    kpis.value = res.data.kpis || {};
  } catch (err) {
    console.error('Error al cargar datos de ventana de stock:', err);
    showToast('Error al recargar la matriz de ventana de stock.', true);
  } finally {
    loading.value = false;
  }
}

// Guardar Global
async function guardarGlobal() {
  if (!isSuperAdmin.value) return;

  guardandoGlobal.value = true;
  try {
    const res = await axios.post('/supervisor/stock-ventana/global', {
      dias_historial: nuevoGlobalDias.value,
    });
    showToast(res.data.message || 'Ventana histórica global actualizada.');
    await fetchData();
  } catch (err) {
    console.error('Error al guardar ventana global:', err);
    showToast(err.response?.data?.message || 'Error al actualizar la ventana global.', true);
  } finally {
    guardandoGlobal.value = false;
  }
}

// Modal Edición por Ruta
const modalEdicion = ref({
  show: false,
  ruta: null,
  dias: 30,
  guardando: false,
});

function abrirModalEdicion(rutaObj) {
  modalEdicion.value = {
    show: true,
    ruta: rutaObj,
    dias: rutaObj.dias_historial || globalDias.value,
    guardando: false,
  };
}

function cerrarModalEdicion() {
  modalEdicion.value.show = false;
  modalEdicion.value.ruta = null;
}

function calcularFechaCorte(dias) {
  const d = new Date();
  d.setDate(d.getDate() - Number(dias || 30));
  const dia = String(d.getDate()).padStart(2, '0');
  const mes = String(d.getMonth() + 1).padStart(2, '0');
  const anio = d.getFullYear();
  return `${dia}/${mes}/${anio}`;
}

async function guardarModalEdicion() {
  modalEdicion.value.guardando = true;
  const rNombre = modalEdicion.value.ruta.ruta;
  try {
    const res = await axios.post('/supervisor/stock-ventana/route', {
      ruta: rNombre,
      dias_historial: modalEdicion.value.dias,
    });
    showToast(res.data.message || `Ruta ${rNombre} configurada.`);
    cerrarModalEdicion();
    await fetchData();
  } catch (err) {
    console.error('Error al guardar ruta:', err);
    showToast(err.response?.data?.message || 'Error al actualizar la ruta.', true);
  } finally {
    modalEdicion.value.guardando = false;
  }
}

async function resetearDesdeModal() {
  const rNombre = modalEdicion.value.ruta.ruta;
  modalEdicion.value.guardando = true;
  try {
    const res = await axios.post('/supervisor/stock-ventana/route/reset', {
      ruta: rNombre,
    });
    showToast(res.data.message || `Ruta ${rNombre} restablecida a global.`);
    cerrarModalEdicion();
    await fetchData();
  } catch (err) {
    console.error('Error al restablecer ruta:', err);
    showToast(err.response?.data?.message || 'Error al restablecer la ruta.', true);
  } finally {
    modalEdicion.value.guardando = false;
  }
}

async function resetearRuta(rutaObj) {
  if (!confirm(`¿Restablecer la ruta ${rutaObj.ruta} a la ventana global (${globalDias.value} días)?`)) {
    return;
  }

  try {
    const res = await axios.post('/supervisor/stock-ventana/route/reset', {
      ruta: rutaObj.ruta,
    });
    showToast(res.data.message || `Ruta ${rutaObj.ruta} restablecida a global.`);
    await fetchData();
  } catch (err) {
    console.error('Error al restablecer ruta:', err);
    showToast(err.response?.data?.message || 'Error al restablecer la ruta.', true);
  }
}

// Modal Masivo
const modalMasivo = ref({
  show: false,
  alcance: 'CANAL',
  canalSeleccionado: 'TDB',
  dias: 30,
  guardando: false,
});

function abrirModalMasivo() {
  modalMasivo.value = {
    show: true,
    alcance: 'CANAL',
    canalSeleccionado: canales.value[0] || 'TDB',
    dias: globalDias.value,
    guardando: false,
  };
}

function cerrarModalMasivo() {
  modalMasivo.value.show = false;
}

async function ejecutarAplicacionMasiva() {
  let rutasDestino = [];
  if (modalMasivo.value.alcance === 'CANAL') {
    rutasDestino = rutas.value
      .filter(r => r.canal === modalMasivo.value.canalSeleccionado)
      .map(r => r.ruta);
  } else {
    rutasDestino = rutasFiltradas.value.map(r => r.ruta);
  }

  if (rutasDestino.length === 0) {
    showToast('No hay rutas en el conjunto seleccionado.', true);
    return;
  }

  if (!confirm(`¿Aplicar ${modalMasivo.value.dias} días de historial a las ${rutasDestino.length} rutas seleccionadas?`)) {
    return;
  }

  modalMasivo.value.guardando = true;
  try {
    const res = await axios.post('/supervisor/stock-ventana/batch', {
      rutas: rutasDestino,
      dias_historial: modalMasivo.value.dias,
    });
    showToast(res.data.message || 'Rutas actualizadas exitosamente.');
    cerrarModalMasivo();
    await fetchData();
  } catch (err) {
    console.error('Error en aplicación masiva:', err);
    showToast(err.response?.data?.message || 'Error al aplicar en lote.', true);
  } finally {
    modalMasivo.value.guardando = false;
  }
}
</script>

