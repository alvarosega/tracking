<template>
  <div class="apple-glass-card rounded-[16px] overflow-hidden select-none font-sans">
    <!-- Encabezado Compacto -->
    <div class="px-3.5 py-2.5 border-b border-black/[0.06] flex flex-wrap items-center justify-between gap-2 bg-white/40">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <h3 class="text-[13px] font-bold text-[#1D1D1F] tracking-tight leading-tight">
            Pizarra Modelo Unilever · KPI 1: Horario de Ingreso y Primer Contacto
          </h3>
          <span class="px-2 py-0.5 rounded-[5px] text-[10px] font-bold text-[#1E6B37] bg-[#EBF9EF] border border-[#D1F2D9]">
            10 pts Base · Criterio Binario
          </span>
        </div>
        <p class="text-[11px] text-[#6E6E73] font-medium mt-0.5">
          Regla: Primer contacto menor o igual a 08:10:00 otorga 10 puntos (Todo o Nada). Haz clic en una ruta para ver paradas y fotos de campo.
        </p>
      </div>
      <div class="flex items-center gap-2 text-[11px]">
        <span class="apple-control-pill px-2.5 py-1 rounded-[8px] font-semibold text-[#6E6E73]">
          Orden: <strong class="text-[#0071E3] font-bold">{{ sortLabels[sortCol] || sortCol }}</strong>
          <span class="text-[#86868B] ml-1">({{ sortDir === 'desc' ? 'Mayor a menor' : 'Menor a mayor' }})</span>
        </span>
        <span class="apple-control-pill px-2.5 py-1 rounded-[8px] font-semibold text-[#6E6E73]">
          Rutas Evaluadas:
          <strong class="text-[#1D1D1F] font-mono ml-0.5">{{ store.loadingRanking ? '…' : rutasOrdenadas.length }}</strong>
        </span>
      </div>
    </div>

    <!-- Tabla Principal -->
    <div class="overflow-x-auto custom-scroll sm:max-h-[calc(100vh-230px)] min-h-[380px]">
      <table class="w-full text-left border-collapse min-w-[600px] sm:min-w-[780px] text-[12px]">
        <thead class="sticky top-0 z-20 border-b border-black/[0.06] text-[10px] font-bold uppercase tracking-wider text-[#6E6E73] bg-[#F8F8FA]">
          <tr>
            <th
              @click="toggleSort('pos')"
              class="px-1 sm:px-2 py-2 text-center sticky left-0 z-30 w-7 sm:w-9 border-r border-black/[0.05] bg-[#F8F8FA] text-[10px] sm:text-[11px] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
              :class="sortCol === 'pos' ? 'bg-blue-50/70 text-[#0071E3]' : ''"
              title="Ordenar por Posición"
            >
              <div class="flex items-center justify-center gap-0.5">
                <span>Pos</span>
                <span v-if="sortCol === 'pos'" class="font-bold text-[9px]">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
              </div>
            </th>
            <th
              class="px-1.5 sm:px-2.5 py-2 sticky left-7 sm:left-9 z-30 w-24 sm:w-44 md:w-52 border-r border-black/[0.06] shadow-[2px_0_4px_-2px_rgba(0,0,0,0.05)] bg-[#F8F8FA] select-none text-[10px] sm:text-[11px]"
            >
              <div class="flex items-center gap-1">
                <span
                  @click="toggleSort('ruta')"
                  class="cursor-pointer hover:text-[#0071E3] transition-colors flex items-center gap-0.5"
                  :class="sortCol === 'ruta' ? 'text-[#0071E3] font-bold' : ''"
                  title="Ordenar por Ruta"
                >
                  <span>Ruta</span>
                  <span v-if="sortCol === 'ruta'" class="font-bold text-[9px]">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
                </span>
                <span class="text-[#D2D2D7]">/</span>
                <span
                  @click="toggleSort('vendedor')"
                  class="cursor-pointer hover:text-[#0071E3] transition-colors flex items-center gap-0.5 truncate"
                  :class="sortCol === 'vendedor' ? 'text-[#0071E3] font-bold' : ''"
                  title="Ordenar por Preventista"
                >
                  <span class="truncate">Preventista</span>
                  <span v-if="sortCol === 'vendedor'" class="font-bold text-[9px]">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
                </span>
              </div>
            </th>
            <th
              @click="toggleSort('hora')"
              class="px-3 py-2 text-center border-r border-black/[0.04] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
              :class="sortCol === 'hora' ? 'bg-blue-50/70 text-[#0071E3]' : ''"
              title="Ordenar por Primer Contacto"
            >
              <div class="flex items-center justify-center gap-1">
                <span>Primer Contacto</span>
                <span v-if="sortCol === 'hora'" class="font-bold text-[9px]">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
              </div>
            </th>
            <th
              @click="toggleSort('hora_web')"
              class="px-3 py-2 text-center border-r border-black/[0.04] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
              :class="sortCol === 'hora_web' ? 'bg-blue-50/70 text-[#0071E3]' : ''"
              title="Ordenar por Check-in Web"
            >
              <div class="flex items-center justify-center gap-1">
                <span>Check-in Web</span>
                <span v-if="sortCol === 'hora_web'" class="font-bold text-[9px]">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
              </div>
            </th>
            <th
              @click="toggleSort('hora_app')"
              class="px-3 py-2 text-center border-r border-black/[0.04] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
              :class="sortCol === 'hora_app' ? 'bg-blue-50/70 text-[#0071E3]' : ''"
              title="Ordenar por Check-in App"
            >
              <div class="flex items-center justify-center gap-1">
                <span>Check-in App</span>
                <span v-if="sortCol === 'hora_app'" class="font-bold text-[9px]">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
              </div>
            </th>
            <th
              @click="toggleSort('estatus')"
              class="px-3 py-2 text-center border-r border-black/[0.04] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
              :class="sortCol === 'estatus' ? 'bg-blue-50/70 text-[#0071E3]' : ''"
              title="Ordenar por Estatus"
            >
              <div class="flex items-center justify-center gap-1">
                <span>Estatus</span>
                <span v-if="sortCol === 'estatus'" class="font-bold text-[9px]">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
              </div>
            </th>
            <th
              @click="toggleSort('puntos')"
              class="px-3 py-2 text-right cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
              :class="sortCol === 'puntos' ? 'bg-blue-50/70 text-[#0071E3]' : ''"
              title="Ordenar por Puntos (10)"
            >
              <div class="flex items-center justify-end gap-1">
                <span>Puntos (10)</span>
                <span v-if="sortCol === 'puntos'" class="font-bold text-[9px]">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
              </div>
            </th>
          </tr>
        </thead>

        <tbody class="divide-y divide-black/[0.04]">
          <template v-if="store.loadingRanking">
            <tr v-for="i in 10" :key="`skel-hor-${i}`">
              <td class="px-1 sm:px-2 py-2 sticky left-0 w-7 sm:w-9"><div class="h-3.5 w-4 mx-auto skeleton"></div></td>
              <td class="px-1.5 sm:px-2.5 py-2 sticky left-7 sm:left-9 w-24 sm:w-44 md:w-52 shadow-[2px_0_4px_-2px_rgba(0,0,0,0.05)]">
                <div class="h-3.5 w-12 sm:w-16 skeleton mb-1"></div>
                <div class="h-3 w-16 sm:w-28 skeleton"></div>
              </td>
              <td class="px-3 py-2 text-center"><div class="h-4 w-16 mx-auto skeleton"></div></td>
              <td class="px-3 py-2 text-center"><div class="h-4 w-14 mx-auto skeleton"></div></td>
              <td class="px-3 py-2 text-center"><div class="h-4 w-14 mx-auto skeleton"></div></td>
              <td class="px-3 py-2 text-center"><div class="h-4 w-16 mx-auto skeleton"></div></td>
              <td class="px-3 py-2 text-right"><div class="h-4 w-8 ml-auto skeleton"></div></td>
            </tr>
          </template>

          <tr v-else-if="rutasOrdenadas.length === 0">
            <td colspan="7" class="p-8 text-center text-[#86868B] text-[12px] font-medium">
              No se encontraron rutas para los filtros seleccionados.
            </td>
          </tr>

          <template v-else>
            <template v-for="(r, idx) in rutasOrdenadas" :key="r.ruta || idx">
              <tr
                @click="toggleRecorridoAcordeon(r)"
                class="cursor-pointer transition-colors"
                :class="acordeonAbierto[r.ruta] ? 'bg-blue-50/40' : 'hover:bg-black/[0.02]'"
              >
                <!-- Pos -->
                <td class="px-1 sm:px-2 py-1.5 text-center font-mono text-[10px] sm:text-[11px] font-semibold text-[#86868B] sticky left-0 z-10 w-7 sm:w-9 border-r border-black/[0.04] bg-white/80 backdrop-blur-sm">
                  {{ r.rank ?? (idx + 1) }}
                </td>

                <!-- Ruta / Preventista -->
                <td
                  class="px-1.5 sm:px-2.5 py-1.5 sticky left-7 sm:left-9 z-10 w-24 sm:w-44 md:w-52 border-r border-black/[0.06] shadow-[2px_0_4px_-2px_rgba(0,0,0,0.05)] bg-white/80 backdrop-blur-sm truncate"
                  :title="`${r.ruta} - ${r.vendedor || 'No Asignado'}`"
                >
                  <div class="flex items-center gap-1 min-w-0">
                    <svg
                      class="w-3 h-3 text-[#86868B] transition-transform duration-200 shrink-0"
                      :class="acordeonAbierto[r.ruta] ? 'rotate-90 text-[#0071E3]' : ''"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                    <span class="font-bold text-[#1D1D1F] text-[11px] sm:text-[12px] leading-tight truncate">{{ r.ruta }}</span>
                  </div>
                  <div class="text-[9.5px] sm:text-[11px] text-[#6E6E73] font-medium leading-tight truncate pl-4">
                    {{ r.vendedor || 'No Asignado' }}
                  </div>
                </td>

                <!-- Primer Contacto -->
                <td class="px-3 py-2 text-center font-mono font-bold text-[11px] border-r border-black/[0.04]">
                  <span
                    v-if="r.kpis?.horario_ingreso?.metricas?.hora_ingreso_detectada"
                    :class="r.kpis?.horario_ingreso?.cumple ? 'text-[#1E6B37]' : 'text-[#C9342C]'"
                  >
                    {{ r.kpis.horario_ingreso.metricas.hora_ingreso_detectada }}
                  </span>
                  <span v-else class="text-[#86868B]">—</span>
                </td>

                <!-- Hora Web -->
                <td class="px-3 py-2 text-center font-mono text-[11px] text-[#6E6E73] border-r border-black/[0.04]">
                  {{ r.kpis?.horario_ingreso?.metricas?.hora_web || '—' }}
                </td>

                <!-- Hora App -->
                <td class="px-3 py-2 text-center font-mono text-[11px] text-[#6E6E73] border-r border-black/[0.04]">
                  {{ r.kpis?.horario_ingreso?.metricas?.hora_app || '—' }}
                </td>

                <!-- Estatus -->
                <td class="px-3 py-2 text-center border-r border-black/[0.04]">
                  <span
                    v-if="r.kpis?.horario_ingreso?.cumple"
                    class="inline-flex items-center px-2 py-0.5 rounded-[5px] text-[10px] font-bold bg-[#EBF9EF] text-[#1E6B37] border border-[#D1F2D9]"
                  >A TIEMPO</span>
                  <span
                    v-else-if="r.kpis?.horario_ingreso?.metricas?.hora_ingreso_detectada"
                    class="inline-flex items-center px-2 py-0.5 rounded-[5px] text-[10px] font-bold bg-[#FDF0EF] text-[#C9342C] border border-[#FADCD5]"
                  >DEMORADO</span>
                  <span
                    v-else
                    class="inline-flex items-center px-2 py-0.5 rounded-[5px] text-[10px] font-bold bg-neutral-100 text-neutral-600 border border-neutral-300"
                  >SIN MARCAJE</span>
                </td>

                <!-- Puntos (10) -->
                <td class="px-3 py-2 text-right font-mono font-bold text-[12px]">
                  <span
                    v-if="r.kpis?.horario_ingreso?.cumple"
                    class="inline-flex items-center justify-center min-w-[28px] px-1.5 py-0.5 rounded-[5px] text-[11px] font-bold text-[#1E6B37] bg-[#EBF9EF] border border-[#D1F2D9]"
                  >10</span>
                  <span v-else class="text-[#86868B] text-[11px]">—</span>
                </td>
              </tr>

              <!-- Fila Acordeón: Recorrido Cronológico de Paradas -->
              <tr v-if="acordeonAbierto[r.ruta]">
                <td colspan="7" class="p-3 bg-white/60 border-b border-black/[0.06]">
                  <div v-if="cargandoDetalle[r.ruta]" class="p-4 text-center text-[#6E6E73] text-[11px] font-medium">
                    Cargando cronología de paradas para la ruta {{ r.ruta }}...
                  </div>
                  <div v-else-if="errorDetalle[r.ruta]" class="p-4 text-center text-[#C9342C] text-[11px] font-semibold">
                    {{ errorDetalle[r.ruta] }}
                  </div>
                  <div v-else-if="detallePorRuta[r.ruta]" class="apple-glass-card rounded-[12px] overflow-hidden">
                    <!-- Barra de Resumen y Métricas de pan_ruteo -->
                    <div class="px-3 py-2.5 border-b border-black/[0.06] flex flex-wrap items-center justify-between gap-2 bg-white/60 text-[11px]">
                      <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-bold text-[#1D1D1F] text-[12px]">
                          Auditoría de Ruta: {{ r.ruta }} ({{ r.vendedor }})
                        </span>
                        <span class="text-[10px] font-mono text-[#6E6E73] bg-black/[0.04] px-2 py-0.5 rounded-[6px]">
                          {{ detallePorRuta[r.ruta].dia_semana || 'Plan del Día' }} · {{ detallePorRuta[r.ruta].fecha }}
                        </span>
                      </div>

                      <!-- Métricas Clave de pan_ruteo vs Visitas -->
                      <div class="flex items-center gap-1.5 flex-wrap text-[10px]">
                        <!-- Plan del Día (pan_ruteo) -->
                        <span class="apple-control-pill px-2.5 py-0.5 rounded-[6px] text-[#1D1D1F] font-semibold" title="Clientes programados para hoy en pan_ruteo">
                          Plan del Día: <strong class="font-mono text-[#0071E3] font-bold">{{ detallePorRuta[r.ruta].totales?.plan_dia ?? 0 }}</strong>
                        </span>

                        <!-- Visitados del Plan -->
                        <span class="apple-control-pill px-2.5 py-0.5 rounded-[6px] text-[#1E6B37] bg-[#EBF9EF] border border-[#D1F2D9] font-bold" title="Clientes programados que sí fueron visitados">
                          Visitados: <strong class="font-mono">{{ detallePorRuta[r.ruta].totales?.visitados_del_plan ?? 0 }}</strong>
                          <span class="font-medium text-[9px] ml-0.5">({{ detallePorRuta[r.ruta].totales?.cobertura_pct ?? 0 }}%)</span>
                        </span>

                        <!-- No Visitados / Pendientes -->
                        <span
                          class="apple-control-pill px-2.5 py-0.5 rounded-[6px] font-bold"
                          :class="(detallePorRuta[r.ruta].totales?.no_visitados ?? 0) > 0 ? 'text-[#C9342C] bg-[#FDF0EF] border border-[#FADCD5]' : 'text-[#6E6E73]'"
                          title="Clientes de pan_ruteo que no registran visita hoy"
                        >
                          Pendientes: <strong class="font-mono">{{ detallePorRuta[r.ruta].totales?.no_visitados ?? 0 }}</strong>
                        </span>

                        <!-- Fuera de Plan -->
                        <span
                          v-if="(detallePorRuta[r.ruta].totales?.fuera_plan ?? 0) > 0"
                          class="apple-control-pill px-2.5 py-0.5 rounded-[6px] text-[#86868B] font-medium"
                          title="Visitas realizadas a clientes no agendados hoy"
                        >
                          Fuera Plan: <strong class="font-mono font-bold text-[#1D1D1F]">{{ detallePorRuta[r.ruta].totales?.fuera_plan }}</strong>
                        </span>
                      </div>
                    </div>

                    <!-- Barra de Filtros Segmentados de la Subtabla -->
                    <div class="px-3 py-1.5 border-b border-black/[0.04] bg-[#F8F8FA] flex items-center justify-between gap-2 flex-wrap text-[10px]">
                      <div class="flex items-center gap-1">
                        <button
                          type="button"
                          @click.stop="setSubtablaFiltro(r.ruta, 'todos')"
                          class="px-2 py-0.5 rounded-[6px] transition-colors font-semibold cursor-pointer"
                          :class="getSubtablaFiltro(r.ruta) === 'todos' ? 'bg-[#0071E3] text-white shadow-xs' : 'text-[#6E6E73] hover:bg-black/[0.05]'"
                        >
                          Todos ({{ detallePorRuta[r.ruta].total_eventos || 0 }})
                        </button>
                        <button
                          type="button"
                          @click.stop="setSubtablaFiltro(r.ruta, 'visitados')"
                          class="px-2 py-0.5 rounded-[6px] transition-colors font-semibold cursor-pointer"
                          :class="getSubtablaFiltro(r.ruta) === 'visitados' ? 'bg-[#1E6B37] text-white shadow-xs' : 'text-[#6E6E73] hover:bg-black/[0.05]'"
                        >
                          Visitados ({{ detallePorRuta[r.ruta].totales?.visitados ?? 0 }})
                        </button>
                        <button
                          type="button"
                          @click.stop="setSubtablaFiltro(r.ruta, 'pendientes')"
                          class="px-2 py-0.5 rounded-[6px] transition-colors font-semibold cursor-pointer"
                          :class="getSubtablaFiltro(r.ruta) === 'pendientes' ? 'bg-[#C9342C] text-white shadow-xs' : 'text-[#6E6E73] hover:bg-black/[0.05]'"
                        >
                          Pendientes ({{ detallePorRuta[r.ruta].totales?.no_visitados ?? 0 }})
                        </button>
                        <button
                          v-if="(detallePorRuta[r.ruta].totales?.fuera_plan ?? 0) > 0"
                          type="button"
                          @click.stop="setSubtablaFiltro(r.ruta, 'fuera_plan')"
                          class="px-2 py-0.5 rounded-[6px] transition-colors font-semibold cursor-pointer"
                          :class="getSubtablaFiltro(r.ruta) === 'fuera_plan' ? 'bg-neutral-700 text-white shadow-xs' : 'text-[#6E6E73] hover:bg-black/[0.05]'"
                        >
                          Fuera de Plan ({{ detallePorRuta[r.ruta].totales?.fuera_plan }})
                        </button>
                      </div>

                      <span class="text-[#86868B] font-mono">
                        Mostrando: <strong>{{ getEventosOrdenados(r.ruta).length }}</strong> clientes
                      </span>
                    </div>

                    <div v-if="(detallePorRuta[r.ruta].eventos || []).length === 0" class="p-6 text-center text-[#86868B] text-[11px]">
                      No se registraron paradas ni marcajes en la fecha consultada.
                    </div>

                    <div v-else class="overflow-x-auto max-h-80 custom-scroll">
                      <table class="w-full text-left border-collapse text-[11px]">
                        <thead class="sticky top-0 z-10 border-b border-black/[0.06] text-[10px] font-bold uppercase text-[#6E6E73] bg-[#F8F8FA]">
                          <tr>
                            <th
                              @click.stop="toggleSubtablaSort(r.ruta, 'orden')"
                              class="px-2.5 py-1.5 text-center w-8 cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
                              :class="getSubtablaSort(r.ruta).col === 'orden' ? 'text-[#0071E3]' : ''"
                              title="Ordenar por Secuencia"
                            >
                              <div class="flex items-center justify-center gap-0.5">
                                <span>#</span>
                                <span v-if="getSubtablaSort(r.ruta).col === 'orden'">{{ getSubtablaSort(r.ruta).dir === 'desc' ? '↓' : '↑' }}</span>
                              </div>
                            </th>
                            <th
                              @click.stop="toggleSubtablaSort(r.ruta, 'cliente')"
                              class="px-3 py-1.5 cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
                              :class="getSubtablaSort(r.ruta).col === 'cliente' ? 'text-[#0071E3]' : ''"
                              title="Ordenar por Cliente / PDV"
                            >
                              <div class="flex items-center gap-1">
                                <span>Cliente / PDV</span>
                                <span v-if="getSubtablaSort(r.ruta).col === 'cliente'">{{ getSubtablaSort(r.ruta).dir === 'desc' ? '↓' : '↑' }}</span>
                              </div>
                            </th>
                            <th
                              @click.stop="toggleSubtablaSort(r.ruta, 'hora_web')"
                              class="px-2.5 py-1.5 text-center w-24 cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
                              :class="getSubtablaSort(r.ruta).col === 'hora_web' ? 'text-[#0071E3]' : ''"
                              title="Ordenar por Check-in Web"
                            >
                              <div class="flex items-center justify-center gap-0.5">
                                <span>Check-in Web</span>
                                <span v-if="getSubtablaSort(r.ruta).col === 'hora_web'">{{ getSubtablaSort(r.ruta).dir === 'desc' ? '↓' : '↑' }}</span>
                              </div>
                            </th>
                            <th
                              @click.stop="toggleSubtablaSort(r.ruta, 'hora_app')"
                              class="px-2.5 py-1.5 text-center w-24 cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
                              :class="getSubtablaSort(r.ruta).col === 'hora_app' ? 'text-[#0071E3]' : ''"
                              title="Ordenar por Check-in App"
                            >
                              <div class="flex items-center justify-center gap-0.5">
                                <span>Check-in App</span>
                                <span v-if="getSubtablaSort(r.ruta).col === 'hora_app'">{{ getSubtablaSort(r.ruta).dir === 'desc' ? '↓' : '↑' }}</span>
                              </div>
                            </th>
                            <th
                              @click.stop="toggleSubtablaSort(r.ruta, 'diferencia_segundos')"
                              class="px-2.5 py-1.5 text-center w-24 cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
                              :class="getSubtablaSort(r.ruta).col === 'diferencia_segundos' ? 'text-[#0071E3]' : ''"
                              title="Ordenar por Diferencia de Horario (Web vs App)"
                            >
                              <div class="flex items-center justify-center gap-0.5">
                                <span>Diferencia</span>
                                <span v-if="getSubtablaSort(r.ruta).col === 'diferencia_segundos'">{{ getSubtablaSort(r.ruta).dir === 'desc' ? '↓' : '↑' }}</span>
                              </div>
                            </th>
                            <th
                              @click.stop="toggleSubtablaSort(r.ruta, 'tipo')"
                              class="px-2.5 py-1.5 text-center cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
                              :class="getSubtablaSort(r.ruta).col === 'tipo' ? 'text-[#0071E3]' : ''"
                              title="Ordenar por Tipo"
                            >
                              <div class="flex items-center justify-center gap-0.5">
                                <span>Tipo</span>
                                <span v-if="getSubtablaSort(r.ruta).col === 'tipo'">{{ getSubtablaSort(r.ruta).dir === 'desc' ? '↓' : '↑' }}</span>
                              </div>
                            </th>
                            <th
                              @click.stop="toggleSubtablaSort(r.ruta, 'monto')"
                              class="px-2.5 py-1.5 text-right font-mono cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
                              :class="getSubtablaSort(r.ruta).col === 'monto' ? 'text-[#0071E3]' : ''"
                              title="Ordenar por Monto"
                            >
                              <div class="flex items-center justify-end gap-0.5">
                                <span>Monto (Bs)</span>
                                <span v-if="getSubtablaSort(r.ruta).col === 'monto'">{{ getSubtablaSort(r.ruta).dir === 'desc' ? '↓' : '↑' }}</span>
                              </div>
                            </th>
                            <th class="px-2.5 py-1.5 text-center w-12">Foto</th>
                          </tr>
                        </thead>
                        <tbody class="divide-y divide-black/[0.03]">
                          <tr
                            v-for="ev in getEventosOrdenados(r.ruta)"
                            :key="ev.orden || ev.cliente"
                            class="hover:bg-black/[0.02] transition-colors"
                          >
                            <td class="px-2.5 py-1 text-center font-mono text-[10px] text-[#86868B]">{{ ev.orden }}</td>
                            <td class="px-3 py-1 font-semibold text-[#1D1D1F] truncate max-w-[280px]" :title="ev.cliente">
                              <div class="flex items-center gap-1.5 min-w-0">
                                <span class="truncate">{{ ev.cliente }}</span>
                                <span v-if="ev.client_id" class="px-1.5 py-0.2 rounded font-mono text-[9px] font-bold text-[#0071E3] bg-blue-50 border border-blue-200 shrink-0">
                                  #{{ ev.client_id }}
                                </span>
                                <span v-else class="text-[9px] text-[#C7C7CC] shrink-0 font-mono">s/código</span>
                                <span v-if="ev.en_plan === false" class="px-1 py-0.2 rounded text-[8.5px] font-bold text-neutral-600 bg-neutral-100 border border-neutral-300 shrink-0" title="Cliente no programado hoy en pan_ruteo">
                                  Fuera Plan
                                </span>
                              </div>
                            </td>
                            <td class="px-2.5 py-1 text-center font-mono text-[11px]" :class="ev.hora_web ? 'font-bold text-[#1D1D1F]' : 'text-[#C7C7CC]'">
                              {{ ev.hora_web || '—' }}
                            </td>
                            <td class="px-2.5 py-1 text-center font-mono text-[11px]" :class="ev.hora_app ? 'font-bold text-[#1D1D1F]' : 'text-[#C7C7CC]'">
                              {{ ev.hora_app || '—' }}
                            </td>
                            <td class="px-2.5 py-1 text-center">
                              <span
                                v-if="ev.diferencia_texto && ev.diferencia_texto !== '—'"
                                class="inline-flex px-1.5 py-0.2 rounded font-mono text-[9.5px] font-bold"
                                :class="badgeColorDiferencia(ev)"
                                :title="`Diferencia entre App (${ev.hora_app || '—'}) y Web (${ev.hora_web || '—'})`"
                              >
                                {{ ev.diferencia_texto }}
                              </span>
                              <span v-else class="text-[#C7C7CC] text-[11px] font-mono">—</span>
                            </td>
                            <td class="px-2.5 py-1 text-center">
                              <span
                                class="inline-flex px-1.5 py-0.2 rounded text-[9px] font-bold"
                                :class="badgeColorTipo(ev.tipo)"
                              >
                                {{ ev.tipo || (ev.visitado === false ? 'No Visitado' : 'Visita') }}
                              </span>
                            </td>
                            <td class="px-2.5 py-1 text-right font-mono font-bold text-[#1D1D1F]">
                              {{ ev.monto > 0 ? ev.monto.toFixed(2) : '—' }}
                            </td>
                            <td class="px-2.5 py-1 text-center">
                              <button
                                v-if="ev.foto_url || ev.tiene_fotos_historicas"
                                type="button"
                                @click.stop="abrirCarruselFotos(ev)"
                                class="p-1 rounded hover:bg-black/[0.05] transition-colors cursor-pointer"
                                :class="ev.foto_url ? 'text-[#0071E3]' : 'text-[#6E6E73]'"
                                :title="ev.foto_url ? 'Ver fotografía del día e histórico' : 'Ver fotografías históricas'"
                              >
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                              </button>
                              <span v-else class="text-[#C7C7CC] text-[10px]">—</span>
                            </td>
                          </tr>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </td>
              </tr>
            </template>
          </template>
        </tbody>
      </table>
    </div>

    <!-- Modal Carrusel de Fotos de Campo -->
    <Teleport to="body">
      <div
        v-if="modalFoto.abierto"
        class="fixed inset-0 z-[9999] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
        @click.self="cerrarModalFoto"
      >
        <div class="apple-glass-card rounded-[20px] max-w-xl w-full p-4 overflow-hidden flex flex-col gap-3 shadow-2xl bg-white">
          <div class="flex items-center justify-between border-b border-black/[0.06] pb-2">
            <div>
              <h4 class="font-bold text-sm text-[#1D1D1F]">{{ modalFoto.cliente }}</h4>
              <p class="text-[11px] text-[#6E6E73] font-medium">Auditoría fotográfica de visita</p>
            </div>
            <button
              type="button"
              @click="cerrarModalFoto"
              class="w-7 h-7 rounded-full flex items-center justify-center hover:bg-black/[0.05] text-[#86868B] transition-colors cursor-pointer"
            >
              ✕
            </button>
          </div>

          <div class="relative bg-black/5 rounded-[14px] overflow-hidden min-h-[300px] max-h-[460px] flex items-center justify-center">
            <div v-if="modalFoto.cargando" class="flex flex-col items-center justify-center gap-2 p-8 text-[#86868B]">
              <div class="w-6 h-6 border-2 border-[#0071E3] border-t-transparent rounded-full animate-spin"></div>
              <span class="text-xs font-medium">Buscando fotografías...</span>
            </div>
            <template v-else-if="fotoActual && (fotoActual.url || fotoActual.photo_url)">
              <img
                :src="fotoActual.url || fotoActual.photo_url"
                class="max-h-[460px] w-auto object-contain select-none transition-all duration-200"
                alt="Foto campo"
                @error="fotoActual.error = true"
              />
              <div v-if="fotoActual.error" class="text-center text-xs text-[#C9342C] p-4">
                No se pudo cargar la imagen desde el servidor.
              </div>
            </template>
            <div v-else class="text-[#86868B] text-xs p-6 text-center">
              Sin fotografías disponibles para este cliente
            </div>

            <!-- Navegación Carrusel -->
            <button
              v-if="!modalFoto.cargando && modalFoto.fotos.length > 1"
              type="button"
              @click="navegarCarrusel(-1)"
              class="absolute left-2 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-white/80 hover:bg-white text-[#1D1D1F] shadow flex items-center justify-center cursor-pointer transition-colors"
            >
              ‹
            </button>
            <button
              v-if="!modalFoto.cargando && modalFoto.fotos.length > 1"
              type="button"
              @click="navegarCarrusel(1)"
              class="absolute right-2 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-white/80 hover:bg-white text-[#1D1D1F] shadow flex items-center justify-center cursor-pointer transition-colors"
            >
              ›
            </button>
          </div>

          <div class="flex items-center justify-between text-xs text-[#6E6E73]">
            <span class="font-medium truncate max-w-[80%]">
              {{ modalFoto.cargando ? 'Cargando evidencia...' : (fotoActual?.fecha || 'Evidencia fotográfica') }}
            </span>
            <span class="font-mono font-bold text-[#1D1D1F]">
              {{ modalFoto.fotos.length > 0 ? (modalFoto.indice + 1) + ' / ' + modalFoto.fotos.length : '0 / 0' }}
            </span>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch } from 'vue'
