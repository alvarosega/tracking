<template>
  <div
    class="relative apple-glass-card rounded-[14px] p-2 sm:p-2.5 flex flex-wrap items-center justify-between gap-2 mb-2.5 select-none font-sans transition-all duration-150"
    :class="menuCanalAbierto || menuRutasAbierto ? 'z-[100]' : 'z-30'"
  >
    <!-- Grupo de Filtros Izquierda -->
    <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
      <!-- Filtro Fecha Inicio -->
      <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1">
        <label class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Desde:</label>
        <input
          v-model="store.fechaInicio"
          type="date"
          @change="emit('cambio')"
          @keyup.enter="onConsultarClick"
          class="bg-transparent text-[11px] font-bold text-[#1D1D1F] focus:outline-none cursor-pointer font-mono"
        />
      </div>

      <!-- Filtro Fecha Corte -->
      <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1">
        <label class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Corte:</label>
        <input
          v-model="store.fechaCorte"
          type="date"
          @change="emit('cambio')"
          @keyup.enter="onConsultarClick"
          class="bg-transparent text-[11px] font-bold text-[#1D1D1F] focus:outline-none cursor-pointer font-mono"
        />
      </div>

      <!-- Selector de Canal (Multiselección) -->
      <div class="relative inline-block text-left" :class="menuCanalAbierto ? 'z-[101]' : 'z-10'" ref="canalDropdownRef">
        <button
          type="button"
          @click="menuCanalAbierto = !menuCanalAbierto; if (menuCanalAbierto) menuRutasAbierto = false"
          class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1 text-[11px] font-semibold text-[#1D1D1F] cursor-pointer"
        >
          <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Canal:</span>
          <span class="max-w-[110px] truncate text-[11px] font-bold text-[#0071E3]">{{ etiquetaCanalesSeleccionados }}</span>
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

        <!-- Dropdown Canales -->
        <div
          v-if="menuCanalAbierto"
          class="absolute top-full left-0 mt-2 w-56 glass-dropdown rounded-[14px] z-[102] p-2 space-y-1 shadow-float"
        >
          <div class="flex items-center justify-between px-1.5 pb-1.5 border-b border-black/[0.06] text-[10px]">
            <button type="button" @click="seleccionarTodosCanales" class="text-[#0071E3] font-bold hover:underline cursor-pointer">Marcar todos</button>
            <button type="button" @click="deseleccionarTodosCanales" class="text-[#86868B] font-semibold hover:underline cursor-pointer">Desmarcar todos</button>
          </div>

          <div class="max-h-48 overflow-y-auto custom-scroll space-y-0.5 pt-0.5">
            <label
              v-for="c in canalesDisponiblesLimpios"
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
            <span>{{ canalesDisponiblesLimpios.length }} canales</span>
            <button type="button" @click="menuCanalAbierto = false" class="font-bold text-[#1D1D1F] hover:text-[#0071E3] cursor-pointer">Cerrar</button>
          </div>
        </div>
      </div>

      <!-- Selector de Rutas (Multiselección) -->
      <div class="relative inline-block text-left" :class="menuRutasAbierto ? 'z-[101]' : 'z-10'" ref="rutasDropdownRef">
        <button
          type="button"
          @click="menuRutasAbierto = !menuRutasAbierto; if (menuRutasAbierto) menuCanalAbierto = false"
          class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1 text-[11px] font-semibold text-[#1D1D1F] cursor-pointer"
        >
          <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Rutas:</span>
          <span class="max-w-[120px] truncate text-[11px] font-bold text-[#0071E3]">{{ etiquetaRutasSeleccionadas }}</span>
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

        <!-- Dropdown Rutas -->
        <div
          v-if="menuRutasAbierto"
          class="absolute top-full left-0 mt-2 w-64 glass-dropdown rounded-[14px] z-[102] p-2 space-y-1 shadow-float"
        >
          <div class="flex items-center justify-between px-1.5 pb-1.5 border-b border-black/[0.06] text-[10px]">
            <button type="button" @click="seleccionarTodas" class="text-[#0071E3] font-bold hover:underline cursor-pointer">Marcar todas</button>
            <button type="button" @click="deseleccionarTodas" class="text-[#86868B] font-semibold hover:underline cursor-pointer">Desmarcar todas</button>
          </div>

          <div class="relative pt-0.5">
            <input
              v-model="filtroTextoRuta"
              type="text"
              placeholder="Buscar ruta..."
              class="w-full text-[10px] bg-white/70 rounded-[8px] px-2.5 py-1.5 focus:outline-none font-medium border border-black/[0.06] placeholder-[#86868B]"
            />
          </div>

          <div class="max-h-52 overflow-y-auto custom-scroll space-y-0.5 pt-1">
            <label
              v-for="r in rutasOpcionesFiltradas"
              :key="r.ruta"
              class="flex items-center gap-2 px-2 py-1.5 rounded-[8px] hover:bg-blue-500/10 cursor-pointer text-[11px] transition-colors select-none"
            >
              <input
                type="checkbox"
                :checked="esRutaMarcada(r.ruta)"
                @change="toggleRuta(r.ruta)"
                class="rounded border-[#D2D2D7] text-[#0071E3] focus:ring-0 cursor-pointer shrink-0"
              />
              <span class="font-bold text-[#1D1D1F] truncate flex-1">
                {{ r.ruta }}
                <span v-if="r.vendedor" class="font-normal text-[10px] text-[#86868B] ml-1">({{ r.vendedor }})</span>
              </span>
            </label>
          </div>

          <div class="pt-1.5 border-t border-black/[0.06] flex items-center justify-between text-[9px] text-[#86868B] px-1">
            <span>{{ rutasFiltradasPorCanal.length }} rutas</span>
            <button type="button" @click="menuRutasAbierto = false" class="font-bold text-[#1D1D1F] hover:text-[#0071E3] cursor-pointer">Cerrar</button>
          </div>
        </div>
      </div>

      <!-- Buscador -->
      <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1">
        <svg class="w-3.5 h-3.5 text-[#86868B] shrink-0 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <input
          v-model="store.busqueda"
          type="text"
          placeholder="Buscar ruta o vendedor..."
          @input="emit('cambio')"
          @keyup.enter="onConsultarClick"
          class="bg-transparent text-[11px] font-medium text-[#1D1D1F] placeholder-[#86868B] focus:outline-none w-32 sm:w-44"
        />
      </div>

      <!-- Botón Consultar Principal -->
      <button
        type="button"
        @click="onConsultarClick"
        :disabled="store.cargandoCumplimiento"
        class="flex items-center gap-1.5 px-3.5 py-1.5 rounded-[10px] text-[11px] font-semibold text-white bg-[#0071E3] hover:bg-[#0077ED] active:scale-[0.98] transition-all duration-150 shadow-[0_2px_8px_rgba(0,113,227,0.35)] hover:shadow-[0_4px_12px_rgba(0,113,227,0.45)] cursor-pointer disabled:opacity-75 disabled:cursor-not-allowed select-none shrink-0"
      >
        <svg
          v-if="store.cargandoCumplimiento"
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
        <span>{{ store.cargandoCumplimiento ? 'Consultando...' : 'Consultar' }}</span>
      </button>
    </div>

    <!-- Días Hábiles -->
    <div class="sm:ml-auto text-[10px] font-medium text-[#6E6E73] apple-control-pill px-2.5 py-1 rounded-[10px] flex items-center gap-1.5 font-mono">
      <span class="w-1.5 h-1.5 rounded-full bg-[#0071E3] animate-pulse"></span>
      <span>Días hábiles:</span>
      <span class="font-bold text-[#1D1D1F] tabular-nums text-[11px]">
        {{ textoDiasHabiles }}
      </span>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useAvanceStore } from '@/Stores/avanceStore'

const store = useAvanceStore()
const emit = defineEmits(['consultar', 'cambio'])

const canalDropdownRef = ref(null)
const menuCanalAbierto = ref(false)
const rutasDropdownRef = ref(null)
const menuRutasAbierto = ref(false)
const filtroTextoRuta = ref('')

const canalesDisponiblesLimpios = computed(() => {
  return (store.canalesDisponibles || []).filter(c => c && c !== 'TODOS' && c !== 'TODAS' && c !== '__NINGUNO__')
})

const etiquetaCanalesSeleccionados = computed(() => {
  const sel = store.canalesSeleccionados || []
  if (sel.includes('__NINGUNO__')) return 'Ninguno'
  const total = canalesDisponiblesLimpios.value.length
  if (sel.length === 0 || (total > 0 && sel.length >= total)) return 'TODOS'
  if (sel.length === 1) return sel[0]
  return `${sel.length} de ${total}`
})

function esCanalMarcado(canal) {
  const sel = store.canalesSeleccionados || []
  if (sel.includes('__NINGUNO__')) return false
  if (sel.length === 0) return true
  const cNorm = String(canal).trim().toUpperCase()
  return sel.map(c => String(c).trim().toUpperCase()).includes(cNorm)
}

function toggleCanal(canal) {
  const todos = canalesDisponiblesLimpios.value
  const cNorm = String(canal).trim().toUpperCase()
  let actuales = (store.canalesSeleccionados || []).filter(c => c !== '__NINGUNO__')

  if (!store.canalesSeleccionados || store.canalesSeleccionados.length === 0 || store.canalesSeleccionados.length >= todos.length) {
    actuales = todos.filter(c => String(c).trim().toUpperCase() !== cNorm)
  } else if (actuales.map(c => String(c).trim().toUpperCase()).includes(cNorm)) {
    actuales = actuales.filter(c => String(c).trim().toUpperCase() !== cNorm)
  } else {
    actuales.push(canal)
  }

  if (actuales.length === 0) {
    store.canalesSeleccionados = ['__NINGUNO__']
  } else if (actuales.length >= todos.length) {
    store.canalesSeleccionados = []
  } else {
    store.canalesSeleccionados = actuales
  }

  emit('cambio')
}

function seleccionarTodosCanales() {
  store.canalesSeleccionados = []
  emit('cambio')
}

function deseleccionarTodosCanales() {
  store.canalesSeleccionados = ['__NINGUNO__']
  emit('cambio')
}

const rutasFiltradasPorCanal = computed(() => {
  return store.rutasPermitidasPorCanal || []
})

const rutasOpcionesFiltradas = computed(() => {
  const lista = rutasFiltradasPorCanal.value
  if (!filtroTextoRuta.value.trim()) return lista
  const q = filtroTextoRuta.value.toLowerCase().trim()
  return lista.filter(r => (r.ruta && r.ruta.toLowerCase().includes(q)) || (r.vendedor && r.vendedor.toLowerCase().includes(q)))
})

const etiquetaRutasSeleccionadas = computed(() => {
  const sel = store.rutasSeleccionadas || []
  if (sel.includes('__NINGUNA__')) return 'Ninguna'
  const total = rutasFiltradasPorCanal.value.length
  if (sel.length === 0 || (total > 0 && sel.length >= total)) return 'TODAS'
  if (sel.length === 1) return sel[0]
  return `${sel.length} de ${total}`
})

function esRutaMarcada(ruta) {
  const sel = store.rutasSeleccionadas || []
  if (sel.includes('__NINGUNA__')) return false
  if (sel.length === 0) return true
  const rNorm = String(ruta).trim().toUpperCase()
  return sel.map(r => String(r).trim().toUpperCase()).includes(rNorm)
}

function toggleRuta(ruta) {
  const todas = rutasFiltradasPorCanal.value.map(r => r.ruta)
  const sel = (store.rutasSeleccionadas || []).filter(r => r !== '__NINGUNA__')
  const rNorm = String(ruta).trim().toUpperCase()
  let actuales = []

  if (!store.rutasSeleccionadas || store.rutasSeleccionadas.length === 0 || store.rutasSeleccionadas.length >= todas.length) {
    actuales = todas.filter(r => String(r).trim().toUpperCase() !== rNorm)
  } else if (sel.map(r => String(r).trim().toUpperCase()).includes(rNorm)) {
    actuales = sel.filter(r => String(r).trim().toUpperCase() !== rNorm)
  } else {
    actuales = [...sel, ruta]
  }

  if (actuales.length === 0) {
    store.rutasSeleccionadas = ['__NINGUNA__']
  } else if (actuales.length >= todas.length) {
    store.rutasSeleccionadas = []
  } else {
    store.rutasSeleccionadas = actuales
  }

  emit('cambio')
}

function seleccionarTodas() {
  store.rutasSeleccionadas = []
  emit('cambio')
}

function deseleccionarTodas() {
  store.rutasSeleccionadas = ['__NINGUNA__']
  emit('cambio')
}

const textoDiasHabiles = computed(() => {
  const d = store.diasCalendario
  if (!d) return '-'
  const trans = d.transcurridos ?? 0
  const tot = d.totales ?? 0
  const rest = d.restantes ?? 0
  return `${trans} trans / ${tot} tot (${rest} rest)`
})

function onConsultarClick() {
  menuCanalAbierto.value = false
  menuRutasAbierto.value = false
  store.aplicarFiltros()
  emit('consultar')
  emit('cambio')
}

function handleClickOutside(event) {
  if (rutasDropdownRef.value && !rutasDropdownRef.value.contains(event.target)) {
    menuRutasAbierto.value = false
  }
  if (canalDropdownRef.value && !canalDropdownRef.value.contains(event.target)) {
    menuCanalAbierto.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', handleClickOutside)
})

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside)
})
</script>
