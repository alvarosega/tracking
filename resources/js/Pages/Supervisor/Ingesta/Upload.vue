<template>
  <SupervisorLayout :title="titulo">
    <div class="space-y-4 max-w-4xl mx-auto pb-8 font-sans">
      <!-- Sub-navegación -->
      <IngestaSubTabs :active-tab="entidad" />

      <!-- Encabezado con Botón Volver y Apple Glass Card -->
      <div class="apple-glass-card rounded-[18px] p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-sm border border-black/[0.04]">
        <div class="flex items-center gap-3">
          <Link
            :href="route('supervisor.ingesta.index')"
            class="px-2.5 py-1 text-[11px] font-semibold rounded-[10px] bg-white text-[#6E6E73] border border-black/[0.12] hover:text-[#1D1D1F] hover:bg-black/[0.03] transition-all flex items-center gap-1 shadow-sm cursor-pointer"
          >
            ← Volver
          </Link>
          <div>
            <h1 class="text-[17px] font-bold text-[#1D1D1F] tracking-tight flex items-center gap-2">
              {{ titulo }}
              <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded bg-black/[0.05] text-[#6E6E73] border border-black/[0.08]">
                {{ tabla }}
              </span>
            </h1>
            <p class="text-[11px] text-[#86868B] mt-0.5">{{ subtitulo }}</p>
          </div>
        </div>

        <Link
          v-if="entidad === 'ruteo'"
          :href="route('supervisor.ingesta.snapshots')"
          class="px-3 py-1.5 text-[11px] font-semibold rounded-[10px] bg-white text-[#0071E3] border border-[#0071E3]/30 hover:bg-[#0071E3]/[0.05] transition-all flex items-center gap-1 shadow-sm"
        >
          <span>Historial Snapshots</span>
          <span>→</span>
        </Link>
        <Link
          v-else-if="entidad === 'preventas'"
          :href="route('supervisor.ingesta.cancelados')"
          class="px-3 py-1.5 text-[11px] font-semibold rounded-[10px] bg-white text-red-600 border border-red-200 hover:bg-red-50 transition-all flex items-center gap-1 shadow-sm"
        >
          <span>Auditar Cancelados</span>
          <span>→</span>
        </Link>
      </div>

      <!-- Campo opcional para Plan de Ruteo: Fecha asignada -->
      <div v-if="entidad === 'ruteo'" class="apple-glass-card rounded-[16px] p-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white/80 border border-black/[0.04]">
        <div>
          <label class="text-[12px] font-bold text-[#1D1D1F] block">Fecha Asignada al Ruteo (Opcional)</label>
          <span class="text-[10px] text-[#86868B]">Si no se especifica, se aplicará automáticamente la fecha actual del servidor.</span>
        </div>
        <input
          v-model="fechaRuteo"
          type="date"
          class="px-3 py-1.5 text-[11px] font-semibold rounded-[10px] border border-black/[0.12] bg-white focus:outline-none focus:border-[#0071E3] font-num text-[#1D1D1F]"
        />
      </div>

      <!-- Drop Zone de Archivo -->
      <DropZone
        ref="dropZoneRef"
        :cargando="procesando"
        @archivo-listo="ejecutarIngesta"
      />

      <!-- Alertas de Error con Diagnóstico -->
      <div
        v-if="errorMsg"
        class="p-4 rounded-[14px] bg-red-50 text-red-700 border border-red-200 text-[11px] font-semibold flex items-start justify-between gap-3 shadow-sm"
      >
        <div>
          <div class="font-bold text-[12px] mb-0.5">Discrepancia o Error en Ingesta:</div>
          <div class="leading-relaxed">{{ errorMsg }}</div>
        </div>
        <button
          type="button"
          @click="errorMsg = null"
          class="text-red-700 hover:opacity-70 cursor-pointer p-0.5"
          title="Cerrar alerta"
        >
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <!-- Resultados y Métricas de Ingesta Exitosa -->
      <div v-if="resultado" class="space-y-3 font-num">
        <div
          class="p-3.5 rounded-[14px] bg-emerald-50 text-emerald-800 border border-emerald-200 text-[11px] font-semibold flex items-center justify-between shadow-sm"
        >
          <div>
            <span class="font-bold text-[12px]">¡Ingesta procesada exitosamente!</span>
            <span v-if="resultado.fecha_operativa || resultado.rango_fechas || resultado.fecha_registrada" class="ml-1 text-[10px] opacity-90">
              · {{ resultado.fecha_operativa || resultado.rango_fechas ? 'Rango operativo: ' + (resultado.fecha_operativa || resultado.rango_fechas) : 'Registrado: ' + resultado.fecha_registrada }}
            </span>
          </div>
          <Link :href="route('supervisor.ingesta.index')" class="text-[11px] underline font-bold hover:opacity-80 text-emerald-900">
            Ver estado general →
          </Link>
        </div>

        <!-- Métricas Clave de la Carga -->
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5">
          <div class="apple-glass-card rounded-[14px] p-3 shadow-sm bg-white border border-black/[0.04]">
            <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider block">Leídas</span>
            <div class="text-[19px] font-bold text-[#1D1D1F] mt-0.5 font-num">
              {{ formatNum(resultado.filas_leidas ?? resultado.metricas?.total_filas_archivo) }}
            </div>
          </div>

          <div class="apple-glass-card rounded-[14px] p-3 shadow-sm bg-white border border-black/[0.04]">
            <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider block">Nuevas</span>
            <div class="text-[19px] font-bold text-emerald-700 mt-0.5 font-num">
              {{ formatNum(resultado.nuevos ?? resultado.filas_nuevas ?? resultado.insertadas ?? resultado.filas_insertadas ?? resultado.metricas?.total_nuevos) }}
            </div>
          </div>

          <div class="apple-glass-card rounded-[14px] p-3 shadow-sm bg-white border border-black/[0.04]">
            <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider block">Modificadas</span>
            <div class="text-[19px] font-bold text-amber-600 mt-0.5 font-num">
              {{ formatNum(resultado.modificados ?? resultado.filas_actualizadas ?? resultado.filas_alteradas ?? resultado.metricas?.total_modificados) }}
            </div>
          </div>

          <div class="apple-glass-card rounded-[14px] p-3 shadow-sm bg-white border border-black/[0.04]">
            <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider block">Alertas / Cancel.</span>
            <div class="text-[19px] font-bold text-red-600 mt-0.5 font-num">
              {{ formatNum(resultado.cancelados ?? resultado.eliminadas ?? resultado.eliminados ?? resultado.filas_con_discrepancia ?? resultado.filas_eliminadas ?? resultado.metricas?.total_eliminados) }}
            </div>
          </div>

          <div class="apple-glass-card rounded-[14px] p-3 shadow-sm bg-white border border-black/[0.04]">
            <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider block">Sin Cambios</span>
            <div class="text-[19px] font-bold text-[#86868B] mt-0.5 font-num">
              {{ formatNum(resultado.sin_cambios ?? resultado.filas_ignoradas ?? resultado.filas_identicas ?? resultado.metricas?.total_sin_cambios) }}
            </div>
          </div>
        </div>

        <!-- Detalle extra de Ruteo si existe snapshot_id -->
        <div v-if="resultado.snapshot_id" class="apple-glass-card rounded-[14px] p-3 text-[11px] flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-[#6E6E73] bg-white/90 shadow-sm border border-black/[0.06]">
          <div class="flex items-center gap-2 flex-wrap">
            <span>Snapshot inmutable preservado: <code class="font-mono font-bold text-[#1D1D1F] bg-black/[0.05] px-1.5 py-0.5 rounded">{{ resultado.snapshot_id }}</code></span>
            <span v-if="resultado.total_registros_auditoria" class="font-semibold text-[#1D1D1F]">
              · {{ formatNum(resultado.total_registros_auditoria) }} eventos de auditoría registrados
            </span>
          </div>
          <Link :href="route('supervisor.ingesta.snapshots')" class="text-[11px] underline font-bold hover:opacity-80 text-[#0071E3]">
            Ver historial de snapshots →
          </Link>
        </div>

        <!-- Detalle extra de volumen monetario procesado si existe -->
        <div v-if="resultado.volumen_total !== undefined || resultado.monto_total !== undefined" class="apple-glass-card rounded-[14px] p-3 text-[11px] flex items-center justify-between text-[#6E6E73] bg-white border border-black/[0.04]">
          <span>Volumen monetario total procesado en corte:</span>
          <span class="font-bold text-[#1D1D1F] text-[13px] font-num">
            Bs {{ Number(resultado.volumen_total ?? resultado.monto_total ?? 0).toLocaleString('es-BO', { minimumFractionDigits: 2 }) }}
          </span>
        </div>
      </div>
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link } from '@inertiajs/vue3'
import axios from 'axios'
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue'
import IngestaSubTabs from './Components/IngestaSubTabs.vue'
import DropZone from './Components/DropZone.vue'