import axios from 'axios'
import { usePreventaStore } from '@/Stores/preventaStore'

const store = usePreventaStore()

const sortCol = ref('puntos')
const sortDir = ref('desc')

const sortLabels = {
  pos: 'Posición Ranking',
  ruta: 'Ruta',
  vendedor: 'Preventista',
  hora: 'Primer Contacto',
  hora_web: 'Check-in Web',
  hora_app: 'Check-in App',
  estatus: 'Estatus',
  puntos: 'Puntos (10)'
}

function toggleSort(col) {
  if (sortCol.value === col) {
    sortDir.value = sortDir.value === 'desc' ? 'asc' : 'desc'
  } else {
    sortCol.value = col
    if (['pos', 'ruta', 'vendedor', 'hora', 'hora_web', 'hora_app'].includes(col)) {
      sortDir.value = 'asc'
    } else {
      sortDir.value = 'desc'
    }
  }
}

function compareHoras(horaA, horaB, dir) {
  const valA = horaA ? String(horaA).trim() : null
  const valB = horaB ? String(horaB).trim() : null
  if (!valA && !valB) return 0
  if (!valA) return 1
  if (!valB) return -1
  return dir * valA.localeCompare(valB)
}

const rutasOrdenadas = computed(() => {
  const lista = [...(store.rutasFiltradas || [])]
  const col = sortCol.value
  const dir = sortDir.value === 'asc' ? 1 : -1

  lista.sort((a, b) => {
    if (col === 'pos') {
      const pA = Number(a.rank ?? 9999)
      const pB = Number(b.rank ?? 9999)
      if (pA !== pB) return dir * (pA - pB)
      return String(a.ruta || '').localeCompare(String(b.ruta || ''), undefined, { numeric: true })
    }
    if (col === 'ruta') {
      return dir * String(a.ruta || '').localeCompare(String(b.ruta || ''), undefined, { numeric: true })
    }
    if (col === 'vendedor') {
      const vA = String(a.vendedor || '').trim()
      const vB = String(b.vendedor || '').trim()
      const cmp = vA.localeCompare(vB, undefined, { numeric: true, sensitivity: 'base' })
      if (cmp !== 0) return dir * cmp
      return String(a.ruta || '').localeCompare(String(b.ruta || ''), undefined, { numeric: true })
    }
    if (col === 'hora') {
      const hA = a.kpis?.horario_ingreso?.metricas?.hora_ingreso_detectada
      const hB = b.kpis?.horario_ingreso?.metricas?.hora_ingreso_detectada
      const cmp = compareHoras(hA, hB, dir)
      if (cmp !== 0) return cmp
      return String(a.ruta || '').localeCompare(String(b.ruta || ''), undefined, { numeric: true })
    }
    if (col === 'hora_web') {
      const hwA = a.kpis?.horario_ingreso?.metricas?.hora_web
      const hwB = b.kpis?.horario_ingreso?.metricas?.hora_web
      const cmp = compareHoras(hwA, hwB, dir)
      if (cmp !== 0) return cmp
      return String(a.ruta || '').localeCompare(String(b.ruta || ''), undefined, { numeric: true })
    }
    if (col === 'hora_app') {
      const haA = a.kpis?.horario_ingreso?.metricas?.hora_app
      const haB = b.kpis?.horario_ingreso?.metricas?.hora_app
      const cmp = compareHoras(haA, haB, dir)
      if (cmp !== 0) return cmp
      return String(a.ruta || '').localeCompare(String(b.ruta || ''), undefined, { numeric: true })
    }
    if (col === 'estatus') {
      const getWeight = (item) => {
        if (item.kpis?.horario_ingreso?.cumple) return 2
        if (item.kpis?.horario_ingreso?.metricas?.hora_ingreso_detectada) return 1
        return 0
      }
      const eA = getWeight(a)
      const eB = getWeight(b)
      if (eA !== eB) return dir * (eA - eB)
      const hA = a.kpis?.horario_ingreso?.metricas?.hora_ingreso_detectada
      const hB = b.kpis?.horario_ingreso?.metricas?.hora_ingreso_detectada
      const cmpH = compareHoras(hA, hB, 1)
      if (cmpH !== 0) return cmpH
      return String(a.ruta || '').localeCompare(String(b.ruta || ''), undefined, { numeric: true })
    }
    // puntos: default
    const pA = a.kpis?.horario_ingreso?.cumple ? 10 : 0
    const pB = b.kpis?.horario_ingreso?.cumple ? 10 : 0
    if (pA !== pB) return dir * (pA - pB)
    const hA = a.kpis?.horario_ingreso?.metricas?.hora_ingreso_detectada
    const hB = b.kpis?.horario_ingreso?.metricas?.hora_ingreso_detectada
    const cmpH = compareHoras(hA, hB, 1)
    if (cmpH !== 0) return cmpH
    return String(a.ruta || '').localeCompare(String(b.ruta || ''), undefined, { numeric: true })
  })

  return lista
})

