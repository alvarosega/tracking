import { ref, reactive, computed, watch } from 'vue'
import axios from 'axios'

const STORAGE_KEY = 'supervisor_avance_filtros_estado_v2'
const LEGACY_STORAGE_KEY = 'supervisor_avance_filtros_estado'

function norm(str) {
  return String(str || '').trim().toUpperCase()
}

// Variables reactivas singleton
const fechaInicio = ref('')
const fechaCorte = ref('')
const mes = ref('')
const canalSeleccionado = ref('TODOS')
const canalesSeleccionados = ref([]) // [] representa TODOS los canales disponibles
const canalesDisponibles = ref([])
const catalogoRutas = ref([])
const rutasSeleccionadas = ref([]) // [] representa TODAS las rutas de los canales activos
const busqueda = ref('')
const diasCalendario = ref(null)

const filtrosAplicados = ref({
  fechaInicio: '',
  fechaCorte: '',
  mes: '',
  canalSeleccionado: 'TODOS',
  canalesSeleccionados: [],
  rutasSeleccionadas: [],
  busqueda: ''
})

const cargandoCumplimiento = ref(false)
const datosCumplimiento = ref(null)
const errorCumplimiento = ref(null)
const vistaCumplimiento = ref('resumen') // 'resumen' o 'kpi'
const kpiActivo = ref('vol_general')
const modoTemporalCumplimiento = ref('acumulado') // 'acumulado' o 'diario'

function actualizarCanalSeleccionadoTexto() {
  const sel = canalesSeleccionados.value || []
  if (sel.includes('__NINGUNO__')) {
    canalSeleccionado.value = 'NINGUNO'
  } else if (sel.length === 0 || (canalesDisponibles.value.length > 0 && sel.length >= canalesDisponibles.value.length)) {
    canalSeleccionado.value = 'TODOS'
  } else if (sel.length === 1) {
    canalSeleccionado.value = sel[0]
  } else {
    canalSeleccionado.value = `${sel.length} CANALES`
  }
}

const rutasPermitidasPorCanal = computed(() => {
  const cat = catalogoRutas.value || []
  const cSel = canalesSeleccionados.value || []
  const cDisp = canalesDisponibles.value || []

  if (cSel.includes('__NINGUNO__')) return []
  if (cSel.length === 0) return cat
  if (cDisp.length > 0 && cSel.length >= cDisp.length) return cat

  const setC = new Set(cSel.map(norm))
  return cat.filter(item => {
    const canalItem = norm(item.canal || 'SC')
    return setC.has(canalItem)
  })
})

function sincronizarRutasConCanales() {
  const cSel = canalesSeleccionados.value || []
  const permitidas = (rutasPermitidasPorCanal.value || []).map(item => norm(item.ruta))
  const permitidasSet = new Set(permitidas)

  // 1. Si los canales están en '__NINGUNO__', no hay rutas disponibles
  if (cSel.includes('__NINGUNO__')) {
    rutasSeleccionadas.value = ['__NINGUNA__']
    actualizarCanalSeleccionadoTexto()
    return
  }

  // 2. Si las rutas estaban en '__NINGUNA__', restablecer a TODAS ([])
  if (rutasSeleccionadas.value.includes('__NINGUNA__')) {
    rutasSeleccionadas.value = []
    actualizarCanalSeleccionadoTexto()
    return
  }

  // 3. Si rutasSeleccionadas es [] (representa TODAS las rutas), se mantiene []
  if (!Array.isArray(rutasSeleccionadas.value) || rutasSeleccionadas.value.length === 0) {
    rutasSeleccionadas.value = []
    actualizarCanalSeleccionadoTexto()
    return
  }

  // 4. Si el usuario tenía seleccionadas rutas específicas, conservar solo las que pertenezcan a los canales actuales
  const validas = rutasSeleccionadas.value.filter(r => permitidasSet.has(norm(r)))

  if (validas.length === 0) {
    // Si ninguna de las rutas previas pertenece al nuevo canal (ej: pasó de TDB a MZO),
    // se restablece automáticamente a TODAS ([]) para evitar vistas vacías sin datos.
    rutasSeleccionadas.value = []
  } else if (permitidas.length > 0 && validas.length >= permitidas.length) {
    // Si abarca todas las rutas permitidas del canal, simplificar a [] (significa TODAS)
    rutasSeleccionadas.value = []
  } else {
    rutasSeleccionadas.value = validas
  }

  actualizarCanalSeleccionadoTexto()
}

