<template>
  <div
    class="sticky top-0 apple-glass-card rounded-[16px] p-2 sm:p-2.5 flex flex-wrap items-center justify-between gap-1.5 sm:gap-2 select-none font-sans transition-all duration-150"
    :class="menuCanalAbierto || menuRutasAbierto ? 'z-[10000]' : 'z-40'"
  >
    <!-- Grupo de Filtros Izquierda -->
    <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
      
      <!-- SubNavTabs para cambiar entre submódulos de Preventas -->
      <SubNavTabs :tabs="preventasSubTabs" currentTab="cumplimiento" />

      <div class="h-5 w-px bg-black/[0.08] hidden sm:block shrink-0"></div>

      <!-- Fecha -->
      <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1">
        <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Fecha:</span>
        <input
          v-model="store.fecha"
          type="date"
          @keyup.enter="onConsultarClick"
          class="bg-transparent text-[11px] font-bold text-[#1D1D1F] focus:outline-none cursor-pointer font-num"
        />
      </div>

      <!-- Multiselect Canales -->
      <div class="relative inline-block text-left" :class="menuCanalAbierto ? 'z-[10001]' : 'z-10'" ref="canalDropdownRef">
        <button
          type="button"
          @click="menuCanalAbierto = !menuCanalAbierto; if (menuCanalAbierto) menuRutasAbierto = false"
          class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1 text-[11px] font-semibold text-[#1D1D1F] cursor-pointer"
        >
          <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Canal:</span>
          <span class="truncate max-w-[130px] font-bold text-[#0071E3]">{{ etiquetaCanales }}</span>
          <svg
            class="w-3 h-3 text-[#86868B] transition-transform duration-200 pointer-events-none"
            :class="menuCanalAbierto ? 'rotate-180 text-[#0071E3]' : ''"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>

        <div
          v-if="menuCanalAbierto"
          class="absolute top-full left-0 mt-2 w-56 glass-dropdown rounded-[14px] z-[10002] p-2 space-y-1 shadow-2xl backdrop-blur-3xl bg-white/95 border border-black/[0.08]"
        >
          <div class="flex items-center justify-between px-1.5 pb-1.5 border-b border-black/[0.06] text-[10px]">
            <button
              type="button"
              @click="marcarTodosCanales(true)"
              class="text-[#0071E3] font-bold hover:underline cursor-pointer"
            >
              Marcar todos
            </button>
            <button
              type="button"
              @click="marcarTodosCanales(false)"
              class="text-[#86868B] font-semibold hover:underline cursor-pointer"
            >
              Desmarcar todos
            </button>
          </div>

          <div class="max-h-48 overflow-y-auto custom-scroll space-y-0.5 pt-0.5">
            <label
              v-for="c in store.canalesDisponibles"
              :key="c"
              class="flex items-center gap-2 px-2 py-1.5 rounded-[8px] hover:bg-blue-500/10 cursor-pointer text-[11px] transition-colors select-none"
            >
              <input
                type="checkbox"
                :checked="esCanalMarcado(c)"
                @change="toggleCanal(c)"
                class="rounded border-[#D2D2D7] text-[#0071E3] focus:ring-0 cursor-pointer shrink-0"
              />
              <span class="font-bold text-[#1D1D1F] truncate flex-1">
                {{ c }}
              </span>
            </label>
          </div>

          <div class="pt-1.5 border-t border-black/[0.06] flex items-center justify-between text-[9px] text-[#86868B] px-1">
            <span>{{ store.canalesDisponibles.length }} canales</span>
            <button
              type="button"
              @click="menuCanalAbierto = false"
              class="font-bold text-[#1D1D1F] hover:text-[#0071E3] cursor-pointer"
            >
              Cerrar
            </button>
          </div>
        </div>
      </div>

      <!-- Multiselect Rutas -->
      <div class="relative inline-block text-left" :class="menuRutasAbierto ? 'z-[10001]' : 'z-10'" ref="rutasDropdownRef">
        <button
          type="button"
          @click="menuRutasAbierto = !menuRutasAbierto; if (menuRutasAbierto) menuCanalAbierto = false"
          class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1 text-[11px] font-semibold text-[#1D1D1F] cursor-pointer"
        >
          <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Rutas:</span>
          <span class="truncate max-w-[130px] font-bold text-[#0071E3]">{{ etiquetaRutas }}</span>
          <svg
            class="w-3 h-3 text-[#86868B] transition-transform duration-200 pointer-events-none"
            :class="menuRutasAbierto ? 'rotate-180 text-[#0071E3]' : ''"
            fill="none"
            stroke="currentColor"
            viewBox="0 0 24 24"
          >
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
          </svg>
        </button>

        <div
          v-if="menuRutasAbierto"
          class="absolute top-full left-0 mt-2 w-64 glass-dropdown rounded-[14px] z-[10002] p-2 space-y-1 shadow-2xl backdrop-blur-3xl bg-white/95 border border-black/[0.08]"
        >
          <div class="flex items-center justify-between px-1.5 pb-1.5 border-b border-black/[0.06] text-[10px]">
            <button
              type="button"
              @click="marcarTodasLasRutas(true)"
              class="text-[#0071E3] font-bold hover:underline cursor-pointer"
            >
              Marcar todas
            </button>
            <button
              type="button"
              @click="marcarTodasLasRutas(false)"
              class="text-[#86868B] font-semibold hover:underline cursor-pointer"
            >
              Desmarcar todas
            </button>
          </div>

          <div class="relative pt-0.5">
            <input
              v-model="filtroTextoRuta"
              type="text"
              placeholder="Buscar ruta o preventista..."
              class="w-full text-[10px] bg-white/80 rounded-[8px] px-2.5 py-1.5 focus:outline-none font-medium border border-black/[0.06] placeholder-[#86868B]"
            />
          </div>

          <div v-if="rutasOpcionesFiltradas.length === 0" class="p-2 text-[11px] text-[#86868B] text-center">
            Sin rutas disponibles
          </div>

          <div v-else class="max-h-52 overflow-y-auto custom-scroll space-y-0.5 pt-1">
            <label
              v-for="item in rutasOpcionesFiltradas"
              :key="item.ruta"
              class="flex items-center gap-2 px-2 py-1.5 rounded-[8px] hover:bg-blue-500/10 cursor-pointer text-[11px] transition-colors select-none"
            >
              <input
                type="checkbox"
                :checked="esRutaMarcada(item.ruta)"
                @change="toggleRuta(item.ruta)"
                class="rounded border-[#D2D2D7] text-[#0071E3] focus:ring-0 cursor-pointer shrink-0"
              />
              <span class="font-bold text-[#1D1D1F] truncate flex-1">
                {{ item.ruta }} <span v-if="item.vendedor" class="font-normal text-[#6E6E73] text-[10px]">- {{ item.vendedor }}</span>
              </span>
            </label>
          </div>

          <div class="pt-1.5 border-t border-black/[0.06] flex items-center justify-between text-[9px] text-[#86868B] px-1">
            <span>{{ store.rutasPermitidasPorCanal.length }} rutas</span>
            <button
              type="button"
              @click="menuRutasAbierto = false"
              class="font-bold text-[#1D1D1F] hover:text-[#0071E3] cursor-pointer"
            >
              Cerrar
            </button>
          </div>
        </div>
      </div>

      <!-- Buscador rápido -->
      <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1">
        <svg class="w-3.5 h-3.5 text-[#86868B] shrink-0 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <input
          v-model="store.busqueda"
          @keyup.enter="onConsultarClick"
          type="text"
          placeholder="Filtrar tabla..."
          class="bg-transparent text-[11px] font-medium text-[#1D1D1F] placeholder-[#86868B] focus:outline-none w-28 sm:w-36"
        />
      </div>

      <!-- Checkbox Evaluar App + Web -->
      <label
        class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1 text-[11px] font-semibold text-[#1D1D1F] cursor-pointer select-none"
        title="Al estar marcado, evalúa tanto App como Web: si alguna hora supera las 08:10 o falta marcación, no sumará puntos"
      >
        <input
          v-model="store.horarioEstricto"
          type="checkbox"
          class="rounded border-[#D2D2D7] text-[#0071E3] focus:ring-0 cursor-pointer"
        />
        <span class="whitespace-nowrap">Validar App + Web</span>
      </label>

      <!-- Botón Consultar Principal -->
      <button
        type="button"
        @click="onConsultarClick"
        :disabled="store.estaCargando"
        class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-[10px] text-[11px] font-semibold text-white bg-[#0071E3] hover:bg-[#0077ED] active:scale-[0.98] transition-all duration-150 shadow-[0_2px_8px_rgba(0,113,227,0.35)] hover:shadow-[0_4px_12px_rgba(0,113,227,0.45)] cursor-pointer disabled:opacity-75 disabled:cursor-not-allowed select-none shrink-0"
      >
        <svg
          v-if="store.estaCargando"
          class="w-3.5 h-3.5 animate-spin text-white shrink-0"
          fill="none"
          viewBox="0 0 24 24"
        >
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
        </svg>
        <svg
          v-else
          class="w-3.5 h-3.5 text-white shrink-0"
          fill="none"
          stroke="currentColor"
          viewBox="0 0 24 24"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <span>{{ store.estaCargando ? 'Consultando...' : 'Consultar' }}</span>
      </button>
    </div>

    <!-- Días Hábiles Metadata -->
    <div class="sm:ml-auto text-[10px] font-medium text-[#6E6E73] apple-control-pill px-3 py-1 rounded-[10px] flex items-center gap-1.5 font-mono">
      <span class="w-1.5 h-1.5 rounded-full bg-[#0071E3] shadow-[0_0_6px_rgba(0,113,227,0.6)]"></span>
      <span>Días hábiles:</span>
      <span class="font-bold text-[#1D1D1F] font-num tabular-nums text-[11px]">{{ store.textoDiasHabiles }}</span>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { usePreventaStore } from '@/Stores/preventaStore'
import SubNavTabs from '@/Components/UI/SubNavTabs.vue'
import { preventasSubTabs } from '@/Config/navigation'

const store = usePreventaStore()
const emit = defineEmits(['consultar', 'cambio-filtro', 'cambio-fecha'])

const canalDropdownRef = ref(null)
const rutasDropdownRef = ref(null)
const menuCanalAbierto = ref(false)
const menuRutasAbierto = ref(false)
const filtroTextoRuta = ref('')

function norm(str) {
  return String(str || '').trim().toUpperCase()
}

const etiquetaCanales = computed(() => {
  const disp = Array.isArray(store.canalesDisponibles) ? store.canalesDisponibles : []
  const sel = Array.isArray(store.canalesSeleccionados) ? store.canalesSeleccionados : []
  const total = disp.length
  const selCount = sel.length
  if (total === 0 || selCount === total) return 'TODOS'
  if (selCount === 0) return 'NINGUNO'
  if (selCount === 1) return sel[0]
  return `${selCount}/${total}`
})

const etiquetaRutas = computed(() => {
  const permitidas = Array.isArray(store.rutasPermitidasPorCanal) ? store.rutasPermitidasPorCanal : []
  const totalActual = permitidas.length
  if (totalActual === 0) return 'NINGUNA'
  const sel = Array.isArray(store.rutasSeleccionadas) ? store.rutasSeleccionadas : []
  const setR = new Set(sel.map(norm))
  const activasVisibles = permitidas.filter(item => setR.has(norm(item.ruta))).length
  if (activasVisibles === totalActual) return 'TODAS'
  if (activasVisibles === 1) {
    const unica = permitidas.find(item => setR.has(norm(item.ruta)))
    return unica ? unica.ruta : (sel[0] || '1')
  }
  return `${activasVisibles}/${totalActual}`
})

const rutasOpcionesFiltradas = computed(() => {
  const permitidas = Array.isArray(store.rutasPermitidasPorCanal) ? store.rutasPermitidasPorCanal : []
  const q = filtroTextoRuta.value.trim().toUpperCase()
  if (!q) return permitidas
  return permitidas.filter(item => {
    return String(item.ruta || '').toUpperCase().includes(q) || String(item.vendedor || '').toUpperCase().includes(q)
  })
})

function esCanalMarcado(c) {
  const sel = Array.isArray(store.canalesSeleccionados) ? store.canalesSeleccionados : []
  return sel.includes(norm(c))
}

function marcarTodosCanales(marcar) {
  const disp = Array.isArray(store.canalesDisponibles) ? store.canalesDisponibles : []
  store.canalesSeleccionados = marcar ? disp.map(norm) : []
  store.sincronizarRutasAlCambiarCanal()
}

function toggleCanal(c) {
  const val = norm(c)
  const sel = Array.isArray(store.canalesSeleccionados) ? store.canalesSeleccionados : []
  const actuales = new Set(sel)
  if (actuales.has(val)) actuales.delete(val)
  else actuales.add(val)
  store.canalesSeleccionados = Array.from(actuales)
  store.sincronizarRutasAlCambiarCanal()
}

function esRutaMarcada(r) {
  const sel = Array.isArray(store.rutasSeleccionadas) ? store.rutasSeleccionadas : []
  return sel.includes(norm(r))
}

function toggleRuta(r) {
  const val = norm(r)
  const sel = Array.isArray(store.rutasSeleccionadas) ? store.rutasSeleccionadas : []
  const actuales = new Set(sel)
  if (actuales.has(val)) actuales.delete(val)
  else actuales.add(val)
  store.rutasSeleccionadas = Array.from(actuales)
}

function marcarTodasLasRutas(marcar) {
  if (marcar) {
    const permitidas = Array.isArray(store.rutasPermitidasPorCanal) ? store.rutasPermitidasPorCanal : []
    store.rutasSeleccionadas = permitidas.map(item => norm(item.ruta))
  } else {
    store.rutasSeleccionadas = []
  }
}

function onConsultarClick() {
  menuCanalAbierto.value = false
  menuRutasAbierto.value = false
  store.aplicarFiltros()
  emit('consultar')
  emit('cambio-fecha', store.fecha)
  emit('cambio-filtro')
}

function handleClickOutside(e) {
  if (canalDropdownRef.value && !canalDropdownRef.value.contains(e.target)) {
    menuCanalAbierto.value = false
  }
  if (rutasDropdownRef.value && !rutasDropdownRef.value.contains(e.target)) {
    menuRutasAbierto.value = false
  }
}

onMounted(() => {
  document.addEventListener('pointerdown', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('pointerdown', handleClickOutside)
})
</script>