const subtablaSort = reactive({})
const subtablaFiltro = reactive({})

function getSubtablaSort(ruta) {
  if (!subtablaSort[ruta]) {
    subtablaSort[ruta] = { col: 'orden', dir: 'asc' }
  }
  return subtablaSort[ruta]
}

function toggleSubtablaSort(ruta, col) {
  const current = getSubtablaSort(ruta)
  if (current.col === col) {
    current.dir = current.dir === 'asc' ? 'desc' : 'asc'
  } else {
    current.col = col
    current.dir = (col === 'monto' ? 'desc' : 'asc')
  }
}

function getSubtablaFiltro(ruta) {
  return subtablaFiltro[ruta] || 'todos'
}

function setSubtablaFiltro(ruta, f) {
  subtablaFiltro[ruta] = f
}

function getEventosOrdenados(ruta) {
  const eventos = detallePorRuta[ruta]?.eventos || []
  const filtro = getSubtablaFiltro(ruta)

  const filtrados = eventos.filter(ev => {
    if (filtro === 'visitados') return ev.visitado !== false
    if (filtro === 'pendientes') return ev.visitado === false
    if (filtro === 'fuera_plan') return ev.en_plan === false
    return true
  })

  const { col, dir } = getSubtablaSort(ruta)
  const mul = dir === 'asc' ? 1 : -1

  return [...filtrados].sort((a, b) => {
    if (col === 'orden') {
      return mul * ((Number(a.orden) || 0) - (Number(b.orden) || 0))
    }
    if (col === 'cliente') {
      return mul * String(a.cliente || '').localeCompare(String(b.cliente || ''))
    }
    if (col === 'hora_web') {
      return compareHoras(a.hora_web, b.hora_web, mul)
    }
    if (col === 'hora_app') {
      return compareHoras(a.hora_app, b.hora_app, mul)
    }
    if (col === 'diferencia_segundos') {
      const valA = a.diferencia_segundos !== null && a.diferencia_segundos !== undefined ? Number(a.diferencia_segundos) : 999999
      const valB = b.diferencia_segundos !== null && b.diferencia_segundos !== undefined ? Number(b.diferencia_segundos) : 999999
      return mul * (valA - valB)
    }
    if (col === 'tipo') {
      return mul * String(a.tipo || '').localeCompare(String(b.tipo || ''))
    }
    if (col === 'monto') {
      return mul * ((Number(a.monto) || 0) - (Number(b.monto) || 0))
    }
    return 0
  })
}

