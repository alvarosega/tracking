<template>
  <div class="apple-glass-card rounded-[16px] overflow-hidden select-none font-sans">
    <!-- Encabezado Compacto -->
    <div class="px-3.5 py-2.5 border-b border-black/[0.06] flex flex-wrap items-center justify-between gap-2 bg-white/40">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <h3 class="text-[13px] font-bold text-[#1D1D1F] tracking-tight leading-tight">
            Pizarra Modelo Unilever · KPI 2: Cobertura de Visitas a Cartera Programada
          </h3>
          <span class="px-2 py-0.5 rounded-[5px] text-[10px] font-bold text-[#1E6B37] bg-[#EBF9EF] border border-[#D1F2D9]">
            5 pts Base · Criterio Binario
          </span>
        </div>
        <p class="text-[11px] text-[#6E6E73] font-medium mt-0.5">
          Regla: Los clientes activos visitados deben cubrir el 100% de la cartera planificada activa. Visitas a inactivos o fuera de plan no suman a la meta del día.
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
      <table class="w-full text-left border-collapse min-w-[600px] sm:min-w-[800px] text-[12px]">
        <thead class="sticky top-0 z-20 border-b border-black/[0.06] text-[10px] font-bold uppercase tracking-wider text-[#6E6E73] bg-[#F8F8FA]">
          <tr>
            <th class="px-1 sm:px-2 py-2 text-center sticky left-0 z-30 w-7 sm:w-9 border-r border-black/[0.05] bg-[#F8F8FA] text-[10px] sm:text-[11px]">Pos</th>
            <th
              @click="toggleSort('ruta')"
              class="px-1.5 sm:px-2.5 py-2 sticky left-7 sm:left-9 z-30 w-24 sm:w-44 md:w-52 border-r border-black/[0.06] shadow-[2px_0_4px_-2px_rgba(0,0,0,0.05)] bg-[#F8F8FA] cursor-pointer hover:bg-black/[0.04] transition-colors select-none text-[10px] sm:text-[11px]"
              title="Ordenar por Ruta"
            >
              <div class="flex items-center gap-1">
                <span :class="sortCol === 'ruta' ? 'text-[#0071E3] font-bold' : ''">Ruta / Preventista</span>
                <span v-if="sortCol === 'ruta'" class="text-[#0071E3] font-bold">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
              </div>
            </th>
            
            <th
              @click="toggleSort('plan')"
              class="px-3 py-2 text-center border-r border-black/[0.04] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
              :class="sortCol === 'plan' ? 'bg-blue-50/70 text-[#0071E3]' : ''"
              title="Ordenar por Cartera Plan"
            >
              <div class="flex items-center justify-center gap-1">
                <span>Cartera Plan</span>
                <span v-if="sortCol === 'plan'" class="font-bold">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
              </div>
            </th>

            <th
              @click="toggleSort('visitados')"
              class="px-3 py-2 text-center border-r border-black/[0.04] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
              :class="sortCol === 'visitados' ? 'bg-blue-50/70 text-[#0071E3]' : ''"
              title="Ordenar por Visitados"
            >
              <div class="flex items-center justify-center gap-1">
                <span>Visitados</span>
                <span v-if="sortCol === 'visitados'" class="font-bold">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
              </div>
            </th>

            <th
              @click="toggleSort('inactivos')"
              class="px-3 py-2 text-center border-r border-black/[0.04] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
              :class="sortCol === 'inactivos' ? 'bg-blue-50/70 text-[#0071E3]' : ''"
              title="Ordenar por Inactivos"
            >
              <div class="flex items-center justify-center gap-1">
                <span>Inactivos</span>
                <span v-if="sortCol === 'inactivos'" class="font-bold">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
              </div>
            </th>

            <th
              @click="toggleSort('fueraPlan')"
              class="px-3 py-2 text-center border-r border-black/[0.04] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
              :class="sortCol === 'fueraPlan' ? 'bg-blue-50/70 text-[#0071E3]' : ''"
              title="Ordenar por Fuera Plan"
            >
              <div class="flex items-center justify-center gap-1">
                <span>Fuera Plan</span>
                <span v-if="sortCol === 'fueraPlan'" class="font-bold">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
              </div>
            </th>

            <th
              @click="toggleSort('dif')"
              class="px-3 py-2 text-center border-r border-black/[0.04] cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
              :class="sortCol === 'dif' ? 'bg-blue-50/70 text-[#0071E3]' : ''"
              title="Ordenar por Diferencia"
            >
              <div class="flex items-center justify-center gap-1">
                <span>Diferencia</span>
                <span v-if="sortCol === 'dif'" class="font-bold">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
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
                <span v-if="sortCol === 'estatus'" class="font-bold">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
              </div>
            </th>

            <th
              @click="toggleSort('puntos')"
              class="px-3 py-2 text-right cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
              :class="sortCol === 'puntos' ? 'bg-blue-50/70 text-[#0071E3]' : ''"
              title="Ordenar por Puntos (5)"
            >
              <div class="flex items-center justify-end gap-1">
                <span>Puntos (5)</span>
                <span v-if="sortCol === 'puntos'" class="font-bold">{{ sortDir === 'desc' ? '↓' : '↑' }}</span>
              </div>
            </th>
          </tr>
        </thead>

        <tbody class="divide-y divide-black/[0.04]">
          <template v-if="store.loadingRanking">
            <tr v-for="i in 10" :key="`skel-vis-${i}`">
              <td class="px-1 sm:px-2 py-2 sticky left-0 w-7 sm:w-9"><div class="h-3.5 w-4 mx-auto skeleton"></div></td>
              <td class="px-1.5 sm:px-2.5 py-2 sticky left-7 sm:left-9 w-24 sm:w-44 md:w-52 shadow-[2px_0_4px_-2px_rgba(0,0,0,0.05)]">
                <div class="h-3.5 w-12 sm:w-16 skeleton mb-1"></div>
                <div class="h-3 w-16 sm:w-28 skeleton"></div>
              </td>
              <td v-for="c in 6" :key="c" class="px-3 py-2 text-center"><div class="h-4 w-10 mx-auto skeleton"></div></td>
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
                @click="toggleVisitadosAcordeon(r)"
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

                <!-- Cartera Plan -->
                <td class="px-3 py-1.5 text-center font-mono font-bold text-[#1D1D1F] border-r border-black/[0.04]">
                  {{ metricasFila(r).plan }}
                </td>

                <!-- Visitados -->
                <td
                  class="px-3 py-1.5 text-center font-mono font-bold border-r border-black/[0.04]"
                  :class="metricasFila(r).visitados < metricasFila(r).plan ? 'text-[#C9342C]' : 'text-[#1E6B37]'"
                >
                  {{ metricasFila(r).visitados }}
                </td>

                <!-- Inactivos -->
                <td class="px-3 py-1.5 text-center font-mono text-[#6E6E73] border-r border-black/[0.04]">
                  {{ metricasFila(r).inactivos }}
                </td>

                <!-- Fuera Plan -->
                <td class="px-3 py-1.5 text-center font-mono text-[#6E6E73] border-r border-black/[0.04]">
                  {{ metricasFila(r).fueraPlan }}
                </td>

                <!-- Diferencia -->
                <td
                  class="px-3 py-1.5 text-center font-mono font-bold border-r border-black/[0.04]"
                  :class="metricasFila(r).dif < 0 ? 'text-[#C9342C]' : (metricasFila(r).dif > 0 ? 'text-[#1E6B37]' : 'text-[#6E6E73]')"
                >
                  {{ metricasFila(r).dif > 0 ? `+${metricasFila(r).dif}` : metricasFila(r).dif }}
                </td>

                <!-- Estatus -->
                <td class="px-3 py-1.5 text-center border-r border-black/[0.04]">
                  <span
                    v-if="r.kpis?.clientes_visitados?.cumple"
                    class="inline-flex items-center px-2 py-0.5 rounded-[5px] text-[10px] font-bold bg-[#EBF9EF] text-[#1E6B37] border border-[#D1F2D9]"
                  >CUMPLE</span>
                  <span
                    v-else
                    class="inline-flex items-center px-2 py-0.5 rounded-[5px] text-[10px] font-bold bg-[#FDF0EF] text-[#C9342C] border border-[#FADCD5]"
                  >DÉFICIT</span>
                </td>

                <!-- Puntos (5) -->
                <td class="px-3 py-1.5 text-right font-mono font-bold text-[12px]">
                  <span
                    v-if="r.kpis?.clientes_visitados?.cumple"
                    class="inline-flex items-center justify-center min-w-[28px] px-1.5 py-0.5 rounded-[5px] text-[11px] font-bold text-[#1E6B37] bg-[#EBF9EF] border border-[#D1F2D9]"
                  >5</span>
                  <span v-else class="text-[#86868B] text-[11px]">—</span>
                </td>
              </tr>

              <!-- Fila Acordeón: Auditoría Consolidada de Cartera -->
              <tr v-if="acordeonAbierto[r.ruta]">
                <td colspan="9" class="p-3 bg-white/60 border-b border-black/[0.06]">
                  <div v-if="cargandoDetalle[r.ruta]" class="p-4 text-center text-[#6E6E73] text-[11px] font-medium">
                    Auditando cobertura de cartera para {{ r.ruta }}...
                  </div>
                  <div v-else-if="errorDetalle[r.ruta]" class="p-4 text-center text-[#C9342C] text-[11px] font-semibold">
                    {{ errorDetalle[r.ruta] }}
                  </div>
                  <div v-else-if="detallePorRuta[r.ruta]" class="apple-glass-card rounded-[12px] overflow-hidden">
                    <!-- Barra de Resumen -->
                    <div class="px-3 py-2 border-b border-black/[0.06] flex flex-wrap items-center justify-between gap-2 bg-white/50 text-[11px]">
                      <span class="font-bold text-[#1D1D1F]">
                        Auditoría de Cartera: {{ r.ruta }} ({{ r.vendedor }})
                      </span>
                      <div class="flex flex-wrap items-center gap-2 font-mono text-[10px]">
                        <span class="apple-control-pill px-2 py-0.5 rounded-[6px]">
                          Plan Activo: <strong>{{ detallePorRuta[r.ruta].totales?.cartera_plan || 0 }}</strong>
                        </span>
                        <span class="px-2 py-0.5 rounded-[6px] bg-[#EBF9EF] text-[#1E6B37] border border-[#D1F2D9]">
                          Visitados: <strong>{{ detallePorRuta[r.ruta].totales?.visitados || 0 }}</strong>
                        </span>
                        <span class="px-2 py-0.5 rounded-[6px] bg-[#FDF0EF] text-[#C9342C] border border-[#FADCD5]">
                          No Visitados: <strong>{{ detallePorRuta[r.ruta].totales?.no_visitados || 0 }}</strong>
                        </span>
                        <span class="apple-control-pill px-2 py-0.5 rounded-[6px]">
                          Visita Inactivo: <strong>{{ detallePorRuta[r.ruta].totales?.visitados_inactivos || 0 }}</strong>
                        </span>
                        <span class="px-2 py-0.5 rounded-[6px] bg-[#FFF5E5] text-[#B25E00] border border-amber-200">
                          Fuera de Plan: <strong>{{ detallePorRuta[r.ruta].totales?.fuera_plan || 0 }}</strong>
                        </span>
                      </div>
                    </div>

                    <!-- Subtabs de Cartera -->
                    <div class="px-3 py-1.5 bg-black/[0.02] border-b border-black/[0.05] flex items-center gap-1.5 text-[11px]">
                      <button
                        type="button"
                        v-for="st in [
                          { id: 'todos', label: 'Todos' },
                          { id: 'visitados', label: 'Visitados Activos' },
                          { id: 'no_visitados', label: 'No Visitados' },
                          { id: 'inactivos', label: 'Inactivos' },
                          { id: 'fuera_plan', label: 'Fuera de Plan' }
                        ]"
                        :key="st.id"
                        @click="subTabRuta[r.ruta] = st.id"
                        :class="(subTabRuta[r.ruta] || 'todos') === st.id ? 'glass-active-pill font-bold text-[#0071E3]' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
                        class="px-2.5 py-1 rounded-[7px] text-[10px] font-semibold cursor-pointer transition-colors"
                      >
                        {{ st.label }}
                      </button>
                    </div>

                    <!-- Tabla de Clientes Auditados -->
                    <div class="overflow-x-auto max-h-80 custom-scroll">
                      <table class="w-full text-left border-collapse text-[11px]">
                        <thead class="sticky top-0 z-10 border-b border-black/[0.06] text-[10px] font-bold uppercase text-[#6E6E73] bg-[#F8F8FA]">
                          <tr>
                            <th class="px-2.5 py-1.5 text-center w-8">#</th>
                            <th class="px-2.5 py-1.5 w-20">Código</th>
                            <th class="px-3 py-1.5">Cliente / PDV</th>
                            <th
                              @click.stop="toggleSubtablaSort(r.ruta, 'check_in')"
                              class="px-2.5 py-1.5 text-center w-24 cursor-pointer hover:bg-black/[0.04] transition-colors select-none"
                              :class="getSubtablaSort(r.ruta).col === 'check_in' ? 'text-[#0071E3]' : ''"
                              title="Ordenar por Check-in Web"
                            >
                              <div class="flex items-center justify-center gap-0.5">
                                <span>Check-in Web</span>
                                <span v-if="getSubtablaSort(r.ruta).col === 'check_in'">{{ getSubtablaSort(r.ruta).dir === 'desc' ? '↓' : '↑' }}</span>
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
                              title="Ordenar por Diferencia Web vs App"
                            >
                              <div class="flex items-center justify-center gap-0.5">
                                <span>Diferencia</span>
                                <span v-if="getSubtablaSort(r.ruta).col === 'diferencia_segundos'">{{ getSubtablaSort(r.ruta).dir === 'desc' ? '↓' : '↑' }}</span>
                              </div>
                            </th>
                            <th class="px-2.5 py-1.5 text-center">Tiempo PDV</th>
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
                            <th class="px-2.5 py-1.5 text-center">Estatus</th>
                            <th class="px-2.5 py-1.5 text-center w-12">Foto</th>
                          </tr>
                        </thead>
                        <tbody class="divide-y divide-black/[0.03]">
                          <tr
                            v-for="(c, cIdx) in clientesVisitasFiltrados(r.ruta)"
                            :key="c.cliente_id || cIdx"
                            class="hover:bg-black/[0.02] transition-colors"
                          >
                            <td class="px-2.5 py-1 text-center font-mono text-[10px] text-[#86868B]">{{ cIdx + 1 }}</td>
                            <td class="px-2.5 py-1 font-mono text-[10px]">
                              <span v-if="c.cliente_id" class="px-1.5 py-0.2 rounded font-mono text-[9px] font-bold text-[#0071E3] bg-blue-50 border border-blue-200">
                                #{{ c.cliente_id }}
                              </span>
                              <span v-else class="text-[#C7C7CC] text-[9px]">s/código</span>
                            </td>
                            <td class="px-3 py-1 font-semibold text-[#1D1D1F] truncate max-w-[240px]" :title="c.cliente">
                              <div class="flex items-center gap-1.5 min-w-0">
                                <span class="truncate">{{ c.cliente }}</span>
                                <span v-if="c.categoria === 'Fuera de Plan'" class="px-1 py-0.2 rounded text-[8.5px] font-bold text-neutral-600 bg-neutral-100 border border-neutral-300 shrink-0" title="Cliente no programado hoy en pan_ruteo">
                                  Fuera Plan
                                </span>
                              </div>
                            </td>
                            <td class="px-2.5 py-1 text-center font-mono text-[11px]" :class="c.check_in ? 'font-bold text-[#1D1D1F]' : 'text-[#C7C7CC]'">
                              {{ c.check_in || '—' }}
                            </td>
                            <td class="px-2.5 py-1 text-center font-mono text-[11px]" :class="c.hora_app ? 'font-bold text-[#1D1D1F]' : 'text-[#C7C7CC]'">
                              {{ c.hora_app || '—' }}
                            </td>
                            <td class="px-2.5 py-1 text-center">
                              <span
                                v-if="c.diferencia_texto && c.diferencia_texto !== '—'"
                                class="inline-flex px-1.5 py-0.2 rounded font-mono text-[9.5px] font-bold"
                                :class="badgeColorDiferencia(c)"
                                :title="`Diferencia entre App (${c.hora_app || '—'}) y Web (${c.check_in || '—'})`"
                              >
                                {{ c.diferencia_texto }}
                              </span>
                              <span v-else class="text-[#C7C7CC] text-[11px] font-mono">—</span>
                            </td>
                            <td class="px-2.5 py-1 text-center font-mono text-[10px] text-[#6E6E73]">{{ c.tiempo_pdv || '—' }}</td>
                            <td class="px-2.5 py-1 text-right font-mono font-bold text-[#1D1D1F]">
                              {{ c.monto > 0 ? Number(c.monto).toFixed(2) : '—' }}
                            </td>
                            <td class="px-2.5 py-1 text-center">
                              <span
                                class="inline-flex px-1.5 py-0.2 rounded text-[9px] font-bold"
                                :class="badgeColorEstatus(c.tipo || c.motivo || c.categoria)"
                              >
                                {{ c.tipo || c.motivo || (c.categoria === 'No Visitado' ? 'No Visitado' : 'Visita') }}
                              </span>
                            </td>
                            <td class="px-2.5 py-1 text-center">
                              <button
                                v-if="c.foto_url"
                                type="button"
                                @click.stop="abrirCarruselFotos(c)"
                                class="p-1 rounded hover:bg-black/[0.05] text-[#0071E3] transition-colors cursor-pointer"
                                title="Ver foto de campo"
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

    <!-- Modal Carrusel de Fotos -->
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
  puntos: 'Puntos (5)',
  plan: 'Cartera Plan',
  visitados: 'Visitados',
  inactivos: 'Inactivos',
  fueraPlan: 'Fuera Plan',
  dif: 'Diferencia',
  estatus: 'Estatus',
  ruta: 'Ruta'
}

