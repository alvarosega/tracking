import { ref, reactive, computed } from 'vue'
import axios from 'axios'

const STORAGE_KEY = 'supervisor_avance_filtros_estado'

function norm(str) {
  return String(str || '').trim().toUpperCase()
}

// Variables reactivas singleton
const fechaInicio = ref('')
const fechaCorte = ref('')
const mes = ref('')
const canalSeleccionado = ref('TDB')
const canalesSeleccionados = ref(['TDB'])
const canalesDisponibles = ref([])
const catalogoRutas = ref([])
const rutasSeleccionadas = ref([])
const busqueda = ref('')
const diasCalendario = ref(null)

const filtrosAplicados = ref({
  fechaInicio: '',
  fechaCorte: '',
  mes: '',
  canalSeleccionado: 'TDB',
  canalesSeleccionados: ['TDB'],
  rutasSeleccionadas: [],
  busqueda: ''
})

const cargandoCumplimiento = ref(false)
const datosCumplimiento = ref(null)
const errorCumplimiento = ref(null)
const vistaCumplimiento = ref('resumen') // 'resumen' o 'kpi'
const kpiActivo = ref('vol_general')
const modoTemporalCumplimiento = ref('acumulado') // 'acumulado' o 'diario'

function aplicarFiltros() {
  filtrosAplicados.value = {
    fechaInicio: fechaInicio.value,
    fechaCorte: fechaCorte.value,
    mes: mes.value,
    canalSeleccionado: canalSeleccionado.value,
    canalesSeleccionados: [...(canalesSeleccionados.value || [])],
    rutasSeleccionadas: [...(rutasSeleccionadas.value || [])],
    busqueda: String(busqueda.value || '').trim()
  }
  guardarEstado()
}

function recuperarEstado() {
  try {
    const raw = sessionStorage.getItem(STORAGE_KEY)
    return raw ? JSON.parse(raw) : null
  } catch {
    return null
  }
}

function guardarEstado() {
  try {
    const estado = {
      fechaInicio: fechaInicio.value,
      fechaCorte: fechaCorte.value,
      mes: mes.value,
      canalSeleccionado: canalSeleccionado.value,
      canalesSeleccionados: canalesSeleccionados.value || [],
      rutasSeleccionadas: rutasSeleccionadas.value || [],
      busqueda: busqueda.value || '',
      vistaCumplimiento: vistaCumplimiento.value,
      kpiActivo: kpiActivo.value,
      modoTemporalCumplimiento: modoTemporalCumplimiento.value
    }
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(estado))
  } catch (e) {
    console.warn('[AvanceStore] No se pudo guardar estado:', e)
  }
}

function configurarCanales(listaCanales) {
  const limpios = Array.from(
    new Set(
      (listaCanales || [])
        .map(c => String(c || '').trim())
        .filter(c => c && c.toUpperCase() !== 'TODOS')
    )
  )
  canalesDisponibles.value = limpios

  if (!Array.isArray(canalesSeleccionados.value) || canalesSeleccionados.value.length === 0) {
    const tdb = limpios.find(c => norm(c) === 'TDB' || norm(c).includes('TDB'))
    if (tdb) {
      canalesSeleccionados.value = [norm(tdb)]
      canalSeleccionado.value = norm(tdb)
    } else if (limpios.length > 0) {
      canalesSeleccionados.value = [norm(limpios[0])]
      canalSeleccionado.value = norm(limpios[0])
    }
  }
}

const rutasPermitidasPorCanal = computed(() => {
  const cat = catalogoRutas.value || []
  const cSel = canalesSeleccionados.value || []
  const cDisp = canalesDisponibles.value || []

  if (cSel.length === 0) return cat
  if (cDisp.length > 0 && cSel.length === cDisp.length) return cat
  const setC = new Set(cSel.map(norm))
  return cat.filter(item => setC.has(norm(item.canal)))
})

function configurarRutas(listaObjetosRuta) {
  const mapaUnico = new Map()
  ;(listaObjetosRuta || []).forEach(item => {
    const r = String(item.ruta || '').trim()
    const c = String(item.canal || '').trim()
    const v = String(item.vendedor || '').trim()
    if (r && !mapaUnico.has(norm(r))) {
      mapaUnico.set(norm(r), { ruta: r, canal: c, vendedor: v })
    }
  })

  catalogoRutas.value = Array.from(mapaUnico.values()).sort((a, b) => a.ruta.localeCompare(b.ruta))
  const permitidas = (rutasPermitidasPorCanal.value || []).map(item => norm(item.ruta))
  if (!Array.isArray(rutasSeleccionadas.value) || rutasSeleccionadas.value.length === 0) {
    rutasSeleccionadas.value = permitidas
  }
}

function inicializarDesdeStorage(props = {}) {
  if (props.fecha_inicio_default) {
    fechaInicio.value = props.fecha_inicio_default
  }
  if (props.fecha_corte_default) {
    fechaCorte.value = props.fecha_corte_default
  }
  if (props.mes_default) {
    mes.value = props.mes_default
  }
  if (props.dias_calendario) {
    diasCalendario.value = props.dias_calendario
  }
  if (props.catalogo_rutas && Array.isArray(props.catalogo_rutas)) {
    configurarRutas(props.catalogo_rutas)
  }
  if (props.canales && Array.isArray(props.canales)) {
    configurarCanales(props.canales)
  }

  const guardado = recuperarEstado()
  if (guardado) {
    if (guardado.fechaInicio) fechaInicio.value = guardado.fechaInicio
    if (guardado.fechaCorte) fechaCorte.value = guardado.fechaCorte
    if (guardado.mes) mes.value = guardado.mes
    if (guardado.busqueda) busqueda.value = guardado.busqueda
    if (guardado.vistaCumplimiento) vistaCumplimiento.value = guardado.vistaCumplimiento
    if (guardado.kpiActivo) kpiActivo.value = guardado.kpiActivo
    if (guardado.modoTemporalCumplimiento) modoTemporalCumplimiento.value = guardado.modoTemporalCumplimiento
    if (Array.isArray(guardado.canalesSeleccionados) && guardado.canalesSeleccionados.length > 0) {
      canalesSeleccionados.value = guardado.canalesSeleccionados
    }
    if (Array.isArray(guardado.rutasSeleccionadas) && guardado.rutasSeleccionadas.length > 0) {
      rutasSeleccionadas.value = guardado.rutasSeleccionadas
    }
  }

  aplicarFiltros()
}

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
    cargarDiasHabiles,
    cargarCumplimiento
  })
}