const acordeonAbierto = reactive({})
const cargandoDetalle = reactive({})
const errorDetalle = reactive({})
const detallePorRuta = reactive({})

watch(
  () => store.fecha,
  () => {
    Object.keys(acordeonAbierto).forEach(k => delete acordeonAbierto[k])
    Object.keys(detallePorRuta).forEach(k => delete detallePorRuta[k])
    Object.keys(subtablaFiltro).forEach(k => delete subtablaFiltro[k])
  }
)

async function toggleRecorridoAcordeon(r) {
  const ruta = r.ruta
  if (acordeonAbierto[ruta]) {
    acordeonAbierto[ruta] = false
    return
  }
  acordeonAbierto[ruta] = true
  if (detallePorRuta[ruta]) return

  cargandoDetalle[ruta] = true
  errorDetalle[ruta] = null
  try {
    const res = await axios.post('/supervisor/preventas/cumplimiento/horario/detalle', {
      fecha: store.fecha,
      ruta
    })
    detallePorRuta[ruta] = res.data
  } catch (e) {
    errorDetalle[ruta] = e?.response?.data?.message || e.message || 'Error al cargar cronología de paradas'
  } finally {
    cargandoDetalle[ruta] = false
  }
}

function badgeColorTipo(tipo) {
  const t = String(tipo || '').toLowerCase()
  if (t.includes('no visitado') || t.includes('pendiente')) {
    return 'bg-[#FDF0EF] text-[#C9342C] border border-[#FADCD5]'
  }
  if (t.includes('preventa') || t.includes('venta')) {
    return 'bg-[#EBF9EF] text-[#1E6B37] border border-[#D1F2D9]'
  }
  if (t.includes('no') || t.includes('cerrad') || t.includes('rechaz') || t.includes('sin')) {
    return 'bg-[#FDF0EF] text-[#C9342C] border border-[#FADCD5]'
  }
  if (t.includes('cobranza')) {
    return 'bg-blue-50 text-[#0071E3] border border-blue-200'
  }
  if (t.includes('oportunidad')) {
    return 'bg-purple-50 text-purple-700 border border-purple-200'
  }
  return 'bg-black/[0.04] text-[#1D1D1F]'
}