const props = defineProps({
  entidad: {
    type: String,
    required: true,
  },
  titulo: {
    type: String,
    required: true,
  },
  tabla: {
    type: String,
    required: true,
  },
  subtitulo: {
    type: String,
    required: true,
  },
  submoduloActivo: {
    type: String,
    default: 'ventas',
  },
})

const dropZoneRef = ref(null)
const fechaRuteo = ref('')
const procesando = ref(false)
const errorMsg = ref(null)
const resultado = ref(null)

const routeEndpoints = {
  ventas: 'supervisor.ingesta.upload.ventas',
  preventas: 'supervisor.ingesta.upload.preventas',
  ruteo: 'supervisor.ingesta.upload.ruteo',
  visitas: 'supervisor.ingesta.upload.visitas',
  inventario: 'supervisor.ingesta.upload.inventario',
}

async function ejecutarIngesta(file) {
  procesando.value = true
  errorMsg.value = null
  resultado.value = null

  try {
    const formData = new FormData()
    formData.append('file', file)

    if (props.entidad === 'ruteo' && fechaRuteo.value) {
      formData.append('fecha_ruteo', fechaRuteo.value)
    }

    const routeName = routeEndpoints[props.entidad]
    if (!routeName) {
      throw new Error(`Entidad no soportada para carga: ${props.entidad}`)
    }

    const url = route(routeName)
    const { data } = await axios.post(url, formData, {
      headers: {
        'Accept': 'application/json',
      },
    })

    resultado.value = data.detalle || data
    dropZoneRef.value?.resetear()
  } catch (err) {
    const backendMsg = err.response?.data?.message || err.response?.data?.detail || err.response?.data?.error
    errorMsg.value = backendMsg || err.message || 'Error durante el procesamiento del archivo.'
  } finally {
    procesando.value = false
  }
}

function formatNum(v) {
  if (v === null || v === undefined) return '0'
  return Number(v).toLocaleString('es-BO')
}
</script>

