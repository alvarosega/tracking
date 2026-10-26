<template>
  <SupervisorLayout title="Saneamiento | Matriz BD">
    <div class="space-y-4">
      <SaneamientoSubTabs active-tab="bd" />

      <!-- Panel de Filtros Superior (Apple Glass) -->
      <div class="apple-glass-card rounded-[16px] p-3 border border-black/[0.06] bg-white/70 backdrop-blur-md">
        <div class="flex flex-wrap items-center justify-between gap-2.5 text-[11px]">
          
          <div class="flex flex-wrap items-center gap-2">
            <!-- Canal -->
            <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1.5 bg-white/80 border border-black/[0.06]">
              <label class="text-[10px] uppercase font-bold text-[#6E6E73] tracking-wider">Canal:</label>
              <select
                v-model="filtroCanal"
                @change="alCambiarCanal"
                class="bg-transparent font-bold text-[#0071E3] focus:outline-none cursor-pointer text-[11px]"
              >
                <option value="TDB">TDB (Por defecto)</option>
                <option value="TODOS">Todos los Canales</option>
                <option
                  v-for="c in canalesDisponibles.filter(c => c !== 'TDB' && c !== 'TODOS')"
                  :key="c"
                  :value="c"
                >
                  {{ c }}
                </option>
              </select>
            </div>

            <!-- Ruta -->
            <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1.5">
              <label class="text-[10px] uppercase font-bold text-[#6E6E73] tracking-wider">Ruta:</label>
              <select
                v-model="filtroRuta"
                class="bg-transparent font-bold text-[#1D1D1F] focus:outline-none cursor-pointer text-[11px] max-w-[140px]"
              >
                <option value="TODAS">Todas las Rutas</option>
                <option v-for="r in rutasDisponibles" :key="r.ruta" :value="r.ruta">
                  {{ r.ruta }}
                </option>
              </select>
            </div>

            <!-- Día -->
            <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1.5">
              <label class="text-[10px] uppercase font-bold text-[#6E6E73] tracking-wider">Día:</label>
              <select
                v-model="filtroDia"
                class="bg-transparent font-bold text-[#1D1D1F] focus:outline-none cursor-pointer text-[11px]"
              >
                <option value="TODOS">Todos los Días</option>
                <option value="lunes">Lunes</option>
                <option value="martes">Martes</option>
                <option value="miercoles">Miércoles</option>
                <option value="jueves">Jueves</option>
                <option value="viernes">Viernes</option>
                <option value="sabado">Sábado</option>
              </select>
            </div>

            <!-- Falla / Tipo Error -->
            <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1.5 bg-neutral-100/80">
              <label class="text-[10px] uppercase font-bold text-[#6E6E73] tracking-wider">Falla:</label>
              <select
                v-model="filtroTipoDato"
                class="bg-transparent font-bold text-[#1D1D1F] focus:outline-none cursor-pointer text-[11px]"
              >
                <option value="TODOS">Todas las Fallas</option>
                <option value="SOLO_DEFECTUOSOS">Solo con Datos Incorrectos</option>
                <option value="FALTA_APELLIDO">Sin Apellido / Nombre Incompleto</option>
                <option value="SIN_TELEFONO">Sin Teléfono / Celular</option>
                <option value="SIN_NIT_CI">Sin NIT / Carnet de Identidad</option>
                <option value="SIN_DIRECCION">Sin Dirección / Sin Número</option>
                <option value="SIN_COORDENADAS">Sin Coordenadas GPS (0.0 o nulo)</option>
                <option value="SIN_DIA">Sin Día de Visita</option>
                <option value="CORRECTOS">Datos 100% Correctos</option>
              </select>
            </div>

            <!-- Botón Consultar Principal -->
            <button
              type="button"
              @click="consultarMatriz"
              :disabled="cargando"
              class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-[10px] text-[11px] font-semibold text-white bg-[#0071E3] hover:bg-[#0077ED] active:scale-[0.98] transition-all duration-150 shadow-[0_2px_8px_rgba(0,113,227,0.35)] cursor-pointer disabled:opacity-75"
            >
              <svg v-if="cargando" class="w-3.5 h-3.5 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
              </svg>
              <svg v-else class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
              <span>{{ cargando ? 'Consultando...' : 'Consultar Matriz' }}</span>
            </button>
          </div>

        </div>
      </div>

      <!-- Tarjetas Métricas Ejecutivas -->
      <div v-if="resumenGeneral" class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="apple-glass-card rounded-[14px] p-3 border border-black/[0.06] bg-white/70">
          <div class="text-[10px] uppercase font-bold text-[#86868B] font-mono">Universo Clientes</div>
          <div class="text-xl font-black text-ink mt-0.5 font-mono">{{ resumenGeneral.total_clientes }}</div>
          <div class="text-[10px] text-[#86868B] mt-0.5">{{ resumenGeneral.total_rutas }} rutas analizadas</div>
        </div>

        <div class="apple-glass-card rounded-[14px] p-3 border border-rose-200 bg-rose-50/50">
          <div class="text-[10px] uppercase font-bold text-rose-700 font-mono">Clientes Defectuosos</div>
          <div class="text-xl font-black text-rose-600 mt-0.5 font-mono">{{ resumenGeneral.total_defectuosos }}</div>
          <div class="text-[10px] text-rose-700 font-bold mt-0.5 font-mono">{{ resumenGeneral.porcentaje_defectuoso }}% del padrón</div>
        </div>

        <div class="apple-glass-card rounded-[14px] p-3 border border-emerald-200 bg-emerald-50/50">
          <div class="text-[10px] uppercase font-bold text-emerald-700 font-mono">Clientes Correctos</div>
          <div class="text-xl font-black text-emerald-600 mt-0.5 font-mono">{{ resumenGeneral.total_correctos }}</div>
          <div class="text-[10px] text-emerald-700 font-bold mt-0.5 font-mono">
            {{ (100 - resumenGeneral.porcentaje_defectuoso).toFixed(1) }}% de completitud
          </div>
        </div>

        <div class="apple-glass-card rounded-[14px] p-3 border border-blue-200 bg-blue-50/50">
          <div class="text-[10px] uppercase font-bold text-blue-700 font-mono">Sin Coordenadas GPS</div>
          <div class="text-xl font-black text-blue-600 mt-0.5 font-mono">
            {{ resumenGeneral.totales_anomalias?.sin_coordenadas || 0 }}
          </div>
          <div class="text-[10px] text-blue-700 mt-0.5">Ubicación 0.0 o faltante</div>
        </div>
      </div>

      <!-- Píldoras de Anomalías Globales -->
      <div v-if="resumenGeneral?.totales_anomalias" class="apple-glass-card p-2.5 rounded-[14px] shadow-subtle flex flex-wrap items-center gap-2 text-[10px] font-mono border border-black/[0.06] bg-white/70">
        <span class="text-[#86868B] uppercase font-bold text-[9px] mr-1">Fallas Detectadas:</span>
        <div class="px-2 py-0.5 rounded bg-white border border-neutral-200 text-ink shadow-2xs">
          Sin Apellido: <strong class="text-purple-600">{{ resumenGeneral.totales_anomalias.falta_apellido }}</strong>
        </div>
        <div class="px-2 py-0.5 rounded bg-white border border-neutral-200 text-ink shadow-2xs">
          Sin Teléfono: <strong class="text-amber-600">{{ resumenGeneral.totales_anomalias.sin_telefono }}</strong>
        </div>
        <div class="px-2 py-0.5 rounded bg-white border border-neutral-200 text-ink shadow-2xs">
          Sin NIT/CI: <strong class="text-blue-600">{{ resumenGeneral.totales_anomalias.sin_nit_ci }}</strong>
        </div>
        <div class="px-2 py-0.5 rounded bg-white border border-neutral-200 text-ink shadow-2xs">
          Sin Dirección/Nro: <strong class="text-rose-600">{{ resumenGeneral.totales_anomalias.sin_direccion }}</strong>
        </div>
        <div class="px-2 py-0.5 rounded bg-white border border-neutral-200 text-ink shadow-2xs">
          Sin GPS: <strong class="text-red-700">{{ resumenGeneral.totales_anomalias.sin_coordenadas }}</strong>
        </div>
        <div class="px-2 py-0.5 rounded bg-white border border-neutral-200 text-ink shadow-2xs">
          Sin Día: <strong class="text-gray-600">{{ resumenGeneral.totales_anomalias.sin_dia }}</strong>
        </div>
      </div>

      <!-- Sección 1: Matriz Consolidada de Rutas x Días -->
      <div class="apple-glass-card rounded-[16px] shadow-subtle overflow-hidden border border-black/[0.06] bg-white/70 backdrop-blur-md">
        <div class="px-4 py-2.5 border-b border-neutral-100 flex items-center justify-between bg-white/80">
          <div class="flex items-center gap-2">
            <span class="font-bold text-[12px] text-ink">Matriz de Rutas & Diagnóstico por Jornada</span>
            <span class="text-[10px] font-mono text-[#86868B]">
              Haz clic en cualquier ruta para filtrar los clientes en la tabla inferior
            </span>
          </div>
          <span class="text-[10px] font-mono text-ink-secondary">{{ matrizRutas.length }} rutas</span>
        </div>

        <div class="overflow-x-auto custom-scroll max-h-80">
          <table class="w-full text-left text-[11px]">
            <thead class="sticky top-0 bg-neutral-50/95 backdrop-blur-sm text-ink-secondary uppercase text-[9px] font-mono tracking-wider border-b border-neutral-200 z-10">
              <tr>
                <th class="py-2.5 px-3">Ruta</th>
                <th class="py-2.5 px-3">Vendedor</th>
                <th class="py-2.5 px-3 text-right">Total PDVs</th>
                <th class="py-2.5 px-3 text-right">Defectuosos</th>
                <th class="py-2.5 px-3 text-center">% Falla</th>
                <th class="py-2.5 px-2 text-center">Lunes</th>
                <th class="py-2.5 px-2 text-center">Martes</th>
                <th class="py-2.5 px-2 text-center">Miércoles</th>
                <th class="py-2.5 px-2 text-center">Jueves</th>
                <th class="py-2.5 px-2 text-center">Viernes</th>
                <th class="py-2.5 px-2 text-center">Sábado</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100 bg-white/60">
              <tr v-if="matrizRutas.length === 0">
                <td colspan="11" class="py-10 text-center text-[#86868B] font-medium">
                  {{ cargando ? 'Procesando matriz de auditoría...' : 'No hay datos de rutas disponibles para los filtros seleccionados.' }}
                </td>
              </tr>
              <tr
                v-for="r in matrizRutas"
                :key="r.ruta"
                @click="seleccionarRutaEnMatriz(r.ruta)"
                class="hover:bg-blue-50/50 cursor-pointer transition-colors"
                :class="rutaFiltroActiva === r.ruta ? 'bg-blue-50/80 font-bold' : ''"
              >
                <td class="py-2 px-3 font-mono font-bold text-ink">{{ r.ruta }}</td>
                <td class="py-2 px-3 text-ink-secondary truncate max-w-[140px]">{{ r.vendedor }}</td>
                <td class="py-2 px-3 font-mono text-right font-semibold">{{ r.total_clientes }}</td>
                <td class="py-2 px-3 font-mono text-right text-rose-600 font-bold">{{ r.total_defectuosos }}</td>
                <td class="py-2 px-3 text-center">
                  <span
                    class="px-2 py-0.5 rounded text-[10px] font-mono font-bold"
                    :class="r.porcentaje_defectuoso > 50 ? 'bg-rose-100 text-rose-800' : (r.porcentaje_defectuoso > 20 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800')"
                  >
                    {{ r.porcentaje_defectuoso }}%
                  </span>
                </td>
                <!-- Días de la semana -->
                <td
                  v-for="dia in DIAS_LISTA"
                  :key="dia"
                  class="py-2 px-2 text-center font-mono text-[10px]"
                >
                  <div v-if="r.dias && r.dias[dia] && r.dias[dia].total > 0">
                    <span :class="r.dias[dia].defectuosos > 0 ? 'text-rose-600 font-bold' : 'text-neutral-500'">
                      {{ r.dias[dia].defectuosos }}
                    </span>
                    <span class="text-neutral-400 text-[8px]">/{{ r.dias[dia].total }}</span>
                  </div>
                  <span v-else class="text-neutral-300">-</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Sección 2: Detalle de Clientes Auditados -->
      <div class="apple-glass-card rounded-[16px] shadow-subtle overflow-hidden border border-black/[0.06] bg-white/70 backdrop-blur-md">
        <div class="px-4 py-2.5 border-b border-neutral-100 flex flex-wrap items-center justify-between gap-2 bg-white/80">
          <div class="flex items-center gap-2">
            <span class="font-bold text-[12px] text-ink">Clientes Auditados en Detalle</span>
            <span v-if="rutaFiltroActiva" class="text-[10px] font-mono text-blue-700 bg-blue-50 px-2 py-0.5 rounded border border-blue-200">
              Ruta: {{ rutaFiltroActiva }}
              <button type="button" @click.stop="rutaFiltroActiva = null" class="ml-1 text-blue-900 font-bold hover:underline">✕</button>
            </span>
          </div>

          <div class="flex items-center gap-2">
            <input
              v-model="filtroBusquedaClientes"
              type="text"
              placeholder="Buscar cliente, ID o dirección..."
              class="text-[10px] bg-neutral-100 rounded-[8px] px-2.5 py-1 focus:outline-none font-medium border border-neutral-200 w-56 font-mono"
            />
            <span class="text-[10px] font-mono text-ink-secondary">{{ clientesFiltrados.length }} registros</span>
          </div>
        </div>

        <div class="overflow-x-auto custom-scroll max-h-[500px]">
          <table class="w-full text-left text-[11px]">
            <thead class="sticky top-0 bg-neutral-50/95 backdrop-blur-sm text-ink-secondary uppercase text-[9px] font-mono tracking-wider border-b border-neutral-200 z-10">
              <tr>
                <th class="py-2.5 px-3">Cliente</th>
                <th class="py-2.5 px-3">ID</th>
                <th class="py-2.5 px-3">Ruta / Vendedor</th>
                <th class="py-2.5 px-3">Dirección / Nro</th>
                <th class="py-2.5 px-3">Contacto</th>
                <th class="py-2.5 px-3">NIT / CI</th>
                <th class="py-2.5 px-3">GPS Coordenadas</th>
                <th class="py-2.5 px-3 text-center">Severidad</th>
                <th class="py-2.5 px-3 text-right">Fotos</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-neutral-100 bg-white/60">
              <tr v-if="clientesFiltrados.length === 0">
                <td colspan="9" class="py-12 text-center text-[#86868B] font-medium">
                  No se encontraron clientes bajo los filtros aplicados.
                </td>
              </tr>
              <tr
                v-for="cli in clientesFiltrados"
                :key="cli.cliente_id"
                class="hover:bg-neutral-50/70 transition-colors"
              >
                <td class="py-2 px-3 font-bold text-ink">
                  <div class="truncate max-w-xs">{{ cli.cliente || 'SIN NOMBRE' }}</div>
                  <div v-if="cli.falta_apellido" class="text-[9px] text-purple-600 font-mono font-bold">
                    ⚠️ Sin apellido
                  </div>
                </td>
                <td class="py-2 px-3 font-mono text-ink-secondary">{{ cli.cliente_id }}</td>
                <td class="py-2 px-3 font-mono">
                  <span class="font-bold text-ink">{{ cli.ruta }}</span>
                  <div class="text-[9px] text-neutral-500 truncate max-w-[120px]">{{ cli.vendedor }}</div>
                </td>
                <td class="py-2 px-3 text-neutral-600 truncate max-w-xs">
                  <div :class="cli.sin_direccion ? 'text-rose-600 font-semibold' : ''">
                    {{ cli.direccion || 'Sin dirección' }}
                  </div>
                  <div class="text-[9px] text-neutral-400 font-mono">Nro: {{ cli.nro || 'S/N' }}</div>
                </td>
                <td class="py-2 px-3 font-mono">
                  <div :class="cli.sin_telefono ? 'text-amber-600 font-bold' : 'text-neutral-600'">
                    {{ cli.telefono || cli.celular || 'Sin número' }}
                  </div>
                </td>
                <td class="py-2 px-3 font-mono">
                  <div :class="cli.sin_nit_ci ? 'text-blue-600 font-bold' : 'text-neutral-600'">
                    {{ cli.nit || cli.ci || 'Sin NIT/CI' }}
                  </div>
                </td>
                <td class="py-2 px-3 font-mono">
                  <span v-if="cli.sin_coordenadas" class="px-1.5 py-0.5 rounded bg-rose-50 text-rose-700 font-bold border border-rose-200 text-[9px]">
                    Sin GPS
                  </span>
                  <span v-else class="text-neutral-500 text-[10px]">
                    {{ cli.latitud ? cli.latitud.toFixed(4) : '-' }}, {{ cli.longitud ? cli.longitud.toFixed(4) : '-' }}
                  </span>
                </td>
                <td class="py-2 px-3 text-center">
                  <span
                    class="px-2 py-0.5 rounded text-[9px] font-mono font-bold"
                    :class="{
                      'bg-rose-100 text-rose-800 border border-rose-200': cli.severidad === 'CRITICO',
                      'bg-amber-100 text-amber-800 border border-amber-200': cli.severidad === 'MEDIO',
                      'bg-yellow-100 text-yellow-800 border border-yellow-200': cli.severidad === 'LEVE',
                      'bg-emerald-100 text-emerald-800 border border-emerald-200': cli.severidad === 'OK'
                    }"
                  >
                    {{ cli.severidad }}
                  </span>
                </td>
                <td class="py-2 px-3 text-right">
                  <button
                    type="button"
                    @click="abrirModalFotos(cli)"
                    class="px-2 py-0.5 bg-white hover:bg-neutral-100 text-ink rounded font-bold border border-neutral-200 shadow-2xs text-[10px] cursor-pointer"
                  >
                    Fotos
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Modal de Fotos -->
      <ClienteFotosModal
        :visible="modalFotosVisible"
        :cliente-id="clienteSeleccionadoFotos?.cliente_id"
        :cliente-nombre="clienteSeleccionadoFotos?.cliente || clienteSeleccionadoFotos?.nombre"
        :ruta="clienteSeleccionadoFotos?.ruta"
        @close="modalFotosVisible = false"
      />
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import axios from 'axios'
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue'
import SaneamientoSubTabs from './Components/SaneamientoSubTabs.vue'
import ClienteFotosModal from './Components/ClienteFotosModal.vue'

