<template>
  <SupervisorLayout full-bleed>
    <div class="flex-1 flex flex-col min-h-0 overflow-y-auto custom-scroll p-3 sm:p-5 font-sans text-ink space-y-3">
      
      <!-- 1. Barra de Filtros Universal de Preventa -->
      <PreventaFiltrosBar @consultar="onConsultar" @cambio-fecha="onConsultar" />

      <!-- 2. Navegación por KPI Segmented Control (Apple Liquid Glass) -->
      <nav
        class="apple-glass-card rounded-[14px] p-1.5 flex flex-nowrap sm:flex-wrap gap-1 items-center overflow-x-auto custom-scroll text-[11px] shrink-0 select-none"
      >
        <button
          v-for="tab in kpiTabs"
          :key="tab.id"
          type="button"
          @click="store.tabActivo = tab.id"
          class="px-3 py-1.5 rounded-[9px] transition-all duration-200 cursor-pointer text-[11px] shrink-0 whitespace-nowrap flex items-center gap-1.5"
          :class="
            store.tabActivo === tab.id
              ? 'glass-active-pill text-[#1D1D1F] font-bold'
              : 'text-[#6E6E73] hover:text-[#1D1D1F] hover:bg-white/55 font-semibold'
          "
        >
          <span
            v-if="store.tabActivo === tab.id"
            class="w-1.5 h-1.5 rounded-full bg-[#0071E3] shadow-[0_0_6px_rgba(0,113,227,0.7)]"
          ></span>
          <span>{{ tab.label }}</span>
        </button>
      </nav>

      <!-- 3. Estado de Error Global -->
      <div
        v-if="store.errorRanking && store.tabActivo !== 'pedido_reposicion' && store.tabActivo !== 'ampliacion_portafolio'"
        class="apple-glass-card rounded-[14px] p-8 text-center text-[#C9342C] font-semibold text-[12px]"
      >
        No se pudieron cargar los datos de evaluación: {{ store.errorRanking }}
      </div>

      <!-- 4. Contenido Dinámico por Pestaña -->
      <div v-else class="w-full flex-1 min-h-0">
        <TabGeneral v-if="store.tabActivo === 'general'" @seleccionar-tab="tab => store.tabActivo = tab" />
        <TabHorarioIngreso v-else-if="store.tabActivo === 'horario_ingreso'" />
        <TabClientesVisitados v-else-if="store.tabActivo === 'clientes_visitados'" />
        <TabCumplimientoObjetivo v-else-if="store.tabActivo === 'cumplimiento_objetivo'" />
        <TabEfectividadRuta v-else-if="store.tabActivo === 'efectividad_ruta'" />
        <TabCompraMinima v-else-if="store.tabActivo === 'compra_minima'" />
        <TabPedidoReposicion v-else-if="store.tabActivo === 'pedido_reposicion'" />
        <TabAmpliacionPortafolio v-else-if="store.tabActivo === 'ampliacion_portafolio'" />
        <TabCovers v-else-if="store.tabActivo === 'covers'" />
      </div>

    </div>
  </SupervisorLayout>
</template>

<script setup>
import { onMounted } from 'vue'
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue'
import { usePreventaStore } from '@/Stores/preventaStore'
import PreventaFiltrosBar from './Cumplimiento/PreventaFiltrosBar.vue'
import TabGeneral from './Cumplimiento/TabGeneral.vue'
import TabHorarioIngreso from './Cumplimiento/TabHorarioIngreso.vue'
import TabClientesVisitados from './Cumplimiento/TabClientesVisitados.vue'
import TabCumplimientoObjetivo from './Cumplimiento/TabCumplimientoObjetivo.vue'
import TabEfectividadRuta from './Cumplimiento/TabEfectividadRuta.vue'
import TabCompraMinima from './Cumplimiento/TabCompraMinima.vue'
import TabPedidoReposicion from './Cumplimiento/TabPedidoReposicion.vue'
import TabAmpliacionPortafolio from './Cumplimiento/TabAmpliacionPortafolio.vue'
import TabCovers from './Cumplimiento/TabCovers.vue'

const props = defineProps({
  catalogo_rutas: {
    type: Array,
    default: () => []
  },
  canales: {
    type: Array,
    default: () => []
  },
  fecha_default: {
    type: String,
    default: ''
  }
})

const store = usePreventaStore()

const kpiTabs = [
  { id: 'general', label: 'Pizarra Unilever (100 pts)' },
  { id: 'horario_ingreso', label: '1. Horario (10)' },
  { id: 'clientes_visitados', label: '2. Visitas (5)' },
  { id: 'cumplimiento_objetivo', label: '3. Objetivo Diario (10)' },
  { id: 'efectividad_ruta', label: '4. Efectividad (30)' },
  { id: 'compra_minima', label: '5. Mín 70 (5)' },
  { id: 'pedido_reposicion', label: '6. Reposición (15)' },
  { id: 'ampliacion_portafolio', label: '7. Ampliación (20)' },
  { id: 'covers', label: '8. Covers (5)' }
]

async function onConsultar() {
  store.aplicarFiltros()
  await store.cargarDatosRanking()
}

onMounted(async () => {
  store.inicializarDesdeStorage(props)
  await store.cargarDatosRanking()
})
</script>
