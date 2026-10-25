import { ref, reactive, computed } from 'vue'
import axios from 'axios'

const STORAGE_KEY = 'supervisor_preventa_filtros_estado'

function norm(str) {
  return String(str || '').trim().toUpperCase()
}

// Variables reactivas internas (singleton)
const fecha = ref(new Date().toISOString().slice(0, 10))
const canalesDisponibles = ref([])
const canalesSeleccionados = ref([]) // Array de strings mayúsculas
const catalogoRutas = ref([]) // [{ ruta, canal, vendedor }]
const rutasSeleccionadas = ref([]) // Array de strings mayúsculas
const busqueda = ref('')
const horarioEstricto = ref(false)
const diasCalendario = ref(null)

const filtrosAplicados = ref({
  canales: [],
  rutas: [],
  busqueda: '',
  fecha: '',
  horario_estricto: false
})

const tabActivo = ref('general')
const loadingRanking = ref(false)
const errorRanking = ref(null)
const datosBrutos = ref(null)
const rutasRanking = ref([])

function aplicarFiltros() {
  filtrosAplicados.value = {
    canales: [...(canalesSeleccionados.value || [])],
    rutas: [...(rutasSeleccionadas.value || [])],
    busqueda: String(busqueda.value || '').trim(),
    fecha: fecha.value,
    horario_estricto: Boolean(horarioEstricto.value)
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
      canales: canalesSeleccionados.value || [],
      rutas: rutasSeleccionadas.value || [],
      busqueda: busqueda.value || '',
      fecha: fecha.value,
      horario_estricto: Boolean(horarioEstricto.value)
    }
    sessionStorage.setItem(STORAGE_KEY, JSON.stringify(estado))
  } catch (e) {
    console.warn('[PreventaStore] No se pudo guardar estado:', e)
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
    } else if (limpios.length > 0) {
      canalesSeleccionados.value = [norm(limpios[0])]
    } else {
      canalesSeleccionados.value = []
    }
  }
}