const props = defineProps({
  catalogo: {
    type: Object,
    default: () => ({ canales: [], rutas: [] }),
  },
})

const DIAS_LISTA = ['lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado']

const cargando = ref(false)
const filtroCanal = ref('TDB')
const filtroRuta = ref('TODAS')
const filtroDia = ref('TODOS')
const filtroTipoDato = ref('TODOS')
const filtroBusquedaClientes = ref('')
const rutaFiltroActiva = ref(null)

const resumenGeneral = ref(null)
const matrizRutas = ref([])
const clientes = ref([])

const modalFotosVisible = ref(false)
const clienteSeleccionadoFotos = ref(null)

const canalesDisponibles = computed(() => props.catalogo.canales || [])

const rutasDisponibles = computed(() => {
  const rutas = props.catalogo.rutas || []
  if (filtroCanal.value === 'TODOS') return rutas
  return rutas.filter(r => r.canal === filtroCanal.value)
})

const clientesFiltrados = computed(() => {
  let list = clientes.value

  if (rutaFiltroActiva.value) {
    list = list.filter(c => c.ruta === rutaFiltroActiva.value)
  }

  if (filtroBusquedaClientes.value.trim()) {
    const q = filtroBusquedaClientes.value.trim().toLowerCase()
    list = list.filter(c =>
      c.cliente.toLowerCase().includes(q) ||
      String(c.cliente_id).includes(q) ||
      c.direccion.toLowerCase().includes(q) ||
      c.ruta.toLowerCase().includes(q)
    )
  }

  return list
})

