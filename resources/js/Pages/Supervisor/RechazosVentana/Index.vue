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
            Selecciona los días de preventa a evaluar contra la facturación en ERP para enviar los pedidos rechazados a la app móvil de los vendedores.
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

      <!-- CARD HERO: SELECCIÓN DIRECTA DE DÍAS DE PREVENTA (APPLE PRO) -->
      <div class="bg-white border border-[#E5E5EA] rounded-[12px] p-4 sm:p-5 shadow-2xs space-y-4">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
          <div class="space-y-1">
            <div class="flex items-center gap-2">
              <div class="w-7 h-7 rounded-[8px] bg-[#0071E3]/10 text-[#0071E3] flex items-center justify-center">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
              </div>

              <h2 class="text-sm font-bold text-[#1D1D1F]">
                Días de Preventa a Evaluar para Hoy ({{ evalInfo.dia_actual }})
              </h2>

              <span
                class="px-2 py-0.5 rounded-full text-[10px] font-mono font-bold"
                :class="evalInfo.es_excepcion
                  ? 'bg-[#FFF4E5] text-[#B25E00] border border-[#FF9500]/30'
                  : 'bg-[#EFF6FF] text-[#0071E3] border border-[#0071E3]/20'"
              >
                {{ evalInfo.es_excepcion ? 'Excepción Feriado' : 'Modo Semanal Activo' }}
              </span>
            </div>

            <p class="text-xs text-[#6E6E73]">
              {{ evalInfo.descripcion }}
            </p>
          </div>

          <!-- Impacto en Fechas Resueltas -->
          <div class="bg-[#F9F9FB] border border-[#E5E5EA] rounded-[8px] px-3.5 py-2 flex items-center gap-3 shrink-0">
            <div>
              <span class="text-[10px] font-medium text-[#86868B] block uppercase tracking-wider">Fechas de preventa evaluadas:</span>
              <span class="text-xs font-mono font-bold text-[#1D1D1F]">
                {{ evalInfo.fechas.length > 0 ? evalInfo.fechas.join(', ') : 'Ninguna' }}
              </span>
            </div>
            <span class="w-2.5 h-2.5 rounded-full" :class="evalInfo.es_excepcion ? 'bg-[#FF9500] animate-pulse' : 'bg-[#34C759]'"></span>
          </div>
        </div>

        <!-- SELECTOR RÁPIDO DE DÍAS (BOTONES CON NOMBRE Y FECHA EXACTA) -->
        <div class="pt-3 border-t border-[#E5E5EA] space-y-3">
          <div class="flex items-center justify-between flex-wrap gap-2">
            <span class="text-xs font-medium text-[#1D1D1F]">
              Haz clic en los días de preventa que se deben cruzar hoy (ej: <strong>Viernes y Sábado</strong>):
            </span>

            <div class="flex items-center gap-2">
              <button
                v-if="canEditGlobal"
                type="button"
                @click="guardarDiasSeleccionados"
                :disabled="guardando || diasSeleccionadosLocales.length === 0"
                class="h-8 px-4 text-xs font-semibold text-white bg-[#0071E3] hover:bg-[#0077ED] disabled:opacity-40 rounded-[6px] shadow-xs inline-flex items-center gap-1.5 transition-colors cursor-pointer"
              >
                <span v-if="guardando" class="w-3 h-3 border-2 border-white/30 border-t-white rounded-full animate-spin"></span>
                <span>Guardar Días de Preventa</span>
              </button>
            </div>
          </div>

          <!-- Botones de Días con Fechas -->
          <div class="flex flex-wrap gap-2">
            <button
              v-for="opc in opcionesDias"
              :key="opc.nombre"
              type="button"
              @click="toggleDia(opc.nombre)"
              class="h-9 px-3.5 rounded-[8px] border text-xs font-mono transition-all flex items-center gap-2 cursor-pointer select-none"
              :class="isDiaActivo(opc.nombre)
                ? 'bg-[#0071E3] text-white border-[#0071E3] font-bold shadow-xs'
                : 'bg-white text-[#1D1D1F] border-[#E5E5EA] hover:bg-[#F2F2F7]'"
            >
              <!-- Checkmark icono -->
              <span
                class="w-4 h-4 rounded-full flex items-center justify-center text-[10px]"
                :class="isDiaActivo(opc.nombre) ? 'bg-white/20 text-white' : 'border border-[#E5E5EA] text-transparent'"
              >
                ✓
              </span>
              <span>{{ opc.nombre }}</span>
              <span
                class="px-1.5 py-0.2 rounded text-[10px] font-mono"
                :class="isDiaActivo(opc.nombre) ? 'bg-white/25 text-white' : 'bg-[#F2F2F7] text-[#6E6E73]'"
              >
                {{ opc.fecha_corta }}
              </span>
            </button>
          </div>

          <!-- Selector de Fecha Específica / Feriado Alternativo -->
          <div class="pt-2 flex flex-wrap items-center justify-between gap-3 text-xs">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-[#86868B]">¿Hubo feriado o fecha especial?</span>
              <button
                type="button"
                @click="mostrarSelectorFechaManual = !mostrarSelectorFechaManual"
                class="text-[#0071E3] hover:underline font-medium cursor-pointer"
              >
                {{ mostrarSelectorFechaManual ? 'Ocultar selector de fecha fija' : 'Fijar fecha de preventa por calendario' }}
              </button>
            </div>

            <div v-if="evalInfo.es_excepcion" class="flex items-center gap-2">
              <span class="text-[#FF9500] font-medium">Excepción manual activa.</span>
              <button
                type="button"
                @click="limpiarExcepcion"
                :disabled="guardando"
                class="text-[#0071E3] hover:underline font-medium cursor-pointer"
              >
                Volver a días automáticos
              </button>
            </div>
          </div>

          <!-- Input de Fecha Manual (Opcional si hubo feriado atípico) -->
          <div v-if="mostrarSelectorFechaManual" class="p-3 bg-[#F9F9FB] rounded-[8px] border border-[#E5E5EA] flex flex-wrap items-center gap-3">
            <span class="text-xs text-[#1D1D1F] font-medium">Fecha de Preventa Fija:</span>
            <input
              type="date"
              v-model="inputFechaManual"
              class="h-8 px-2.5 text-xs font-mono text-[#1D1D1F] bg-white border border-[#E5E5EA] rounded-[6px] focus:outline-none focus:border-[#0071E3]"
            />
            <span class="text-xs text-[#86868B]">hasta (opcional)</span>
            <input
              type="date"
              v-model="inputFechaManualHasta"
              class="h-8 px-2.5 text-xs font-mono text-[#1D1D1F] bg-white border border-[#E5E5EA] rounded-[6px] focus:outline-none focus:border-[#0071E3]"
            />
            <button
              type="button"
              @click="guardarFechaManual"
              :disabled="guardando || !inputFechaManual"
              class="h-8 px-3 text-xs font-semibold text-white bg-[#0071E3] hover:bg-[#0077ED] disabled:opacity-40 rounded-[6px] cursor-pointer transition-colors"
            >
              Aplicar Fecha Calendario
            </button>
          </div>
        </div>
      </div>

      <!-- TABLA MATRIZ DE RUTAS (CON CANAL TDB POR DEFECTO) -->
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

            <!-- Filtro de Canal (Por Defecto TDB) -->
            <select
              v-model="filtroCanal"
              class="h-8 px-2.5 text-xs bg-white border border-[#E5E5EA] rounded-[6px] text-[#1D1D1F] focus:outline-none focus:border-[#0071E3] font-mono"
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
                  <th class="py-2.5 px-3.5">Ruta Comercial</th>
                  <th class="py-2.5 px-2.5 text-center">Canal</th>
                  <th class="py-2.5 px-2.5 text-center" title="Total de preventas evaluadas en el ciclo">Preventas</th>
                  <th class="py-2.5 px-2.5 text-center" title="Pedidos facturados en su totalidad">Entregados</th>
                  <th class="py-2.5 px-2.5 text-center" title="Pedidos con entrega parcial (facturado menor a preventa)">Parciales</th>
                  <th class="py-2.5 px-2.5 text-center" title="Pedidos caídos (0% facturado)">Caídos (0%)</th>
                  <th class="py-2.5 px-2.5 text-center" title="Pedidos con justificación ya guardada">Justificados</th>
                  <th class="py-2.5 px-2.5 text-center" title="Rechazos pendientes por justificar (Caídos + Parciales - Justificados)">Rechazos Pend.</th>
                  <th class="py-2.5 px-3 text-center" title="Monto en Bs pendiente de facturar">Monto Rechazado</th>
                  <th class="py-2.5 px-2.5 text-center">Estado Módulo</th>
                  <th class="py-2.5 px-3 text-right">Acciones</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-[#E5E5EA] text-xs">
                <tr v-if="rutasFiltradas.length === 0">
                  <td colspan="11" class="py-8 text-center text-xs text-[#86868B]">
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
                    <div class="text-[11px] text-[#6E6E73] truncate max-w-[200px]">{{ r.vendedor }}</div>
                  </td>

                  <!-- Canal -->
                  <td class="py-2.5 px-2.5 align-middle text-center">
                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-[#F2F2F7] text-[#1D1D1F]">
                      {{ r.canal }}
                    </span>
                  </td>

                  <!-- Total Preventas -->
                  <td class="py-2.5 px-2.5 align-middle text-center font-mono font-semibold text-[#1D1D1F]">
                    {{ r.total_preventas }}
                  </td>

                  <!-- Entregados -->
                  <td class="py-2.5 px-2.5 align-middle text-center font-mono text-[#34C759]">
                    {{ r.pedidos_entregados }}
                  </td>

                  <!-- Parciales -->
                  <td class="py-2.5 px-2.5 align-middle text-center font-mono" :class="r.pedidos_parciales > 0 ? 'text-[#FF9500] font-semibold' : 'text-[#86868B]'">
                    {{ r.pedidos_parciales }}
                  </td>

                  <!-- Caídos (0%) -->
                  <td class="py-2.5 px-2.5 align-middle text-center font-mono" :class="r.pedidos_caidos > 0 ? 'text-[#FF3B30] font-semibold' : 'text-[#86868B]'">
                    {{ r.pedidos_caidos }}
                  </td>

                  <!-- Justificados -->
                  <td class="py-2.5 px-2.5 align-middle text-center font-mono text-[#86868B]">
                    {{ r.pedidos_justificados }}
                  </td>

                  <!-- Rechazos Pendientes -->
                  <td class="py-2.5 px-2.5 align-middle text-center font-mono">
                    <span
                      class="px-2.5 py-0.5 rounded-full text-xs font-bold"
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
                        title="Ver preventas rechazadas de esta ruta"
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
              <div>Todas las preventas de estas fechas fueron facturadas en su totalidad o ya cuentan con justificación.</div>
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
                    <span class="text-[10px] font-mono text-[#86868B]">• Fecha: {{ p.fecha_preventa }}</span>
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
const evalInfo = ref(props.initialData.eval_info || { fechas: [], dias_activos: [] });
const opcionesDias = ref(props.initialData.opciones_dias || []);
const rutas = ref(props.initialData.rutas || []);
const canales = ref(props.initialData.canales || []);

// Estado local de días seleccionados
const diasSeleccionadosLocales = ref([...(evalInfo.value?.dias_activos || ['Viernes', 'Sábado'])]);

// Estados de carga y formulario
const loading = ref(false);
const guardando = ref(false);
const mostrarSelectorFechaManual = ref(false);
const inputFechaManual = ref('');
const inputFechaManualHasta = ref('');

// FILTRO POR CANAL: POR DEFECTO 'TDB'
const filtroCanal = ref('TDB');
const busqueda = ref('');
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

// Helpers de selección de días
function isDiaActivo(diaNombre) {
  const norm = (s) => s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  return diasSeleccionadosLocales.value.some((d) => norm(d) === norm(diaNombre));
}

function toggleDia(diaNombre) {
  const norm = (s) => s.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  const target = norm(diaNombre);
  const idx = diasSeleccionadosLocales.value.findIndex((d) => norm(d) === target);

  if (idx > -1) {
    if (diasSeleccionadosLocales.value.length > 1) {
      diasSeleccionadosLocales.value.splice(idx, 1);
    }
  } else {
    diasSeleccionadosLocales.value.push(diaNombre);
  }
}

// Guardar los días seleccionados
async function guardarDiasSeleccionados() {
  guardando.value = true;
  try {
    const res = await axios.post('/supervisor/rechazos-ventana/global', {
      modo: 'semanal',
      dias_seleccionados: diasSeleccionadosLocales.value,
      fecha_especifica_activa: null,
      fecha_especifica_hasta: null,
    });
    configGlobal.value = res.data.config;
    await fetchData();
  } catch (e) {
    alert(e.response?.data?.message || 'Error guardando días de preventa');
  } finally {
    guardando.value = false;
  }
}

// Aplicar fecha manual o feriado
async function guardarFechaManual() {
  if (!inputFechaManual.value) return;
  guardando.value = true;
  try {
    await axios.post('/supervisor/rechazos-ventana/global', {
      modo: 'fecha_fija',
      fecha_especifica_activa: inputFechaManual.value,
      fecha_especifica_hasta: inputFechaManualHasta.value || null,
    });
    await fetchData();
  } catch (e) {
    alert(e.response?.data?.message || 'Error guardando fecha manual');
  } finally {
    guardando.value = false;
  }
}

// Limpiar excepción y volver a días automáticos
async function limpiarExcepcion() {
  guardando.value = true;
  try {
    await axios.post('/supervisor/rechazos-ventana/global', {
      modo: 'semanal',
      fecha_especifica_activa: null,
      fecha_especifica_hasta: null,
    });
    inputFechaManual.value = '';
    inputFechaManualHasta.value = '';
    mostrarSelectorFechaManual.value = false;
    await fetchData();
  } catch (e) {
    alert(e.response?.data?.message || 'Error restableciendo a modo semanal');
  } finally {
    guardando.value = false;
  }
}

// Recargar datos desde el servidor
async function fetchData() {
  loading.value = true;
  try {
    const res = await axios.post('/supervisor/rechazos-ventana/data');
    configGlobal.value = res.data.config_global;
    evalInfo.value = res.data.eval_info;
    opcionesDias.value = res.data.opciones_dias || [];
    rutas.value = res.data.rutas;
    canales.value = res.data.canales;
    diasSeleccionadosLocales.value = [...(res.data.eval_info?.dias_activos || ['Viernes', 'Sábado'])];
  } catch (e) {
    console.error('Error al recargar datos:', e);
  } finally {
    loading.value = false;
  }
}

// Activar o desactivar ruta
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

// Previsualización en vivo
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