const rutasPermitidasPorCanal = computed(() => {
  const cat = catalogoRutas.value || []
  const cSel = canalesSeleccionados.value || []
  const cDisp = canalesDisponibles.value || []

  if (cSel.length === 0) return cat
  if (cDisp.length > 0 && cSel.length === cDisp.length) {
    return cat
  }
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

function sincronizarRutasAlCambiarCanal() {
  const permitidas = rutasPermitidasPorCanal.value || []
  rutasSeleccionadas.value = permitidas.map(item => norm(item.ruta))
  guardarEstado()
}

function inicializarDesdeStorage(props = {}) {
  const guardado = recuperarEstado()

  if (props.catalogo_rutas && Array.isArray(props.catalogo_rutas)) {
    configurarRutas(props.catalogo_rutas)
  }
  if (props.canales && Array.isArray(props.canales)) {
    configurarCanales(props.canales)
  }

  // Priorizar la fecha con datos reales más recientes si no hay fecha guardada o si la guardada no tiene datos
  if (props.fecha_default) {
    if (guardado && guardado.fecha && guardado.fecha <= props.fecha_default) {
      fecha.value = guardado.fecha
    } else {
      fecha.value = props.fecha_default
    }
  } else if (guardado && guardado.fecha) {
    fecha.value = guardado.fecha
  }

  if (guardado) {
    if (guardado.busqueda) busqueda.value = guardado.busqueda
    if (typeof guardado.horario_estricto === 'boolean') {
      horarioEstricto.value = guardado.horario_estricto
    }
    if (Array.isArray(guardado.canales) && guardado.canales.length > 0) {
      canalesSeleccionados.value = guardado.canales
    }
    if (Array.isArray(guardado.rutas) && guardado.rutas.length > 0) {
      rutasSeleccionadas.value = guardado.rutas
    }
  } else {
    // Por defecto marcar canal TDB si existe
    const tdb = (canalesDisponibles.value || []).find(c => norm(c) === 'TDB' || norm(c).includes('TDB'))
    if (tdb) {
      canalesSeleccionados.value = [norm(tdb)]
    }
  }
  aplicarFiltros()
}

function canalActivo(canal) {
  if (!canal) return true
  const cNorm = norm(canal)
  const aplicados = filtrosAplicados.value.canales
  const sel = (aplicados && aplicados.length > 0) ? aplicados : (canalesSeleccionados.value || [])
  if (sel.length === 0) return false
  if (sel.length === (canalesDisponibles.value || []).length) return true
  return sel.includes(cNorm)
}

function rutaActiva(ruta) {
  if (!ruta) return true
  if ((catalogoRutas.value || []).length === 0) return true
  const rNorm = norm(ruta)
  const aplicadas = filtrosAplicados.value.rutas
  const sel = (aplicadas && aplicadas.length > 0) ? aplicadas : (rutasSeleccionadas.value || [])
  if (sel.length === 0) return false
  return sel.includes(rNorm)
}

const rutasFiltradas = computed(() => {
  const q = String(filtrosAplicados.value.busqueda ?? busqueda.value ?? '').trim().toUpperCase()
  return (rutasRanking.value || []).filter(r => {
    const cOk = canalActivo(r.canal)
    const rOk = rutaActiva(r.ruta)
    let matchTexto = true
    if (q) {
      const matchRuta = String(r.ruta || '').toUpperCase().includes(q)
      const matchVend = String(r.vendedor || '').toUpperCase().includes(q)
      matchTexto = matchRuta || matchVend
    }
    return cOk && rOk && matchTexto
  })
})

const textoDiasHabiles = computed(() => {
  const d = diasCalendario.value
  if (!d) return '-'
  const trans = d.dias_transcurridos ?? d.transcurridos ?? 0
  const tot = d.dias_totales ?? d.dias_habiles_totales ?? d.totales ?? 0
  const rest = d.dias_restantes ?? d.restantes ?? 0
  return `${trans} / ${tot} (${rest} restan)`
})

const estaCargando = computed(() => Boolean(loadingRanking.value))

function enriquecerRuta(r) {
  const k1 = r.kpi1_horario || {}
  const k2 = r.kpi2_visitas || {}
  const k3 = r.kpi3_objetivo || {}
  const k4 = r.kpi4_efectividad || {}
  const k5 = r.kpi5_compra_minima || {}
  const k6 = r.kpi6_reposicion || {}
  const k7 = r.kpi7_ampliacion || {}
  const k8 = r.kpi8_covers || {}

  r.kpis = {
    horario_ingreso: {
      cumple: Boolean(k1.cumple),
      puntos: Number(k1.puntos || 0),
      estado: k1.estado || 'NO_EVALUADO',
      metricas: {
        hora_ingreso_detectada: k1.primer_checkin || k1.hora_web || null,
        hora_web: k1.hora_web || null,
        hora_app: k1.hora_movil || null,
        retraso_minutos: Number(k1.retraso_minutos || 0),
        estado: k1.estado || 'NO_EVALUADO'
      }
    },
    clientes_visitados: {
      cumple: Boolean(k2.cumple),
      puntos: Number(k2.puntos || 0),
      metricas: {
        cartera_plan: Number(k2.cartera_plan || 0),
        visitados: Number(k2.visitados_activos || 0),
        inactivos: Number(k2.inactivos_visitados || 0),
        fueraPlan: Number(k2.fuera_de_plan || 0),
        dif: Number(k2.visitados_activos || 0) - Number(k2.cartera_plan || 0),
        cobertura_pct: Number(k2.cobertura_pct || 0)
      }
    },
    cumplimiento_objetivo: {
      cumple: Boolean(k3.cumple),
      puntos: Number(k3.puntos || 0),
      metricas: {
        meta_dia: Number(k3.cuota_diaria || 0),
        venta_hoy: Number(k3.venta_hoy || 0),
        alcance_pct: Number(k3.alcance_pct || 0),
        diferencia: Number(k3.venta_hoy || 0) - Number(k3.cuota_diaria || 0),
        meta_mes: Number(k3.meta_mensual || 0),
        facturado_previo: Number(k3.facturado_previo || 0),
        brecha: Number(k3.brecha || 0)
      }
    },
    efectividad_ruta: {
      cumple: Boolean(k4.cumple),
      puntos: Number(k4.puntos || 0),
      metricas: {
        cartera_plan: Number(k4.cartera_plan || 0),
        clientes_efectivos: Number(k4.compradores || 0),
        clientes_sobre_minimo: Number(k4.compradores_sobre_70 || 0),
        clientes_bajo_minimo: Number(k4.compradores_bajo_70 || 0),
        efectividad_pct: Number(k4.efectividad_pct || 0)
      }
    },
    compra_minima: {
      cumple: Boolean(k5.cumple),
      puntos: Number(k5.puntos || 0),
      metricas: {
        total_compraron: Number(k5.compradores || 0),
        bajo_minimo: Number(k5.compradores_bajo_70 || 0),
        aptos: Math.max(0, Number(k5.compradores || 0) - Number(k5.compradores_bajo_70 || 0)),
        pct: Number(k5.compradores || 0) > 0
          ? (((Number(k5.compradores || 0) - Number(k5.compradores_bajo_70 || 0)) / Number(k5.compradores || 1)) * 100)
          : 0
      }
    },
    pedido_reposicion: {
      cumple: Boolean(k6.cumple),
      puntos: Number(k6.puntos || 0),
      evaluado: Boolean(k6.evaluado),
      metricas: {
        evaluables: Number(k6.evaluables || 0),
        cumplen: Number(k6.cumplen || 0),
        tasa_pct: Number(k6.tasa_pct || 0)
      }
    },
    ampliacion_portafolio: {
      cumple: Boolean(k7.cumple),
      puntos: Number(k7.puntos || 0),
      evaluado: Boolean(k7.evaluado),
      metricas: {
        total_compradores: Number(k7.total_compradores || 0),
        cumplen: Number(k7.cumplen || 0),
        tasa_pct: Number(k7.tasa_pct || 0)
      }
    },
    covers: {
      cumple: Boolean(k8.cumple),
      puntos: Number(k8.puntos || 0),
      evaluado: Boolean(k8.evaluado),
      metricas: {
        total_compradores: Number(k8.total_compradores || 0),
        cumplen: Number(k8.cumplen || 0),
        tasa_pct: Number(k8.tasa_pct || 0)
      }
    }
  }
  return r
}

async function cargarDatosRanking() {
  loadingRanking.value = true
  errorRanking.value = null
  try {
    const payload = {
      fecha: fecha.value,
      canales: canalesSeleccionados.value || [],
      rutas: rutasSeleccionadas.value || [],
      estricto_horario: Boolean(horarioEstricto.value)
    }

    const res = await axios.post('/supervisor/preventas/cumplimiento/data', payload)
    const data = res.data || {}

    if (data.fecha) {
      fecha.value = data.fecha
    }
    datosBrutos.value = data

    const rutasArray = (data.rutas || data.ranking || []).map(enriquecerRuta)
    rutasRanking.value = rutasArray

    if (data.dias_calendario) {
      diasCalendario.value = data.dias_calendario
    }

    if (rutasArray.length > 0) {
      if ((canalesDisponibles.value || []).length === 0) {
        const canalesUnicos = Array.from(new Set(rutasArray.map(r => r.canal))).filter(Boolean)
        configurarCanales(canalesUnicos)
      }
      if ((catalogoRutas.value || []).length === 0) {
        const rutasCanal = rutasArray.map(r => ({ ruta: r.ruta, canal: r.canal, vendedor: r.vendedor }))
        configurarRutas(rutasCanal)
      }
    }
    aplicarFiltros()
    guardarEstado()
  } catch (err) {
    console.error('[PreventaStore] Error cargando ranking:', err)
    errorRanking.value = err?.response?.data?.message || err.message || 'Error al cargar datos de evaluación'
  } finally {
    loadingRanking.value = false
  }
}

// Proxy reactivo singleton para que todos los refs y computeds se desenvuelvan automáticamente en JS y template
const storeInstance = reactive({
  fecha,
  canalesDisponibles,
  canalesSeleccionados,
  catalogoRutas,
  rutasSeleccionadas,
  busqueda,
  horarioEstricto,
  diasCalendario,
  filtrosAplicados,
  tabActivo,
  loadingRanking,
  errorRanking,
  datosBrutos,
  rutasRanking,
  rutasPermitidasPorCanal,
  rutasFiltradas,
  textoDiasHabiles,
  estaCargando,
  aplicarFiltros,
  recuperarEstado,
  guardarEstado,
  inicializarDesdeStorage,
  configurarCanales,
  configurarRutas,
  sincronizarRutasAlCambiarCanal,
  canalActivo,
  rutaActiva,
  cargarDatosRanking
})

export function usePreventaStore() {
  return storeInstance
}