onMounted(() => {
  consultarMatriz()
})

function alCambiarCanal() {
  filtroRuta.value = 'TODAS'
  consultarMatriz()
}

async function consultarMatriz() {
  cargando.value = true
  rutaFiltroActiva.value = null
  try {
    const rutasParam = filtroRuta.value !== 'TODAS'
      ? [filtroRuta.value]
      : (filtroCanal.value !== 'TODOS' ? rutasDisponibles.value.map(r => r.ruta) : null)

    const { data } = await axios.post(route('supervisor.saneamiento.bd.matriz'), {
      canal: filtroCanal.value !== 'TODOS' ? filtroCanal.value : null,
      rutas: rutasParam,
      dia: filtroDia.value !== 'TODOS' ? filtroDia.value : null,
      tipo_error: filtroTipoDato.value !== 'TODOS' ? filtroTipoDato.value : null,
    })

    resumenGeneral.value = data?.resumen_general || null
    matrizRutas.value = data?.matriz_rutas || []
    clientes.value = data?.clientes || []
  } catch (err) {
    console.error('Error al consultar matriz BD:', err)
  } finally {
    cargando.value = false
  }
}

function seleccionarRutaEnMatriz(ruta) {
  if (rutaFiltroActiva.value === ruta) {
    rutaFiltroActiva.value = null
  } else {
    rutaFiltroActiva.value = ruta
  }
}

function abrirModalFotos(cli) {
  clienteSeleccionadoFotos.value = cli
  modalFotosVisible.value = true
}
</script>