function aplicarFiltros() {
  sincronizarRutasConCanales()

  filtrosAplicados.value = {
    fechaInicio: fechaInicio.value,
    fechaCorte: fechaCorte.value,
    mes: mes.value,
    canalSeleccionado: canalSeleccionado.value || 'TODOS',
    canalesSeleccionados: [...(canalesSeleccionados.value || [])],
    rutasSeleccionadas: [...(rutasSeleccionadas.value || [])],
    busqueda: String(busqueda.value || '').trim()
  }
  guardarEstado()
}

function recuperarEstado() {
  try {
    let raw = localStorage.getItem(STORAGE_KEY) || sessionStorage.getItem(STORAGE_KEY)
    if (!raw) {
      // Revisar si existe estado legacy y limpiarlo si tenía TDB forzado
      const legacyRaw = sessionStorage.getItem(LEGACY_STORAGE_KEY) || localStorage.getItem(LEGACY_STORAGE_KEY)
      if (legacyRaw) {
        try {
          const parsed = JSON.parse(legacyRaw)
          if (
            Array.isArray(parsed.canalesSeleccionados) &&
            parsed.canalesSeleccionados.length === 1 &&
            norm(parsed.canalesSeleccionados[0]) === 'TDB'
          ) {
            parsed.canalesSeleccionados = []
            parsed.canalSeleccionado = 'TODOS'
            parsed.rutasSeleccionadas = []
          }
          return parsed
        } catch {}
      }
      return null
    }
    return JSON.parse(raw)
  } catch (e) {
    console.warn('[AvanceStore] Error al recuperar estado:', e)
    return null
  }
}

function guardarEstado() {
  try {
    const estado = {
      version: 2,
      fechaInicio: fechaInicio.value,
      fechaCorte: fechaCorte.value,
      mes: mes.value,
      canalSeleccionado: canalSeleccionado.value || 'TODOS',
      canalesSeleccionados: canalesSeleccionados.value || [],
      rutasSeleccionadas: rutasSeleccionadas.value || [],
      busqueda: busqueda.value || '',
      vistaCumplimiento: vistaCumplimiento.value,
      kpiActivo: kpiActivo.value,
      modoTemporalCumplimiento: modoTemporalCumplimiento.value
    }
    const json = JSON.stringify(estado)
    localStorage.setItem(STORAGE_KEY, json)
    sessionStorage.setItem(STORAGE_KEY, json)
  } catch (e) {
    console.warn('[AvanceStore] No se pudo guardar estado:', e)
  }
}

function configurarCanales(listaCanales) {
  const limpios = Array.from(
    new Set(
      (listaCanales || [])
        .map(c => String(c || '').trim())
        .filter(c => c && c.toUpperCase() !== 'TODOS' && c.toUpperCase() !== 'TODAS')
    )
  )
  canalesDisponibles.value = limpios

  // NO forzar TDB por defecto: por defecto se mantiene [] (TODOS los canales)
  if (!Array.isArray(canalesSeleccionados.value)) {
    canalesSeleccionados.value = []
  }
  actualizarCanalSeleccionadoTexto()
}

function configurarRutas(listaObjetosRuta) {
  const mapaUnico = new Map()
  ;(listaObjetosRuta || []).forEach(item => {
    const r = String(item.ruta || '').trim()
    const c = String(item.canal || 'SC').trim()
    const v = String(item.vendedor || '').trim()
    if (r && !mapaUnico.has(norm(r))) {
      mapaUnico.set(norm(r), { ruta: r, canal: c, vendedor: v })
    }
  })

  catalogoRutas.value = Array.from(mapaUnico.values()).sort((a, b) =>
    a.ruta.localeCompare(b.ruta, undefined, { numeric: true })
  )

  if (!Array.isArray(rutasSeleccionadas.value)) {
    rutasSeleccionadas.value = []
  }
  sincronizarRutasConCanales()
}

