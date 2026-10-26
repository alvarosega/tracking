<template>
  <div class="apple-glass-card rounded-[16px] overflow-hidden select-none font-sans">
    <!-- Encabezado Compacto y Parámetros -->
    <div class="px-3.5 py-2.5 border-b border-black/[0.06] flex flex-wrap items-center justify-between gap-2 bg-white/40">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <h3 class="text-[13px] font-bold text-[#1D1D1F] tracking-tight leading-tight">
            Pizarra Modelo Unilever · KPI 6: Pedido de Reposición (Canasta Habitual 60%)
          </h3>
          <span class="px-2 py-0.5 rounded-[5px] text-[10px] font-bold text-[#1E6B37] bg-[#EBF9EF] border border-[#D1F2D9]">
            15 pts Base · Histórico 13 Semanas por Cliente (fact_ventas + fact_preventas)
          </span>
        </div>
        <p class="text-[11px] text-[#6E6E73] font-medium mt-0.5">
          Regla: Coincidencia de recompra de SKUs habituales comprados en las últimas {{ semanasFiltro }} semanas (&ge; 1 compra histórica). Umbral exigido: &ge; 60.0% de reposición.
        </p>
      </div>

      <div class="flex items-center gap-2.5 text-[11px]">
        <!-- Selector de Ventana Histórica -->
        <div class="flex items-center gap-1.5 apple-control-pill px-2.5 py-1 rounded-[8px] text-[#6E6E73] font-medium">
          <span>Ventana:</span>
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

    <!-- Tabla Principal -->
    <div class="overflow-x-auto custom-scroll max-h-[calc(100vh-230px)] min-h-[380px]">
      <table class="w-full text-left border-collapse min-w-[920px] text-[12px]">
        <thead class="sticky top-0 z-20 border-b border-black/[0.06] text-[10px] font-bold uppercase tracking-wider text-[#6E6E73] bg-[#F8F8FA]">
          <tr>
            <th class="px-2.5 py-2 text-center sticky left-0 z-30 w-12 border-r border-black/[0.05] bg-[#F8F8FA]">Pos</th>
            <th class="px-3 py-2 sticky left-12 z-30 w-28 border-r border-black/[0.05] bg-[#F8F8FA]">Ruta</th>
            <th class="px-3 py-2 sticky left-[112px] z-30 min-w-[180px] border-r border-black/[0.06] shadow-[2px_0_4px_-2px_rgba(0,0,0,0.04)] bg-[#F8F8FA]">Preventista</th>
            <th class="px-3 py-2 text-center border-r border-black/[0.04]">Compradores</th>
            <th class="px-3 py-2 text-center border-r border-black/[0.04]">SKUs Esperados</th>
            <th class="px-3 py-2 text-center border-r border-black/[0.04]">Match Repo</th>
            <th class="px-3 py-2 text-center border-r border-black/[0.04]">Fuera Canasta</th>
            <th class="px-3 py-2 text-center border-r border-black/[0.04]">% Reposición</th>
            <th class="px-3 py-2 text-center border-r border-black/[0.04]">Estatus</th>
            <th class="px-3 py-2 text-right">Puntos (15)</th>
          </tr>
        </thead>

        <tbody class="divide-y divide-black/[0.04]">
          <template v-if="store.loadingRanking">
            <tr v-for="i in 10" :key="`skel-rep-${i}`">
              <td class="px-2.5 py-2 sticky left-0"><div class="h-3.5 w-5 mx-auto skeleton"></div></td>
              <td class="px-3 py-2 sticky left-12"><div class="h-3.5 w-16 skeleton"></div></td>
              <td class="px-3 py-2 sticky left-[112px]"><div class="h-3.5 w-32 skeleton"></div></td>
              <td v-for="c in 6" :key="c" class="px-3 py-2 text-center"><div class="h-4 w-12 mx-auto skeleton"></div></td>
              <td class="px-3 py-2 text-right"><div class="h-4 w-8 ml-auto skeleton"></div></td>
            </tr>
          </template>

          <tr v-else-if="rutasOrdenadas.length === 0">
            <td colspan="10" class="p-8 text-center text-[#86868B] text-[12px] font-medium">
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
                <td class="px-2.5 py-1.5 text-center font-mono text-[11px] font-semibold text-[#86868B] sticky left-0 z-10 border-r border-black/[0.04] bg-white/70 backdrop-blur-sm">
                  {{ idx + 1 }}
                </td>

                <!-- Ruta -->
                <td class="px-3 py-1.5 font-bold text-[#1D1D1F] text-[12px] sticky left-12 z-10 border-r border-black/[0.04] bg-white/70 backdrop-blur-sm whitespace-nowrap">
                  <div class="flex items-center gap-1.5">
                    <svg
                      class="w-3.5 h-3.5 text-[#86868B] transition-transform duration-200 shrink-0"
                      :class="acordeonAbierto[r.ruta] ? 'rotate-90 text-[#0071E3]' : ''"
                      fill="none"
                      stroke="currentColor"
                      viewBox="0 0 24 24"
                    >
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                    <span>{{ r.ruta }}</span>
                  </div>
                </td>

                <!-- Preventista -->
                <td class="px-3 py-1.5 text-[#6E6E73] font-medium text-[11px] sticky left-[112px] z-10 border-r border-black/[0.05] bg-white/70 backdrop-blur-sm truncate max-w-[200px]" :title="r.vendedor">
                  {{ r.vendedor || 'No Asignado' }}
                </td>

                <!-- Compradores -->
                <td class="px-3 py-1.5 text-center font-mono text-[#1D1D1F] border-r border-black/[0.04]">
                  {{ getCompradores(r) }}
                </td>

                <!-- SKUs Esperados -->
                <td class="px-3 py-1.5 text-center font-mono text-[#1D1D1F] border-r border-black/[0.04]">
                  {{ getSkusEsperados(r) }}
                </td>

                <!-- Match Repo -->
                <td class="px-3 py-1.5 text-center font-mono font-bold text-[#1E6B37] border-r border-black/[0.04]">
                  {{ getSkusMatch(r) }}
                </td>

                <!-- Fuera Canasta -->
                <td class="px-3 py-1.5 text-center font-mono text-[#6E6E73] border-r border-black/[0.04]">
                  {{ getSkusFuera(r) }}
                </td>

                <!-- % Reposición -->
                <td class="px-3 py-1.5 text-center font-mono font-bold border-r border-black/[0.04]">
                  <span
                    v-if="getPctReposicion(r) !== null"
                    :class="getPctReposicion(r) >= 60.0 ? 'text-[#1E6B37]' : 'text-[#C9342C]'"
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
                    :class="getPctReposicion(r) >= 60.0
                      ? 'bg-[#EBF9EF] text-[#1E6B37] border border-[#D1F2D9]'
                      : 'bg-[#FDF0EF] text-[#C9342C] border border-[#FADCD5]'"
                  >
                    {{ getPctReposicion(r) >= 60.0 ? '≥ 60% CUMPLE' : '< 60% DÉFICIT' }}
                  </span>
                  <span v-else class="text-[#86868B] text-[11px] font-mono">—</span>
                </td>

                <!-- Puntos (15) -->
                <td class="px-3 py-1.5 text-right font-mono font-bold text-[12px]">
                  <span
                    v-if="getCumpleKpi(r)"
                    class="inline-flex items-center justify-center min-w-[28px] px-1.5 py-0.5 rounded-[4px] text-[11px] font-bold font-mono text-[#1E6B37] bg-[#EBF9EF] border border-[#D1F2D9]"
                  >
                    +15
                  </span>
                  <span v-else class="text-[#86868B] font-mono">—</span>
                </td>
              </tr>

              <!-- Fila Acordeón: Detalle de Tiendas y Canasta Habitual -->
              <tr v-if="acordeonAbierto[r.ruta]">
                <td colspan="10" class="p-3 bg-white/70 border-b border-black/[0.06]">
                  <div v-if="cargandoDetalle[r.ruta]" class="p-4 text-center text-[#6E6E73] text-[11px] font-medium">
                    Auditando canastas de reposición para {{ r.ruta }}...
                  </div>
                  <div v-else-if="errorDetalle[r.ruta]" class="p-4 text-center text-[#C9342C] text-[11px] font-semibold">
                    {{ errorDetalle[r.ruta] }}
                  </div>
                  <div v-else-if="detallePorRuta[r.ruta]" class="space-y-3">
                    <div class="apple-glass-card rounded-[12px] overflow-hidden border border-black/[0.06]">
                      <!-- Header de la ruta auditada -->
                      <div class="px-3.5 py-2 border-b border-black/[0.06] flex flex-wrap items-center justify-between gap-2 bg-[#F8F8FA] text-[11px]">
                        <div class="flex items-center gap-2">
                          <span class="font-bold text-[#1D1D1F]">
                            Auditoría de Canastas de Tiendas: {{ r.ruta }}
                          </span>
                          <span class="text-[#6E6E73]">· {{ detallePorRuta[r.ruta].vendedor || r.vendedor }}</span>
                        </div>
                        <div class="flex items-center gap-2 text-[10px] font-mono">
                          <span class="apple-control-pill px-2 py-0.5 rounded-[6px] text-[#6E6E73]">
                            Compradores: <strong class="text-[#1D1D1F]">{{ (detallePorRuta[r.ruta].tiendas || []).length }}</strong>
                          </span>
                          <span class="apple-control-pill px-2 py-0.5 rounded-[6px] text-[#6E6E73]">
                            SKUs Base: <strong class="text-[#1D1D1F]">{{ detallePorRuta[r.ruta].totales?.skus_esperados_total || 0 }}</strong>
                          </span>
                          <span class="apple-control-pill px-2 py-0.5 rounded-[6px] text-[#1E6B37]">
                            Match: <strong class="text-[#1E6B37]">{{ detallePorRuta[r.ruta].totales?.skus_con_match_total || 0 }}</strong>
                          </span>
                          <span class="apple-control-pill px-2 py-0.5 rounded-[6px] font-bold" :class="(detallePorRuta[r.ruta].totales?.porcentaje_reposicion_ruta || 0) >= 60.0 ? 'text-[#1E6B37]' : 'text-[#C9342C]'">
                            {{ Number(detallePorRuta[r.ruta].totales?.porcentaje_reposicion_ruta || 0).toFixed(1) }}%
                          </span>
                        </div>
                      </div>

                      <div v-if="(detallePorRuta[r.ruta].tiendas || []).length === 0" class="p-6 text-center text-[#86868B] text-[11px]">
                        No se encontraron tiendas compradoras evaluables para reposición en la fecha.
                      </div>

                      <!-- Lista de tiendas compradoras -->
                      <div v-else class="divide-y divide-black/[0.05] max-h-96 overflow-y-auto custom-scroll">
                        <div
                          v-for="t in detallePorRuta[r.ruta].tiendas"
                          :key="t.cliente_id"
                          class="p-3 text-[11px] hover:bg-black/[0.01] transition-colors"
                        >
                          <!-- Cabecera de la tienda -->
                          <div class="flex flex-wrap items-center justify-between gap-2 mb-1.5">
                            <div>
                              <strong class="font-bold text-[#1D1D1F]">{{ t.cliente }}</strong>
                              <span class="text-[#6E6E73] font-mono ml-2 text-[10px]">#{{ t.cliente_id }}</span>
                              <span v-if="t.codigo_cliente" class="text-[#86868B] font-mono ml-1 text-[10px]">({{ t.codigo_cliente }})</span>
                            </div>
                            <div class="flex items-center gap-2">
                              <span class="text-[10px] font-mono text-[#6E6E73]">
                                Hoy: <strong class="text-[#1D1D1F]">Bs {{ formatBs(t.total_bs_hoy) }}</strong>
                              </span>
                              <span
                                class="px-2 py-0.5 rounded-[5px] text-[10px] font-bold font-mono"
                                :class="t.cumple
                                  ? 'bg-[#EBF9EF] text-[#1E6B37] border border-[#D1F2D9]'
                                  : 'bg-[#FDF0EF] text-[#C9342C] border border-[#FADCD5]'"
                              >
                                {{ Number(t.porcentaje_reposicion || 0).toFixed(1) }}% {{ t.cumple ? 'REPOSICIÓN' : 'DÉFICIT' }}
                              </span>
                              <span class="font-mono text-[10px] font-bold text-[#1D1D1F]">
                                Match: <strong class="text-[#1E6B37]">{{ t.skus_con_match }}</strong> / <span class="text-[#6E6E73]">{{ t.skus_esperados }}</span>
                              </span>
                            </div>
                          </div>

                          <!-- Desglose de SKUs Habituales (Recompra vs Pendiente) -->
                          <div class="mt-1">
                            <div class="text-[10px] font-semibold text-[#86868B] uppercase tracking-tight mb-1">
                              Canasta Habitual ({{ semanasFiltro }} semanas):
                            </div>
                            <div v-if="(t.skus_habituales || []).length === 0" class="text-[10px] italic text-[#86868B]">
                              Sin historial de compras en la ventana de {{ semanasFiltro }} semanas (Apertura de cliente).
                            </div>
                            <div v-else class="flex flex-wrap gap-1.5">
                              <span
                                v-for="sku in t.skus_habituales"
                                :key="sku.codigo_producto || sku.producto"
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[5px] text-[10px] font-medium shadow-xs"
                                :class="sku.recomprado_hoy
                                  ? 'bg-[#EBF9EF] text-[#1E6B37] border border-[#D1F2D9] font-bold'
                                  : 'bg-[#FDF0EF] text-[#C9342C] border border-[#FADCD5]'"
                                :title="`Semanas con compra: ${sku.semanas_con_compra || 1} · Total histórico: ${sku.unidades_acumuladas || 0} uds`"
                              >
                                <span v-if="sku.recomprado_hoy">✓</span>
                                <span v-else>✕</span>
                                <span>{{ sku.producto || sku.descripcion || sku.codigo_producto }}</span>
                                <span v-if="sku.recomprado_hoy" class="font-bold">
                                  ({{ sku.unidades_hoy || 1 }} uds)
                                </span>
                                <span v-else class="text-[9px] opacity-80">
                                  (Faltó · Sug: {{ sku.cantidad_repo || 1 }} uds)
                                </span>
                              </span>
                            </div>
                          </div>

                          <!-- Desglose de SKUs Fuera de Canasta (Ampliación) si compró productos nuevos -->
                          <div v-if="(t.skus_fuera_canasta || []).length > 0" class="mt-2">
                            <div class="text-[10px] font-semibold text-[#0071E3] uppercase tracking-tight mb-1">
                              Ampliación / Fuera de Canasta (Comprados hoy sin historial previo):
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                              <span
                                v-for="p in t.skus_fuera_canasta"
                                :key="`fuera-${p.codigo_producto || p.producto}`"
                                class="inline-flex items-center gap-1 px-2 py-0.5 rounded-[5px] text-[10px] font-medium bg-[#EFF6FF] text-[#1D4ED8] border border-[#DBEAFE] shadow-xs"
                              >
                                <span>+</span>
                                <span>{{ p.producto || p.descripcion }}</span>
                                <span class="font-bold">({{ p.unidades_hoy || p.unidades }} uds · Bs {{ formatBs(p.monto_hoy || p.monto_bs) }})</span>
                              </span>
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

watch(
  () => store.fecha,
  () => {
    Object.keys(acordeonAbierto).forEach(k => delete acordeonAbierto[k])
    Object.keys(detallePorRuta).forEach(k => delete detallePorRuta[k])
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
  return pct !== null && pct >= 60.0
}

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
  // Limpiar cache de rutas abiertas y recargar las que estén actualmente abiertas
  const rutasAbiertas = Object.keys(acordeonAbierto).filter(k => acordeonAbierto[k])
  rutasAbiertas.forEach(ruta => {
    cargarDetalleRuta(ruta, true)
  })
}
</script>
