<template>
  <div class="apple-glass-card rounded-[16px] overflow-hidden select-none font-sans">
    <!-- Encabezado Compacto y Parámetros -->
    <div class="px-3.5 py-2.5 border-b border-black/[0.06] flex flex-wrap items-center justify-between gap-2 bg-white/40">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <h3 class="text-[13px] font-bold text-[#1D1D1F] tracking-tight leading-tight">
            Pizarra Modelo Unilever · KPI 6: Pedido de Reposición (Match Ponderado f_i)
          </h3>
          <span class="px-2 py-0.5 rounded-[5px] text-[10px] font-bold text-[#1E6B37] bg-[#EBF9EF] border border-[#D1F2D9]">
            15 pts Base · Histórico {{ semanasFiltro }} Semanas · Escala Gradual 40% a 70%
          </span>
        </div>
        <p class="text-[11px] text-[#6E6E73] font-medium mt-0.5">
          Regla: Coincidencia ponderada por frecuencia semanal histórica (<strong class="text-[#1D1D1F]">f_i</strong> = semanas con compra). Escala: &lt; 40% (0 pts) · 40% a 69.9% (gradual hasta 14.95 pts) · &ge; 70% (15 pts). Clientes nuevos son neutros.
        </p>
      </div>

      <div class="flex items-center gap-2.5 text-[11px]">
        <!-- Selector de Ventana Histórica -->
        <div class="flex items-center gap-1.5 apple-control-pill px-2.5 py-1 rounded-[8px] text-[#6E6E73] font-medium">
          <span class="text-[9px] font-bold uppercase tracking-wider text-[#86868B]">Ventana:</span>
          <select
            v-model.number="semanasFiltro"
            @change="recalcularReposicion"
            class="bg-transparent font-bold text-[#1D1D1F] focus:outline-none cursor-pointer text-[11px]"
          >
            <option :value="4">4 semanas (1 mes)</option>
            <option :value="8">8 semanas (2 meses)</option>
            <option :value="13">13 semanas (Trimestre)</option>
            <option :value="26">26 semanas (Semestre)</option>
          </select>
        </div>

        <span class="apple-control-pill px-2.5 py-1 rounded-[8px] font-semibold text-[#6E6E73]">
          Rutas:
          <strong class="text-[#1D1D1F] font-mono ml-0.5">{{ store.loadingRanking ? '…' : rutasOrdenadas.length }}</strong>
        </span>
      </div>
    </div>

    <!-- Tabla Principal de Rutas -->
    <div class="overflow-x-auto custom-scroll sm:max-h-[calc(100vh-230px)] min-h-[380px]">
      <table class="w-full text-left border-collapse min-w-[620px] sm:min-w-[840px] text-[12px]">
        <thead class="sticky top-0 z-20 border-b border-black/[0.06] text-[10px] font-bold uppercase tracking-wider text-[#6E6E73] bg-[#F8F8FA]">
          <tr>
            <th class="px-1 sm:px-2 py-2 text-center sticky left-0 z-30 w-7 sm:w-9 border-r border-black/[0.05] bg-[#F8F8FA] text-[10px] sm:text-[11px]">Pos</th>
            <th class="px-1.5 sm:px-2.5 py-2 sticky left-7 sm:left-9 z-30 w-24 sm:w-44 md:w-52 border-r border-black/[0.06] shadow-[2px_0_4px_-2px_rgba(0,0,0,0.05)] bg-[#F8F8FA] text-[10px] sm:text-[11px] truncate">Ruta / Preventista</th>
            <th class="px-3 py-2 text-center border-r border-black/[0.04]">Compradores</th>
            <th class="px-3 py-2 text-center border-r border-black/[0.04]" title="Suma de pesos históricos f_i de todos los SKUs esperados">Pts Esperados</th>
            <th class="px-3 py-2 text-center border-r border-black/[0.04]" title="Suma de pesos f_i de los SKUs recomprados hoy">Pts Match</th>
            <th class="px-3 py-2 text-center border-r border-black/[0.04]">SKUs Match / Base</th>
            <th class="px-3 py-2 text-center border-r border-black/[0.04]">% Reposición</th>
            <th class="px-3 py-2 text-center border-r border-black/[0.04]">Estatus</th>
            <th class="px-3 py-2 text-right">Puntos (15)</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-black/[0.04]">
          <template v-if="store.loadingRanking">
            <tr v-for="i in 10" :key="`skel-rep-${i}`">
              <td class="px-1 sm:px-2 py-2 sticky left-0 w-7 sm:w-9"><div class="h-3.5 w-4 mx-auto skeleton"></div></td>
              <td class="px-1.5 sm:px-2.5 py-2 sticky left-7 sm:left-9 w-24 sm:w-44 md:w-52 shadow-[2px_0_4px_-2px_rgba(0,0,0,0.05)]">
                <div class="h-3.5 w-12 sm:w-16 skeleton mb-1"></div>
                <div class="h-3 w-16 sm:w-28 skeleton"></div>
              </td>
              <td v-for="c in 6" :key="c" class="px-3 py-2 text-center"><div class="h-4 w-12 mx-auto skeleton"></div></td>
              <td class="px-3 py-2 text-right"><div class="h-4 w-8 ml-auto skeleton"></div></td>
            </tr>
          </template>

          <tr v-else-if="rutasOrdenadas.length === 0">
            <td colspan="9" class="p-8 text-center text-[#86868B] text-[12px] font-medium">
              No se encontraron rutas para los filtros seleccionados.
            </td>
          </tr>

          <template v-else>
            <template v-for="(r, idx) in rutasOrdenadas" :key="r.ruta || idx">
              <tr
                @click="toggleReposicionAcordeon(r)"
                class="cursor-pointer transition-colors"
                :class="acordeonAbierto[r.ruta] ? 'bg-blue-50/40' : 'hover:bg-black/[0.02]'"
              >
                <!-- Pos -->
                <td class="px-1 sm:px-2 py-1.5 text-center font-mono text-[10px] sm:text-[11px] font-semibold text-[#86868B] sticky left-0 z-10 w-7 sm:w-9 border-r border-black/[0.04] bg-white/80 backdrop-blur-sm">
                  {{ idx + 1 }}
                </td>

                <!-- Ruta / Preventista -->
                <td
                  class="px-1.5 sm:px-2.5 py-1.5 sticky left-7 sm:left-9 z-10 w-24 sm:w-44 md:w-52 border-r border-black/[0.06] shadow-[2px_0_4px_-2px_rgba(0,0,0,0.05)] bg-white/80 backdrop-blur-sm truncate"
                  :title="`${r.ruta} - ${r.vendedor || 'No Asignado'}`"
                >
                  <div class="flex items-center gap-1 sm:gap-1.5">
                    <svg
                      class="w-3 h-3 sm:w-3.5 sm:h-3.5 text-[#86868B] transition-transform duration-200 shrink-0"
                      :class="acordeonAbierto[r.ruta] ? 'rotate-90 text-[#0071E3]' : ''"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                    <span class="font-bold text-[#1D1D1F] text-[11px] sm:text-[12px] leading-tight truncate">{{ r.ruta }}</span>
                  </div>
                  <div class="text-[9.5px] sm:text-[11px] text-[#6E6E73] font-medium leading-tight truncate pl-4 sm:pl-5">
                    {{ r.vendedor || 'No Asignado' }}
                  </div>
                </td>

                <!-- Compradores -->
                <td class="px-3 py-1.5 text-center font-mono text-[#1D1D1F] border-r border-black/[0.04]">
                  {{ getCompradores(r) }}
                </td>

                <!-- Pts Esperados -->
                <td class="px-3 py-1.5 text-center font-mono font-semibold text-[#1D1D1F] border-r border-black/[0.04]" title="Suma de pesos f_i esperados">
                  {{ getPuntosEsperados(r) }}
                </td>

                <!-- Pts Match -->
                <td class="px-3 py-1.5 text-center font-mono font-bold text-[#1E6B37] border-r border-black/[0.04]" title="Suma de pesos f_i recomprados">
                  {{ getPuntosMatch(r) }}
                </td>

                <!-- SKUs Match / Base -->
                <td class="px-3 py-1.5 text-center font-mono text-[11px] text-[#6E6E73] border-r border-black/[0.04]">
                  <strong class="text-[#1D1D1F]">{{ getSkusMatch(r) }}</strong> / {{ getSkusEsperados(r) }}
                </td>

                <!-- % Reposición -->
                <td class="px-3 py-1.5 text-center font-mono font-bold border-r border-black/[0.04]">
                  <span
                    v-if="getPctReposicion(r) !== null"
                    :class="getPctReposicion(r) >= 70.0 ? 'text-[#1E6B37]' : (getPctReposicion(r) >= 40.0 ? 'text-[#0071E3]' : 'text-[#C9342C]')"
                  >
                    {{ Number(getPctReposicion(r)).toFixed(1) }}%
                  </span>
                  <span v-else class="text-[#86868B]">—</span>
                </td>

                <!-- Estatus -->
                <td class="px-3 py-2 text-center border-r border-black/[0.04]">
                  <span
                    v-if="getPctReposicion(r) !== null"
                    class="inline-flex px-2 py-0.5 rounded-[5px] text-[10px] font-bold"
                    :class="getPctReposicion(r) >= 70.0
                      ? 'bg-[#EBF9EF] text-[#1E6B37] border border-[#D1F2D9]'
                      : (getPctReposicion(r) >= 40.0
                        ? 'bg-[#EFF6FF] text-[#1D4ED8] border border-[#DBEAFE]'
                        : 'bg-[#FDF0EF] text-[#C9342C] border border-[#FADCD5]')"
                  >
                    {{ getPctReposicion(r) >= 70.0 ? '≥ 70% ÓPTIMO' : (getPctReposicion(r) >= 40.0 ? '40-70% GRADUAL' : '< 40% DÉFICIT') }}
                  </span>
                  <span v-else class="text-[#86868B] text-[11px] font-mono">—</span>
                </td>

                <!-- Puntos (15) -->
                <td class="px-3 py-1.5 text-right font-mono font-bold text-[12px]">
                  <span
                    v-if="getPuntosObtenidos(r) > 0"
                    class="inline-flex items-center justify-center min-w-[32px] px-1.5 py-0.5 rounded-[4px] text-[11px] font-bold font-mono"
                    :class="getPuntosObtenidos(r) >= 15.0
                      ? 'text-[#1E6B37] bg-[#EBF9EF] border border-[#D1F2D9]'
                      : 'text-[#1D4ED8] bg-[#EFF6FF] border border-[#DBEAFE]'"
                  >
                    +{{ getPuntosObtenidos(r).toFixed(1) }}
                  </span>
                  <span v-else class="text-[#86868B] font-mono">0 pts</span>
                </td>
              </tr>

              <!-- ═══════════════════════════════════════════════════════════ -->
              <!-- Fila Acordeón: Auditoría en Tablas Multinivel (Python)     -->
              <!-- ═══════════════════════════════════════════════════════════ -->
              <tr v-if="acordeonAbierto[r.ruta]">
                <td colspan="9" class="p-3 bg-white/70 border-b border-black/[0.06]">
                  <!-- Estado de carga -->
                  <div v-if="cargandoDetalle[r.ruta]" class="p-6 text-center text-[#6E6E73] text-[11px] font-medium space-y-2">
                    <div class="h-4 w-48 mx-auto skeleton"></div>
                    <div>Auditando canastas de reposición para {{ r.ruta }}...</div>
                  </div>

                  <!-- Estado de error -->
                  <div v-else-if="errorDetalle[r.ruta]" class="p-4 text-center text-[#C9342C] text-[11px] font-semibold bg-[#FDF0EF] rounded-[8px] border border-[#FADCD5]">
                    {{ errorDetalle[r.ruta] }}
                  </div>

                  <!-- Detalle auditado en tablas -->
                  <div v-else-if="detallePorRuta[r.ruta]" class="space-y-1.5">
                    <!-- Botones de expansión rápida -->
                    <div v-if="(detallePorRuta[r.ruta].tiendas || []).length > 0" class="flex justify-end items-center gap-1.5 pb-0.5">
                      <button
                        type="button"
                        @click="expandirTodasTiendas(r.ruta, detallePorRuta[r.ruta].tiendas, true)"
                        class="px-2 py-0.5 rounded-[5px] bg-white hover:bg-slate-100 text-[#0071E3] font-sans font-semibold text-[10px] border border-black/[0.08] cursor-pointer"
                      >
                        Expandir Todo
                      </button>
                      <button
                        type="button"
                        @click="expandirTodasTiendas(r.ruta, detallePorRuta[r.ruta].tiendas, false)"
                        class="px-2 py-0.5 rounded-[5px] bg-white hover:bg-slate-100 text-[#86868B] font-sans font-semibold text-[10px] border border-black/[0.08] cursor-pointer"
                      >
                        Colapsar
                      </button>
                    </div>

                    <!-- Sin tiendas -->
                    <div v-if="(detallePorRuta[r.ruta].tiendas || []).length === 0" class="p-6 text-center text-[#86868B] text-[11px] apple-glass-card rounded-[12px]">
                      No se encontraron tiendas compradoras evaluables para reposición en la fecha.
                    </div>

                    <!-- Lista de Tiendas Compradoras (cada una con sus 2 tablas) -->
                    <div v-else class="space-y-1.5">
                      <div
                        v-for="(t, tIdx) in detallePorRuta[r.ruta].tiendas"
                        :key="t.cliente_id"
                        class="apple-glass-card rounded-[9px] py-1.5 px-2.5 border border-black/[0.06] bg-white space-y-1.5 transition-all shadow-xs hover:border-black/[0.12]"
                      >
                        <!-- Cabecera de la Tienda (Nivel 2) -->
                        <div class="flex flex-wrap items-center justify-between gap-1.5 pb-1 border-b border-black/[0.05] text-[11px]">
                          <div class="flex items-center gap-1.5 min-w-0">
                            <button
                              type="button"
                              @click="toggleCliente(r.ruta, t.cliente_id, tIdx)"
                              class="font-bold text-[#1D1D1F] hover:text-[#0071E3] text-[12px] flex items-center gap-1.5 cursor-pointer truncate"
                            >
                              <svg
                                class="w-3.5 h-3.5 text-[#86868B] transition-transform duration-200 shrink-0"
                                :class="isClienteAbierto(r.ruta, t.cliente_id, tIdx) ? 'rotate-90 text-[#0071E3]' : ''"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                              >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                              </svg>
                              <span class="truncate">{{ t.cliente }}</span>
                            </button>
                            <span class="text-[9.5px] text-[#86868B] font-mono px-1.5 py-0.2 rounded bg-[#F5F5F7] border border-[#E5E5EA] shrink-0">
                              #{{ t.codigo_cliente || t.cliente_id }}
                            </span>
                          </div>

                          <div class="flex items-center gap-2.5 text-[10.5px] font-mono flex-wrap">
                            <template v-if="t.es_neutro">
                              <span class="px-2 py-0.5 rounded-[4px] text-[9.5px] font-bold font-mono bg-[#F5F5F7] text-[#6E6E73] border border-[#E5E5EA]">
                                CLIENTE NUEVO (NEUTRO · EXCLUIDO)
                              </span>
                              <span class="text-[#6E6E73]">
                                Pedido Hoy: <strong class="text-[#0071E3] font-bold">Bs {{ formatBs(t.total_bs_hoy) }}</strong>
                              </span>
                            </template>
                            <template v-else>
                              <span class="text-[#6E6E73]">
                                Canasta: <strong class="text-[#1D1D1F]">{{ t.skus_esperados || 0 }} SKUs</strong> ({{ t.puntos_esperados || 0 }} pts f_i)
                              </span>
                              <span class="text-[#6E6E73]">
                                Cant. Repo Sugerida: <strong class="text-[#1D1D1F]">{{ totalCanastaRepo(t.canasta_base) }} uds</strong>
                              </span>
                              <span class="text-[#6E6E73]">
                                Match Ponderado: <strong class="text-[#1E6B37] font-bold">{{ t.puntos_match || 0 }}</strong> / {{ t.puntos_esperados || 0 }} pts
                              </span>
                              <span class="text-[#6E6E73]">
                                Pedido Hoy: <strong class="text-[#0071E3] font-bold">Bs {{ formatBs(t.total_bs_hoy) }}</strong>
                              </span>
                              <span
                                class="px-2 py-0.5 rounded-[4px] text-[9.5px] font-bold font-mono"
                                :class="t.cumple
                                  ? 'bg-[#EBF9EF] text-[#1E6B37] border border-[#D1F2D9]'
                                  : 'bg-[#FDF0EF] text-[#C9342C] border border-[#FADCD5]'"
                              >
                                {{ Number(t.porcentaje_reposicion || 0).toFixed(1) }}% {{ t.cumple ? 'REPOSICIÓN' : 'DÉFICIT' }}
                              </span>
                            </template>
                          </div>
                        </div>

                        <!-- Contenido Desplegable: 2 Tablas -->
                        <div
                          v-if="isClienteAbierto(r.ruta, t.cliente_id, tIdx)"
                          class="grid grid-cols-1 xl:grid-cols-12 gap-2.5 pt-1"
                        >
                          <!-- ═════════════════════════════════════════════════ -->
                          <!-- Panel Izquierdo (7 cols): Canasta Histórica       -->
                          <!-- ═════════════════════════════════════════════════ -->
                          <div class="xl:col-span-7 border border-black/[0.06] rounded-[10px] bg-[#F8F8FA]/70 p-2 space-y-1.5">
                            <div class="overflow-x-auto max-h-80 custom-scroll rounded-[8px] border border-black/[0.06] bg-white">
                              <table class="w-full min-w-[460px] text-left text-[11px] border-collapse font-num">
                                <thead class="bg-[#F8F8FA] text-[#6E6E73] text-[9px] font-bold uppercase tracking-tight border-b border-black/[0.06] sticky top-0 z-10 select-none">
                                  <tr>
                                    <th class="py-1.5 px-2.5">Producto (Historial del Cliente)</th>
                                    <th class="py-1.5 px-2 text-center" :title="`Cantidad total comprada por este cliente en las últimas ${semanasFiltro} semanas`">
                                      Cant. Comprada
                                    </th>
                                    <th class="py-1.5 px-2 text-center" title="Peso histórico f_i = semanas distintas con compra de este SKU">
                                      Peso f_i (Sem)
                                    </th>
                                    <th class="py-1.5 px-2 text-center" :title="`Cantidad calculada de reposición (Cant. Comprada / ${semanasFiltro} sem, mín 1)`">
                                      Cantidad Repo
                                    </th>
                                    <th class="py-1.5 px-2 text-center w-14">Estatus</th>
                                  </tr>
                                </thead>

                                <tbody class="divide-y divide-black/[0.04]">
                                  <tr v-if="(t.canasta_base || []).length === 0">
                                    <td colspan="5" class="py-4 text-center text-[#86868B] text-[10px] italic">
                                      Cliente sin historial de compras en las últimas {{ semanasFiltro }} semanas (Apertura de cliente).
                                    </td>
                                  </tr>

                                  <template v-else v-for="(item, sIdx) in t.canasta_base" :key="item.codigo_producto || item.producto || sIdx">
                                    <!-- Fila Principal del SKU -->
                                    <tr class="hover:bg-blue-50/20 transition-colors">
                                      <td class="py-2 px-2.5 font-bold text-[#1D1D1F]">
                                        <div class="flex items-center gap-1.5">
                                          <!-- Botón de expandir transacciones -->
                                          <button
                                            type="button"
                                            @click="toggleSkuDetalle(r.ruta, t.cliente_id, item.codigo_producto || item.producto || sIdx)"
                                            class="p-0.5 hover:bg-slate-200 rounded cursor-pointer text-[#86868B] hover:text-[#1D1D1F] transition-colors shrink-0"
                                            title="Ver fechas de compra de este SKU"
                                          >
                                            <svg
                                              class="w-3.5 h-3.5 transition-transform duration-200"
                                              :class="isSkuDetalleAbierto(r.ruta, t.cliente_id, item.codigo_producto || item.producto || sIdx) ? 'rotate-90 text-[#0071E3]' : ''"
                                              fill="none"
                                              stroke="currentColor"
                                              viewBox="0 0 24 24"
                                            >
                                              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7" />
                                            </svg>
                                          </button>
                                          <span class="truncate max-w-[210px]" :title="item.producto">{{ item.producto || '—' }}</span>
                                        </div>
                                      </td>

                                      <!-- Cantidad total comprada -->
                                      <td class="py-2 px-2 text-center font-bold text-[#1D1D1F] bg-black/[0.01]">
                                        <span class="px-1.5 py-0.5 rounded bg-[#F8F8FA] border border-black/[0.06]">
                                          {{ item.unidades_acumuladas || 0 }} uds
                                        </span>
                                      </td>

                                      <!-- Peso f_i (Semanas con compra) -->
                                      <td class="py-2 px-2 text-center font-bold text-[#0071E3] bg-black/[0.01]">
                                        <span class="px-1.5 py-0.5 rounded bg-[#EBF5FF] border border-[#B3D7FF] font-mono text-[10px]">
                                          {{ item.peso_fi || item.semanas_con_compra || 1 }} pts
                                        </span>
                                      </td>

                                      <!-- Cantidad Repo Sugerida -->
                                      <td class="py-2 px-2 text-center font-bold text-[#1E6B37] bg-black/[0.01]">
                                        <span
                                          class="px-1.5 py-0.5 rounded bg-[#EBF9EF] border border-[#D1F2D9]"
                                          :title="`Sugerido semanal: ${item.unidades_acumuladas || 0} uds / ${semanasFiltro} sem (mín 1)`"
                                        >
                                          {{ item.cantidad_repo || 1 }} uds
                                        </span>
                                      </td>

                                      <!-- Estatus (Check o X) -->
                                      <td class="py-2 px-2 text-center font-bold">
                                        <span
                                          class="text-[14px] font-black leading-none"
                                          :class="item.recomprado_hoy ? 'text-[#1E6B37]' : 'text-[#C9342C]'"
                                          :title="item.recomprado_hoy ? 'Recomprado hoy' : 'No recomprado hoy'"
                                        >
                                          {{ item.recomprado_hoy ? '✓' : '✕' }}
                                        </span>
                                      </td>
                                    </tr>

                                    <!-- Histórico Cronológico de Transacciones (Sub-fila inline) -->
                                    <tr
                                      v-if="isSkuDetalleAbierto(r.ruta, t.cliente_id, item.codigo_producto || item.producto || sIdx)"
                                      class="bg-[#F8F9FA] border-b border-black/[0.06]"
                                    >
                                      <td colspan="5" class="p-2 pl-6">
                                        <div class="bg-white rounded-[8px] border border-black/[0.08] p-2 max-w-xl space-y-1 shadow-2xs">
                                          <div v-if="(item.historial_compras || []).length === 0" class="py-2 text-center text-[#86868B] text-[9px] italic">
                                            Sin detalle desglosado de fechas para este SKU.
                                          </div>

                                          <div v-else class="max-h-36 overflow-y-auto custom-scroll">
                                            <table class="w-full text-left font-num text-[9px] border-collapse">
                                              <thead class="bg-[#F8F8FA] text-[#6E6E73] uppercase border-b border-black/[0.06] sticky top-0">
                                                <tr>
                                                  <th class="py-1 px-2 w-8 text-center">#</th>
                                                  <th class="py-1 px-2">Fecha</th>
                                                  <th class="py-1 px-2 text-center">Cantidad</th>
                                                  <th class="py-1 px-2 text-right">Monto Facturado</th>
                                                </tr>
                                              </thead>
                                              <tbody class="divide-y divide-black/[0.04]">
                                                <tr
                                                  v-for="(tx, txIdx) in item.historial_compras"
                                                  :key="`${tx.fecha}-${txIdx}`"
                                                  class="hover:bg-slate-50"
                                                >
                                                  <td class="py-1 px-2 text-center text-[#86868B]">{{ txIdx + 1 }}</td>
                                                  <td class="py-1 px-2 font-mono font-semibold text-[#1D1D1F]">{{ tx.fecha }}</td>
                                                  <td class="py-1 px-2 text-center font-bold text-[#1D1D1F]">{{ tx.unidades }} uds</td>
                                                  <td class="py-1 px-2 text-right font-bold text-[#1D1D1F]">Bs {{ formatBs(tx.monto_bs) }}</td>
                                                </tr>
                                              </tbody>
                                              <tfoot class="bg-[#F8F8FA] border-t-2 border-black/[0.08] font-bold text-[#1D1D1F] sticky bottom-0">
                                                <tr>
                                                  <td colspan="2" class="py-1 px-2 text-left uppercase text-[8.5px]">Total</td>
                                                  <td class="py-1 px-2 text-center font-bold">{{ item.unidades_acumuladas || totalTxUnidades(item.historial_compras) }} uds</td>
                                                  <td class="py-1 px-2 text-right font-bold text-[#1D1D1F]">Bs {{ formatBs(item.monto_acumulado_bs || totalTxMonto(item.historial_compras)) }}</td>
                                                </tr>
                                              </tfoot>
                                            </table>
                                          </div>
                                        </div>
                                      </td>
                                    </tr>
                                  </template>
                                </tbody>

                                <!-- Fila de Totales Siempre Visible -->
                                <tfoot v-if="(t.canasta_base || []).length > 0" class="bg-[#F8F8FA] border-t-2 border-black/[0.1] text-[#1D1D1F] font-bold text-[10px] sticky bottom-0 z-10">
                                  <tr>
                                    <td class="py-1.5 px-2.5 uppercase font-bold text-[#1D1D1F]">
                                      Total ({{ t.canasta_base.length }} SKUs)
                                    </td>
                                    <td class="py-1.5 px-2 text-center font-bold">
                                      {{ totalCanastaUnidades(t.canasta_base) }} uds
                                    </td>
                                    <td class="py-1.5 px-2 text-center font-bold text-[#0071E3]">
                                      {{ t.puntos_esperados || totalCanastaFi(t.canasta_base) }} pts
                                    </td>
                                    <td class="py-1.5 px-2 text-center font-bold text-[#1E6B37]">
                                      {{ totalCanastaRepo(t.canasta_base) }} uds
                                    </td>
                                    <td class="py-1.5 px-2 text-center font-bold font-mono text-[10px]" :class="(t.skus_con_match || 0) > 0 ? 'text-[#1E6B37]' : 'text-[#86868B]'">
                                      {{ t.skus_con_match || totalCanastaMatches(t.canasta_base) }}/{{ t.canasta_base.length }}
                                    </td>
                                  </tr>
                                </tfoot>
                              </table>
                            </div>
                          </div>

                          <!-- ═════════════════════════════════════════════════ -->
                          <!-- Panel Derecho (5 cols): Pedido Real Facturado Hoy -->
                          <!-- ═════════════════════════════════════════════════ -->
                          <div class="xl:col-span-5 border border-black/[0.06] rounded-[10px] bg-white p-2 space-y-1.5">
                            <div class="overflow-x-auto max-h-80 custom-scroll rounded-[8px] border border-black/[0.06]">
                              <table class="w-full min-w-[380px] text-left text-[11px] border-collapse font-num">
                                <thead class="bg-[#F8F8FA] text-[#6E6E73] text-[9px] font-bold uppercase tracking-tight border-b border-black/[0.06] sticky top-0 z-10 select-none">
                                  <tr>
                                    <th class="py-1.5 px-2">Producto Pedido</th>
                                    <th class="py-1.5 px-2 text-center">Cantidad</th>
                                    <th class="py-1.5 px-2 text-right">Monto Bs</th>
                                    <th class="py-1.5 px-2 text-center">Clasificación</th>
                                  </tr>
                                </thead>

                                <tbody class="divide-y divide-black/[0.04]">
                                  <tr v-if="(t.pedidos_hoy || []).length === 0">
                                    <td colspan="4" class="py-4 text-center text-[#86868B] text-[10px] italic">
                                      Sin compras facturadas hoy.
                                    </td>
                                  </tr>

                                  <tr
                                    v-else
                                    v-for="(item, pIdx) in t.pedidos_hoy"
                                    :key="`ped-${item.codigo_producto || item.producto}-${pIdx}`"
                                    class="hover:bg-slate-50 transition-colors"
                                    :class="item.estatus === 'REPOSICION_SKU' ? 'bg-[#EBF9EF]/10' : ''"
                                  >
                                    <td class="py-2 px-2 font-bold text-[#1D1D1F] truncate max-w-[170px]" :title="item.producto">
                                      {{ item.producto || '—' }}
                                    </td>

                                    <td class="py-2 px-2 text-center font-bold text-[#1D1D1F]">
                                      {{ item.unidades || item.unidades_hoy || 0 }} uds
                                    </td>

                                    <td class="py-2 px-2 text-right font-bold text-[#1D1D1F]">
                                      Bs {{ formatBs(item.monto_bs || item.monto_hoy) }}
                                    </td>

                                    <td class="py-2 px-2 text-center">
                                      <span
                                        class="inline-block px-1.5 py-0.5 rounded text-[9px] font-bold"
                                        :class="item.estatus === 'REPOSICION_SKU'
                                          ? 'bg-[#EBF9EF] text-[#1E6B37] border border-[#D1F2D9]'
                                          : 'bg-[#EFF6FF] text-[#1D4ED8] border border-[#DBEAFE]'"
                                      >
                                        {{ item.estatus === 'REPOSICION_SKU' ? '✓ REPOSICIÓN' : 'AMPLIACIÓN' }}
                                      </span>
                                    </td>
                                  </tr>
                                </tbody>

                                <!-- Fila de Totales Siempre Visible -->
                                <tfoot v-if="(t.pedidos_hoy || []).length > 0" class="bg-[#F8F8FA] border-t-2 border-black/[0.1] text-[#1D1D1F] font-bold text-[10px] sticky bottom-0 z-10">
                                  <tr>
                                    <td class="py-1.5 px-2 uppercase font-bold text-[#1D1D1F]">
                                      Total ({{ t.pedidos_hoy.length }} SKUs)
                                    </td>
                                    <td class="py-1.5 px-2 text-center font-bold">
                                      {{ totalPedidosHoyUnidades(t.pedidos_hoy) }} uds
                                    </td>
                                    <td class="py-1.5 px-2 text-right font-bold text-[#1D1D1F]">
                                      Bs {{ formatBs(totalPedidosHoyBs(t.pedidos_hoy)) }}
                                    </td>
                                    <td></td>
                                  </tr>
                                </tfoot>
                              </table>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </td>
              </tr>
            </template>
          </template>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import axios from 'axios'