function inicializarDesdeStorage(props = {}) {
  // 1. Cargar catálogos
  if (props.catalogo_rutas && Array.isArray(props.catalogo_rutas)) {
    configurarRutas(props.catalogo_rutas)
  }
  if (props.canales && Array.isArray(props.canales)) {
    configurarCanales(props.canales)
  }
  if (props.dias_calendario) {
    diasCalendario.value = props.dias_calendario
  }

  // 2. Restaurar estado persistido
  const guardado = recuperarEstado()
  if (guardado) {
    if (guardado.fechaInicio) fechaInicio.value = guardado.fechaInicio
    else if (props.fecha_inicio_default) fechaInicio.value = props.fecha_inicio_default

    if (guardado.fechaCorte) fechaCorte.value = guardado.fechaCorte
    else if (props.fecha_corte_default) fechaCorte.value = props.fecha_corte_default

    if (guardado.mes) mes.value = guardado.mes
    else if (props.mes_default) mes.value = props.mes_default

    if (guardado.busqueda !== undefined) busqueda.value = guardado.busqueda
    if (guardado.vistaCumplimiento) vistaCumplimiento.value = guardado.vistaCumplimiento
    if (guardado.kpiActivo) kpiActivo.value = guardado.kpiActivo
    if (guardado.modoTemporalCumplimiento) modoTemporalCumplimiento.value = guardado.modoTemporalCumplimiento

    if (Array.isArray(guardado.canalesSeleccionados)) {
      canalesSeleccionados.value = guardado.canalesSeleccionados
    }
    if (guardado.canalSeleccionado) {
      canalSeleccionado.value = guardado.canalSeleccionado
    }
    if (Array.isArray(guardado.rutasSeleccionadas)) {
      rutasSeleccionadas.value = guardado.rutasSeleccionadas
    }
  } else {
    // Primera carga sin almacenamiento previo
    if (props.fecha_inicio_default) fechaInicio.value = props.fecha_inicio_default
    if (props.fecha_corte_default) fechaCorte.value = props.fecha_corte_default
    if (props.mes_default) mes.value = props.mes_default
    canalesSeleccionados.value = []
    canalSeleccionado.value = 'TODOS'
    rutasSeleccionadas.value = []
  }

  // 3. Coherencia y filtros aplicados
  sincronizarRutasConCanales()
  aplicarFiltros()
}

// Watchers de auto-persistencia y coherencia reactiva
watch(
  [fechaInicio, fechaCorte, mes, busqueda, vistaCumplimiento, kpiActivo, modoTemporalCumplimiento],
  () => {
    guardarEstado()
  }
)

watch(
  canalesSeleccionados,
  () => {
    sincronizarRutasConCanales()
    guardarEstado()
  },
  { deep: true }
)

watch(
  rutasSeleccionadas,
  () => {
    guardarEstado()
  },
  { deep: true }
)

async function cargarDiasHabiles() {
  try {
    const res = await axios.post('/supervisor/avance/dias-habiles', {
      mes: mes.value,
      fecha_corte: fechaCorte.value
    })
    diasCalendario.value = res.data
  } catch (err) {
    console.error('[AvanceStore] Error al cargar días hábiles:', err)
  }
}

async function cargarCumplimiento() {
  cargandoCumplimiento.value = true
  errorCumplimiento.value = null

  try {
    const res = await axios.post('/supervisor/avance/cumplimiento/data', {
      vista: vistaCumplimiento.value,
      kpi: kpiActivo.value,
      fecha_inicio: filtrosAplicados.value.fechaInicio || fechaInicio.value,
      fecha_corte: filtrosAplicados.value.fechaCorte || fechaCorte.value,
      canales: filtrosAplicados.value.canalesSeleccionados || canalesSeleccionados.value,
      rutas: filtrosAplicados.value.rutasSeleccionadas || rutasSeleccionadas.value,
      modo_temporal: modoTemporalCumplimiento.value
    })
    datosCumplimiento.value = res.data
    if (res.data?.dias_calendario) {
      diasCalendario.value = res.data.dias_calendario
    }
  } catch (err) {
    console.error('[AvanceStore] Error al cargar cumplimiento:', err)
    errorCumplimiento.value = err?.response?.data?.message || err?.message || 'Error al cargar datos de cumplimiento'
  } finally {
    cargandoCumplimiento.value = false
  }
}

export function useAvanceStore() {
  return reactive({
    fechaInicio,
    fechaCorte,
    mes,
    canalSeleccionado,
    canalesSeleccionados,
    canalesDisponibles,
    catalogoRutas,
    rutasSeleccionadas,
    rutasPermitidasPorCanal,
    busqueda,
    diasCalendario,
    filtrosAplicados,
    cargandoCumplimiento,
    datosCumplimiento,
    errorCumplimiento,
    vistaCumplimiento,
    kpiActivo,
    modoTemporalCumplimiento,

    aplicarFiltros,
    guardarEstado,
    recuperarEstado,
    inicializarDesdeStorage,
    configurarCanales,
    configurarRutas,
    sincronizarRutasConCanales,
    cargarDiasHabiles,
    cargarCumplimiento
  })
}