function metricasFila(r) {
  const m = r.kpis?.clientes_visitados?.metricas || {}
  return {
    plan: m.cartera_plan || 0,
    visitados: m.visitados || 0,
    inactivos: m.inactivos || 0,
    fueraPlan: m.fueraPlan || 0,
    dif: m.dif !== undefined ? m.dif : ((m.visitados || 0) - (m.cartera_plan || 0))
  }
}

function toggleSort(col) {
  if (sortCol.value === col) {
    sortDir.value = sortDir.value === 'desc' ? 'asc' : 'desc'
  } else {
    sortCol.value = col
    sortDir.value = col === 'plan' || col === 'dif' ? 'asc' : 'desc'
  }
}

const rutasOrdenadas = computed(() => {
  const lista = [...(store.rutasFiltradas || [])]
  const col = sortCol.value
  const dir = sortDir.value === 'asc' ? 1 : -1

  lista.sort((a, b) => {
    if (col === 'ruta') {
      return dir * String(a.ruta || '').localeCompare(String(b.ruta || ''), undefined, { numeric: true })
    }
    const mA = metricasFila(a)
    const mB = metricasFila(b)
    let valA = 0
    let valB = 0
    if (col === 'plan') {
      valA = mA.plan
      valB = mB.plan
    } else if (col === 'visitados') {
      valA = mA.visitados
      valB = mB.visitados
    } else if (col === 'inactivos') {
      valA = mA.inactivos
      valB = mB.inactivos
    } else if (col === 'fueraPlan') {
      valA = mA.fueraPlan
      valB = mB.fueraPlan
    } else if (col === 'dif') {
      valA = mA.dif
      valB = mB.dif
    } else if (col === 'estatus') {
      valA = a.kpis?.clientes_visitados?.cumple ? 1 : 0
      valB = b.kpis?.clientes_visitados?.cumple ? 1 : 0
    } else if (col === 'puntos') {
      valA = a.kpis?.clientes_visitados?.cumple ? 5 : 0
      valB = b.kpis?.clientes_visitados?.cumple ? 5 : 0
    }
    if (valA !== valB) {
      return dir * (valA - valB)
    }
    return String(a.ruta || '').localeCompare(String(b.ruta || ''), undefined, { numeric: true })
  })

  return lista
})