import { usePreventaStore } from '@/Stores/preventaStore'

const store = usePreventaStore()

const semanasFiltro = ref(13)

const rutasOrdenadas = computed(() => {
  return [...(store.rutasFiltradas || [])]
})

const acordeonAbierto = reactive({})
const cargandoDetalle = reactive({})
const errorDetalle = reactive({})
const detallePorRuta = reactive({})

// Control de acordeón por tienda y por SKU (Nivel 4)
const clientesAbiertos = reactive({})
const skusDetalleAbiertos = reactive({})

watch(
  () => store.fecha,
  () => {
    Object.keys(acordeonAbierto).forEach(k => delete acordeonAbierto[k])
    Object.keys(detallePorRuta).forEach(k => delete detallePorRuta[k])
    Object.keys(clientesAbiertos).forEach(k => delete clientesAbiertos[k])
    Object.keys(skusDetalleAbiertos).forEach(k => delete skusDetalleAbiertos[k])
  }
)

function formatBs(val) {
  const n = Number(val || 0)
  return n.toLocaleString('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function getCompradores(r) {
  return r.kpi6_reposicion?.compradores
    ?? r.kpi6_reposicion?.evaluables
    ?? detallePorRuta[r.ruta]?.totales?.compradores_hoy
    ?? r.kpis?.pedido_reposicion?.metricas?.total_compradores
    ?? '—'
}

function getPuntosEsperados(r) {
  return r.kpi6_reposicion?.puntos_esperados
    ?? detallePorRuta[r.ruta]?.totales?.puntos_esperados_total
    ?? '—'
}

function getPuntosMatch(r) {
  return r.kpi6_reposicion?.puntos_match
    ?? detallePorRuta[r.ruta]?.totales?.puntos_match_total
    ?? '—'
}

function getSkusEsperados(r) {
  return r.kpi6_reposicion?.skus_esperados
    ?? detallePorRuta[r.ruta]?.totales?.skus_esperados_total
    ?? '—'
}

function getSkusMatch(r) {
  return r.kpi6_reposicion?.skus_match
    ?? detallePorRuta[r.ruta]?.totales?.skus_con_match_total
    ?? '—'
}

function getSkusFuera(r) {
  return r.kpi6_reposicion?.skus_fuera_canasta
    ?? detallePorRuta[r.ruta]?.totales?.skus_fuera_canasta_total
    ?? '—'
}

function getPctReposicion(r) {
  if (r.kpi6_reposicion?.tasa_pct !== undefined && r.kpi6_reposicion?.tasa_pct !== null) {
    return Number(r.kpi6_reposicion.tasa_pct)
  }
  if (detallePorRuta[r.ruta]?.totales?.porcentaje_reposicion_ruta !== undefined) {
    return Number(detallePorRuta[r.ruta].totales.porcentaje_reposicion_ruta)
  }
  if (r.kpis?.pedido_reposicion?.metricas?.promedio_reposicion_pct !== undefined) {
    return Number(r.kpis.pedido_reposicion.metricas.promedio_reposicion_pct)
  }
  return null
}

function getCumpleKpi(r) {
  if (r.kpi6_reposicion?.cumple !== undefined) {
    return Boolean(r.kpi6_reposicion.cumple)
  }
  const pct = getPctReposicion(r)
  return pct !== null && pct >= 40.0
}

function getPuntosObtenidos(r) {
  if (r.kpi6_reposicion?.puntos !== undefined) {
    return Number(r.kpi6_reposicion.puntos)
  }
  if (detallePorRuta[r.ruta]?.totales?.puntos_obtenidos !== undefined) {
    return Number(detallePorRuta[r.ruta].totales.puntos_obtenidos)
  }
  const pct = getPctReposicion(r)
  if (pct === null || pct < 40.0) return 0.0
  if (pct >= 70.0) return 15.0
  return Number((15.0 * ((pct - 40.0) / 30.0)).toFixed(2))
}

// ── Helpers de Totales para Tablas ────────────────────────────────────────
function totalCanastaUnidades(canasta) {
  return (canasta || []).reduce((acc, it) => acc + (Number(it.unidades_acumuladas) || 0), 0)
}

function totalCanastaFi(canasta) {
  return (canasta || []).reduce((acc, it) => acc + (Number(it.peso_fi) || 0), 0)
}

function totalCanastaRepo(canasta) {
  return (canasta || []).reduce((acc, it) => acc + (Number(it.cantidad_repo) || 0), 0)
}

function totalCanastaMatches(canasta) {
  return (canasta || []).filter(it => it.recomprado_hoy).length
}

function totalPedidosHoyUnidades(pedidos) {
  return (pedidos || []).reduce((acc, it) => acc + (Number(it.unidades || it.unidades_hoy) || 0), 0)
}

function totalPedidosHoyBs(pedidos) {
  return (pedidos || []).reduce((acc, it) => acc + (Number(it.monto_bs || it.monto_hoy) || 0), 0)
}

function totalTxUnidades(txs) {
  return (txs || []).reduce((acc, tx) => acc + (Number(tx.unidades) || 0), 0)
}

function totalTxMonto(txs) {
  return (txs || []).reduce((acc, tx) => acc + (Number(tx.monto_bs) || 0), 0)
}

// ── Control de expansión de clientes ──────────────────────────────────────
function isClienteAbierto(ruta, clienteId, idx) {
  const key = `${ruta}-${clienteId}-${idx}`
  return Boolean(clientesAbiertos[key])
}

function toggleCliente(ruta, clienteId, idx) {
  const key = `${ruta}-${clienteId}-${idx}`
  clientesAbiertos[key] = !isClienteAbierto(ruta, clienteId, idx)
}

function expandirTodasTiendas(ruta, tiendas, abrir = true) {
  (tiendas || []).forEach((t, idx) => {
    const key = `${ruta}-${t.cliente_id}-${idx}`
    clientesAbiertos[key] = abrir
  })
}

// ── Control de expansión de transacciones Nivel 4 ─────────────────────────
function isSkuDetalleAbierto(ruta, clienteId, skuKey) {
  const key = `${ruta}-${clienteId}-${skuKey}`
  return Boolean(skusDetalleAbiertos[key])
}

function toggleSkuDetalle(ruta, clienteId, skuKey) {
  const key = `${ruta}-${clienteId}-${skuKey}`
  skusDetalleAbiertos[key] = !skusDetalleAbiertos[key]
}

// ── Carga y Acordeón de Ruta ─────────────────────────────────────────────
async function cargarDetalleRuta(ruta, force = false) {
  if (!force && detallePorRuta[ruta]) return

  cargandoDetalle[ruta] = true
  errorDetalle[ruta] = null
  try {
    const res = await axios.post('/supervisor/preventas/cumplimiento/reposicion/detalle', {
      fecha: store.fecha,
      ruta,
      semanas_historia: semanasFiltro.value || 13
    })
    detallePorRuta[ruta] = res.data
  } catch (e) {
    errorDetalle[ruta] = e?.response?.data?.message || e.message || 'Error al cargar auditoría de reposición'
  } finally {
    cargandoDetalle[ruta] = false
  }
}

async function toggleReposicionAcordeon(r) {
  const ruta = r.ruta
  if (acordeonAbierto[ruta]) {
    acordeonAbierto[ruta] = false
    return
  }
  acordeonAbierto[ruta] = true
  await cargarDetalleRuta(ruta)
}

function recalcularReposicion() {
  const rutasAbiertas = Object.keys(acordeonAbierto).filter(k => acordeonAbierto[k])
  rutasAbiertas.forEach(ruta => {
    cargarDetalleRuta(ruta, true)
  })
}
</script>