function badgeColorDiferencia(ev) {
  if (!ev || ev.diferencia_segundos === null || ev.diferencia_segundos === undefined) {
    if (ev?.diferencia_texto === 'Solo Web' || ev?.diferencia_texto === 'Solo App') {
      return 'bg-slate-100 text-slate-600 border border-slate-200'
    }
    return 'text-[#C7C7CC]'
  }
  const s = Math.abs(ev.diferencia_segundos)
  if (s <= 120) {
    return 'bg-emerald-50 text-emerald-700 border border-emerald-200'
  }
  if (s <= 600) {
    return 'bg-amber-50 text-amber-700 border border-amber-200'
  }
  return 'bg-rose-50 text-rose-700 border border-rose-200'
}

const modalFoto = reactive({
  abierto: false,
  cargando: false,
  cliente: '',
  fotos: [],
  indice: 0
})

const fotoActual = computed(() => modalFoto.fotos[modalFoto.indice] || null)

async function abrirCarruselFotos(ev) {
  modalFoto.abierto = true
  modalFoto.cliente = ev.cliente || 'Cliente'
  modalFoto.indice = 0
  modalFoto.cargando = false

  const listaInicial = []
  if (ev.foto_url) {
    listaInicial.push({
      url: ev.foto_url,
      photo_url: ev.foto_url,
      fecha: `Visita actual (${ev.hora_app || ev.hora_web || ''})`,
      ruta: ''
    })
  }

  modalFoto.fotos = listaInicial

  if (ev.client_id) {
    modalFoto.cargando = listaInicial.length === 0
    try {
      const res = await axios.post(`/supervisor/preventas/cumplimiento/fotos/${ev.client_id}`)
      if (res.data?.success && Array.isArray(res.data.fotos)) {
        const urlActual = ev.foto_url ? ev.foto_url.trim().toLowerCase() : ''
        const extras = res.data.fotos
          .map(f => {
            const urlFinal = f.url || f.photo_url || ''
            const fechaTxt = `${f.fecha || ''} ${f.hora || ''} ${f.vendedor ? '· ' + f.vendedor : ''} ${f.comments ? '(' + f.comments + ')' : ''}`.trim()
            return {
              ...f,
              url: urlFinal,
              photo_url: urlFinal,
              fecha: fechaTxt || 'Foto histórica'
            }
          })
          .filter(f => f.url && f.url.trim().toLowerCase() !== urlActual)

        modalFoto.fotos.push(...extras)
      }
    } catch (e) {
      console.warn('[Horario] No se pudieron cargar fotos históricas:', e)
    } finally {
      modalFoto.cargando = false
    }
  }
}

function navegarCarrusel(delta) {
  const total = modalFoto.fotos.length
  if (total <= 1) return
  modalFoto.indice = (modalFoto.indice + delta + total) % total
}

function cerrarModalFoto() {
  modalFoto.abierto = false
}
</script>