const acordeonAbierto = reactive({})
const subTabRuta = reactive({})
const cargandoDetalle = reactive({})
const errorDetalle = reactive({})
const detallePorRuta = reactive({})

watch(
  () => store.fecha,
  () => {
    Object.keys(acordeonAbierto).forEach(k => delete acordeonAbierto[k])
    Object.keys(detallePorRuta).forEach(k => delete detallePorRuta[k])
  }
)

async function toggleVisitadosAcordeon(r) {
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
    const res = await axios.post('/supervisor/preventas/cumplimiento/visitas/detalle', {
      fecha: store.fecha,
      ruta,
      estricto: store.horarioEstricto
    })
    detallePorRuta[ruta] = res.data
  } catch (e) {
    errorDetalle[ruta] = e?.response?.data?.message || e.message || 'Error al auditar cobertura de cartera'
  } finally {
    cargandoDetalle[ruta] = false
  }
}

const subtablaSort = reactive({})

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

function compareHoras(horaA, horaB, dir) {
  const valA = horaA ? String(horaA).trim() : null
  const valB = horaB ? String(horaB).trim() : null
  if (!valA && !valB) return 0
  if (!valA) return 1
  if (!valB) return -1
  return dir * valA.localeCompare(valB)
}

function badgeColorDiferencia(c) {
  if (!c || c.diferencia_segundos === null || c.diferencia_segundos === undefined) {
    if (c?.diferencia_texto === 'Solo Web' || c?.diferencia_texto === 'Solo App') {
      return 'bg-slate-100 text-slate-600 border border-slate-200'
    }
    return 'text-[#C7C7CC]'
  }
  const s = Math.abs(c.diferencia_segundos)
  if (s <= 120) {
    return 'bg-emerald-50 text-emerald-700 border border-emerald-200'
  }
  if (s <= 600) {
    return 'bg-amber-50 text-amber-700 border border-amber-200'
  }
  return 'bg-rose-50 text-rose-700 border border-rose-200'
}

