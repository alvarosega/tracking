<template>
  <SupervisorLayout title="Centro de Ingesta">
    <div class="space-y-4 max-w-7xl mx-auto pb-8 font-sans">
      <!-- Sub-navegación -->
      <IngestaSubTabs active-tab="dashboard" />

      <!-- Header de la Vista -->
      <div class="apple-glass-card rounded-[18px] p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-sm border border-black/[0.04]">
        <div>
          <h1 class="text-[17px] font-bold text-[#1D1D1F] tracking-tight flex items-center gap-2">
            Centro de Ingesta de Datos
            <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-black/[0.05] text-[#6E6E73] border border-black/[0.08]">
              ETL & AUDITORÍA
            </span>
          </h1>
          <p class="text-[11px] text-[#86868B] mt-0.5">Auditoría del estado operativo de tablas, catálogos maestros y bitácora de cargas</p>
        </div>

        <div class="flex items-center gap-2">
          <button
            type="button"
            @click="recargar"
            :disabled="cargando"
            class="px-3 py-1.5 text-[11px] font-semibold rounded-[10px] bg-white text-[#1D1D1F] border border-black/[0.12] hover:bg-black/[0.03] transition-all cursor-pointer flex items-center gap-1.5 shadow-sm disabled:opacity-50"
          >
            <span :class="cargando ? 'animate-spin' : ''">↻</span>
            <span>Actualizar</span>
          </button>

          <div
            class="px-3 py-1.5 text-[11px] font-bold rounded-[10px] border shadow-sm transition-all"
            :class="estadoGlobalClase"
          >
            {{ estadoGlobalTexto }}
          </div>
        </div>
      </div>

      <!-- Grid de Tarjetas de Acceso Rápido -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
        <Link
          v-for="card in cardsModulos"
          :key="card.to"
          :href="route(card.route)"
          class="apple-glass-card rounded-[16px] p-3.5 shadow-sm hover:bg-white/95 transition-all flex flex-col justify-between group cursor-pointer border border-black/[0.04] hover:shadow-md"
        >
          <div>
            <div class="flex items-center justify-between mb-2">
              <span class="text-[12px] font-bold text-[#1D1D1F]">{{ card.titulo }}</span>
              <span class="text-[9px] font-mono font-bold px-1.5 py-0.5 rounded bg-black/[0.05] text-[#6E6E73] border border-black/[0.08]">
                {{ card.tabla }}
              </span>
            </div>
            <p class="text-[11px] text-[#6E6E73] leading-snug line-clamp-3">{{ card.desc }}</p>
          </div>
          <div class="text-[11px] font-bold text-[#0071E3] mt-3.5 flex items-center justify-end gap-1 group-hover:translate-x-1 transition-transform">
            Gestionar Carga →
          </div>
        </Link>
      </div>

      <!-- Tabla: Estado de Tablas del Sistema -->
      <section class="apple-glass-card rounded-[18px] shadow-sm overflow-hidden border border-black/[0.04]">
        <div class="px-4 py-3 border-b border-black/[0.06] flex items-center justify-between bg-white/80 backdrop-blur-sm">
          <div>
            <h3 class="text-[13px] font-bold text-[#1D1D1F]">Estado de Tablas del Sistema</h3>
            <p class="text-[10px] text-[#86868B]">Monitoreo de integridad, recuento de filas y ventanas de fechas</p>
          </div>
          <span class="text-[10px] font-semibold text-[#86868B] font-num bg-black/[0.04] px-2 py-0.5 rounded-full border border-black/[0.05]">
            {{ tablasList.length }} tablas monitoreadas
          </span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left border-separate border-spacing-0 font-num text-[11px]">
            <thead class="bg-black/[0.02] text-[#6E6E73] font-bold text-[9px] uppercase tracking-wider">
              <tr>
                <th class="py-2 px-3.5 border-b border-black/[0.06]">Entidad / Tabla</th>
                <th class="py-2 px-3.5 border-b border-black/[0.06] text-center">Estado</th>
                <th class="py-2 px-3.5 border-b border-black/[0.06] text-right">Registros</th>
                <th class="py-2 px-3.5 border-b border-black/[0.06]">Rango en BD (Inicio → Fin)</th>
                <th class="py-2 px-3.5 border-b border-black/[0.06]">Última Carga</th>
                <th class="py-2 px-3.5 border-b border-black/[0.06]">Archivo Origen</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-black/[0.04] bg-white/70">
              <tr v-if="cargando && tablasList.length === 0">
                <td colspan="6" class="py-10 text-center text-[#86868B] font-medium text-[11px]">
                  Consultando catálogo de tablas del sistema...
                </td>
              </tr>
              <tr v-else-if="tablasList.length === 0">
                <td colspan="6" class="py-10 text-center text-[#86868B] font-medium text-[11px]">
                  No se registraron tablas en el sistema.
                </td>
              </tr>
              <tr
                v-else
                v-for="t in tablasList"
                :key="t.tabla"
                class="hover:bg-[#0071E3]/[0.02] transition-colors"
              >
                <td class="py-2.5 px-3.5">
                  <div class="font-bold text-[#1D1D1F]">{{ t.nombre || t.tabla }}</div>
                  <div class="text-[9px] font-mono text-[#86868B]">{{ t.tabla }}</div>
                </td>
                <td class="py-2.5 px-3.5 text-center">
                  <span
                    class="px-2 py-0.5 text-[9px] font-bold rounded"
                    :class="(t.total_filas > 0 || t.total_registros > 0)
                      ? 'bg-emerald-50 text-emerald-700 border border-emerald-200'
                      : 'bg-black/[0.04] text-[#86868B] border border-black/[0.08]'"
                  >
                    {{ (t.total_filas > 0 || t.total_registros > 0) ? 'ACTIVA' : 'VACÍA' }}
                  </span>
                </td>
                <td class="py-2.5 px-3.5 text-right font-semibold text-[#1D1D1F]">
                  {{ formatNumero(t.total_filas ?? t.total_registros) }}
                </td>
                <td class="py-2.5 px-3.5">
                  <span
                    v-if="(t.rango_inicio && t.rango_fin && t.rango_inicio !== '-') || (t.rango_datos && t.rango_datos !== '-')"
                    class="px-2 py-0.5 rounded bg-blue-50 text-blue-700 font-semibold border border-blue-200 text-[10px]"
                  >
                    {{ t.rango_datos && t.rango_datos !== '-' ? t.rango_datos : (t.rango_inicio + ' → ' + t.rango_fin) }}
                  </span>
                  <span v-else class="text-[#86868B] text-[10px]">—</span>
                </td>
                <td class="py-2.5 px-3.5 text-[#6E6E73] text-[10px] whitespace-nowrap">
                  {{ t.ultima_carga || '-' }}
                </td>
                <td class="py-2.5 px-3.5 text-[#6E6E73] text-[10px] truncate max-w-xs" :title="t.ultimo_archivo || t.archivo || '-'">
                  {{ t.ultimo_archivo || t.archivo || '-' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Tabla: Historial de Cargas Recientes -->
      <section class="apple-glass-card rounded-[18px] shadow-sm overflow-hidden border border-black/[0.04]">
        <div class="px-4 py-3 border-b border-black/[0.06] flex items-center justify-between bg-white/80 backdrop-blur-sm">
          <div>
            <h3 class="text-[13px] font-bold text-[#1D1D1F]">Últimas 10 Cargas Realizadas</h3>
            <p class="text-[10px] text-[#86868B]">Bitácora de ingestas registradas en sys_cargas con balance de filas afectadas</p>
          </div>
          <span class="text-[10px] font-semibold text-[#86868B] font-num bg-black/[0.04] px-2 py-0.5 rounded-full border border-black/[0.05]">
            {{ historialList.length }} registros
          </span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left border-separate border-spacing-0 font-num text-[11px]">
            <thead class="bg-black/[0.02] text-[#6E6E73] font-bold text-[9px] uppercase tracking-wider">
              <tr>
                <th class="py-2 px-3.5 border-b border-black/[0.06]">Fecha Ingesta</th>
                <th class="py-2 px-3.5 border-b border-black/[0.06]">Archivo</th>
                <th class="py-2 px-3.5 border-b border-black/[0.06]">Tipo</th>
                <th class="py-2 px-3.5 border-b border-black/[0.06]">Rango del Archivo</th>
                <th class="py-2 px-3.5 border-b border-black/[0.06] text-right">Leídas</th>
                <th class="py-2 px-3.5 border-b border-black/[0.06] text-right">Nuevas</th>
                <th class="py-2 px-3.5 border-b border-black/[0.06] text-right">Alteradas</th>
                <th class="py-2 px-3.5 border-b border-black/[0.06] text-right">Alertas</th>
                <th class="py-2 px-3.5 border-b border-black/[0.06] text-right">Omitidas</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-black/[0.04] bg-white/70">
              <tr v-if="cargando && historialList.length === 0">
                <td colspan="9" class="py-10 text-center text-[#86868B] font-medium text-[11px]">
                  Cargando bitácora de ingestas...
                </td>
              </tr>
              <tr v-else-if="historialList.length === 0">
                <td colspan="9" class="py-10 text-center text-[#86868B] font-medium text-[11px]">
                  No se registran cargas en la bitácora sys_cargas.
                </td>
              </tr>
              <tr
                v-else
                v-for="h in historialList"
                :key="h.id"
                class="hover:bg-[#0071E3]/[0.02] transition-colors"
              >
                <td class="py-2.5 px-3.5 text-[#1D1D1F] font-semibold whitespace-nowrap">
                  {{ h.fecha_ingesta || h.creado_en || h.fecha }}
                </td>
                <td class="py-2.5 px-3.5 text-[#1D1D1F] truncate max-w-xs" :title="h.archivo_original">
                  {{ h.archivo_original }}
                </td>
                <td class="py-2.5 px-3.5">
                  <span class="px-1.5 py-0.5 rounded text-[9px] font-mono font-bold bg-black/[0.05] text-[#6E6E73] border border-black/[0.08]">
                    {{ h.tipo_entidad }}
                  </span>
                </td>
                <td class="py-2.5 px-3.5 text-[10px]">
                  <span v-if="h.rango_inicio && h.rango_fin && h.rango_inicio !== '-'" class="px-1.5 py-0.5 rounded bg-black/[0.04] text-[#1D1D1F] font-medium border border-black/[0.06]">
                    {{ h.rango_inicio }} al {{ h.rango_fin }}
                  </span>
                  <span v-else class="text-[#86868B]">—</span>
                </td>
                <td class="py-2.5 px-3.5 text-right font-medium text-[#1D1D1F]">{{ formatNumero(h.filas_leidas) }}</td>
                <td class="py-2.5 px-3.5 text-right font-semibold text-emerald-700">{{ formatNumero(h.filas_nuevas) }}</td>
                <td class="py-2.5 px-3.5 text-right font-semibold text-amber-600">{{ formatNumero(h.filas_actualizadas) }}</td>
                <td class="py-2.5 px-3.5 text-right font-semibold text-red-600">{{ formatNumero(h.filas_con_discrepancia) }}</td>
                <td class="py-2.5 px-3.5 text-right text-[#86868B]">{{ formatNumero(h.filas_ignoradas) }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import axios from 'axios'
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue'
import IngestaSubTabs from './Components/IngestaSubTabs.vue'

const props = defineProps({
  tablas: {
    type: Array,
    default: () => [],
  },
  sistemaListo: {
    type: Boolean,
    default: false,
  },
  tablasCargadas: {
    type: Number,
    default: 0,
  },
  totalTablas: {
    type: Number,
    default: 4,
  },
  historial: {
    type: Array,
    default: () => [],
  },
  submoduloActivo: {
    type: String,
    default: 'dashboard',
  },
})

const tablasList = ref([...props.tablas])
const historialList = ref([...props.historial])
const cargando = ref(false)
const errorMsg = ref(null)

const cardsModulos = [
  { route: 'supervisor.ingesta.ventas', titulo: 'Ventas Diarias', tabla: 'fact_ventas', desc: 'Carga consolidada con protección estricta contra duplicados e inventario de discrepancias.' },
  { route: 'supervisor.ingesta.preventas', titulo: 'Preventas Intra-día', tabla: 'fact_preventas', desc: 'Cortes periódicos con detección de ediciones y aislamiento de pedidos cancelados.' },
  { route: 'supervisor.ingesta.ruteo', titulo: 'Plan de Ruteo', tabla: 'pan_ruteo', desc: 'Versionado de rutas maestras con preservación de snapshots inmutables y auditoría.' },
  { route: 'supervisor.ingesta.visitas', titulo: 'Visitas Operativas', tabla: 'fact_visitas', desc: 'Marcajes, tiempos de permanencia y geocercas en punto de venta.' },
  { route: 'supervisor.ingesta.inventario', titulo: 'Stock Actual', tabla: 'inv_stock', desc: 'Existencias por almacén con preservación de histórico de snapshots.' },
]

const estadoGlobalTexto = computed(() => {
  if (cargando.value) return 'Consultando estado...'
  if (errorMsg.value) return 'Conexión degradada'
  const activas = tablasList.value.filter(t => (t.total_filas > 0 || t.total_registros > 0)).length
  return `Sistema Operativo (${activas}/${tablasList.value.length || props.totalTablas})`
})

const estadoGlobalClase = computed(() => {
  if (cargando.value) return 'bg-black/[0.04] text-[#6E6E73] border-black/[0.08]'
  if (errorMsg.value) return 'bg-red-50 text-red-700 border-red-200'
  return 'bg-emerald-50 text-emerald-700 border-emerald-200'
})

function formatNumero(val) {
  if (val === null || val === undefined) return '0'
  return Number(val).toLocaleString('es-BO')
}

async function recargar() {
  cargando.value = true
  errorMsg.value = null
  try {
    const [resTablas, resHistorial] = await Promise.all([
      axios.get(route('supervisor.ingesta.tablas-estado')),
      axios.get(route('supervisor.ingesta.cargas-historial')),
    ])
    tablasList.value = resTablas.data?.tablas || []
    historialList.value = resHistorial.data?.cargas || []
  } catch (err) {
    errorMsg.value = err.message || 'Error al actualizar estado.'
  } finally {
    cargando.value = false
  }
}
</script>