function clientesVisitasFiltrados(ruta) {
  const d = detallePorRuta[ruta]
  if (!d) return []
  const tab = subTabRuta[ruta] || 'todos'

  const visitados = (d.visitados || []).map(c => ({ ...c, categoria: 'Visitado Activo' }))
  const noVisitados = (d.no_visitados || []).map(c => ({ ...c, categoria: 'No Visitado' }))
  const inactivos = (d.visitados_inactivos || []).map(c => ({ ...c, categoria: 'Visita Inactivo' }))
  const fueraPlan = (d.fuera_plan || []).map(c => ({ ...c, categoria: 'Fuera de Plan' }))

  let lista = []
  if (tab === 'visitados') lista = visitados
  else if (tab === 'no_visitados') lista = noVisitados
  else if (tab === 'inactivos') lista = inactivos
  else if (tab === 'fuera_plan') lista = fueraPlan
  else lista = [...visitados, ...noVisitados, ...inactivos, ...fueraPlan]

  const { col, dir } = getSubtablaSort(ruta)
  const mul = dir === 'asc' ? 1 : -1

  return [...lista].sort((a, b) => {
    if (col === 'cliente') {
      return mul * String(a.cliente || '').localeCompare(String(b.cliente || ''))
    }
    if (col === 'check_in') {
      return compareHoras(a.check_in, b.check_in, mul)
    }
    if (col === 'hora_app') {
      return compareHoras(a.hora_app, b.hora_app, mul)
    }
    if (col === 'diferencia_segundos') {
      const valA = a.diferencia_segundos !== null && a.diferencia_segundos !== undefined ? Number(a.diferencia_segundos) : 999999
      const valB = b.diferencia_segundos !== null && b.diferencia_segundos !== undefined ? Number(b.diferencia_segundos) : 999999
      return mul * (valA - valB)
    }
    if (col === 'monto') {
      return mul * ((Number(a.monto) || 0) - (Number(b.monto) || 0))
    }
    return 0
  })
}

function badgeColorEstatus(categoria) {
  const c = String(categoria || '').toLowerCase()
  if (c.includes('no visitado') || c.includes('pendiente')) {
    return 'bg-[#FDF0EF] text-[#C9342C] border border-[#FADCD5]'
  }
  if (c.includes('preventa') || c.includes('venta') || c.includes('compra') || c.includes('visitado activo')) {
    return 'bg-[#EBF9EF] text-[#1E6B37] border border-[#D1F2D9]'
  }
  if (c.includes('tienda cerrada') || c.includes('cerrad')) {
    return 'bg-amber-50 text-amber-700 border border-amber-200'
  }
  if (c.includes('sin dinero')) {
    return 'bg-purple-50 text-purple-700 border border-purple-200'
  }
  if (c.includes('con stock') || c.includes('stock')) {
    return 'bg-cyan-50 text-cyan-700 border border-cyan-200'
  }
  if (c.includes('ausente')) {
    return 'bg-orange-50 text-orange-700 border border-orange-200'
  }
  if (c.includes('fuera')) {
    return 'bg-[#FFF5E5] text-[#B25E00] border border-amber-200'
  }
  if (c.includes('inactivo')) {
    return 'bg-slate-100 text-slate-700 border border-slate-200'
  }
  if (c.includes('rechaz') || c.includes('no atendido')) {
    return 'bg-rose-50 text-rose-700 border border-rose-200'
  }
  return 'bg-slate-50 text-slate-700 border border-slate-200'
}

const modalFoto = reactive({
  abierto: false,
  cargando: false,
  cliente: '',
  fotos: [],
  indice: 0
})

const fotoActual = computed(() => modalFoto.fotos[modalFoto.indice] || null)

async function abrirCarruselFotos(c) {
  modalFoto.abierto = true
  modalFoto.cliente = c.cliente || 'Cliente'
  modalFoto.indice = 0
  modalFoto.cargando = false

  const listaInicial = []
  if (c.foto_url) {
    listaInicial.push({
      url: c.foto_url,
      photo_url: c.foto_url,
      fecha: `Visita actual (${c.check_in || ''})`,
      ruta: ''
    })
  }

  modalFoto.fotos = listaInicial

  if (c.cliente_id) {
    modalFoto.cargando = listaInicial.length === 0
    try {
      const res = await axios.post(`/supervisor/preventas/cumplimiento/fotos/${c.cliente_id}`)
      if (res.data?.success && Array.isArray(res.data.fotos)) {
        const urlActual = c.foto_url ? c.foto_url.trim().toLowerCase() : ''
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
      console.warn('[Visitas] No se pudieron cargar fotos históricas:', e)
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

