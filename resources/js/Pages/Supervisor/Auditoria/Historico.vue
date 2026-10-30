<template>
  <SupervisorLayout title="Auditoría | Histórico de Resoluciones de Ruteo">
    <div class="space-y-3 max-w-full font-sans select-none pb-8">
      <AuditoriaSubTabs active-tab="historico" />

      <!-- PANEL SUPERIOR DE FILTROS Y CATEGORÍAS (APPLE LIQUID GLASS) -->
      <div class="sticky top-0 z-40 apple-glass-card rounded-[16px] p-3 space-y-2.5 shadow-sm border border-white/70">
        <!-- NIVEL 1: ESTADO DE RESOLUCIÓN + SELECTOR DE CATEGORÍA -->
        <div class="flex flex-wrap items-center justify-between gap-3">
          <!-- Segmented Control de Estados Resueltos -->
          <div class="flex items-center gap-1 bg-black/[0.05] p-1 rounded-[12px] text-xs">
            <button
              type="button"
              @click="cambiarEstado('RESUELTOS')"
              class="px-3 py-1.5 rounded-[9px] cursor-pointer transition-all flex items-center gap-1.5 font-bold"
              :class="filtros.estado_auditoria === 'RESUELTOS' ? 'bg-white text-[#0071E3] shadow-xs border border-black/[0.06]' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
            >
              <span>Todos Resueltos</span>
              <span class="font-mono text-[10px] px-1.5 py-0.2 rounded-full bg-[#0071E3]/15 text-[#0071E3]">
                {{ (conteos.aprobados + conteos.rechazados + conteos.descartados).toLocaleString('es-BO') }}
              </span>
            </button>

            <button
              type="button"
              @click="cambiarEstado('APROBADO')"
              class="px-3 py-1.5 rounded-[9px] cursor-pointer transition-all flex items-center gap-1.5 font-bold"
              :class="filtros.estado_auditoria === 'APROBADO' ? 'bg-white text-[#248A3D] shadow-xs border border-black/[0.06]' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
            >
              <span class="w-1.5 h-1.5 rounded-full bg-[#34C759]"></span>
              <span>Aprobados</span>
              <span class="font-mono text-[10px] px-1.5 py-0.2 rounded-full bg-[#34C759]/15 text-[#248A3D]">
                {{ conteos.aprobados.toLocaleString('es-BO') }}
              </span>
            </button>

            <button
              type="button"
              @click="cambiarEstado('RECHAZADO')"
              class="px-3 py-1.5 rounded-[9px] cursor-pointer transition-all flex items-center gap-1.5 font-bold"
              :class="filtros.estado_auditoria === 'RECHAZADO' ? 'bg-white text-[#C9342C] shadow-xs border border-black/[0.06]' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
            >
              <span class="w-1.5 h-1.5 rounded-full bg-[#FF3B30]"></span>
              <span>Rechazados</span>
              <span class="font-mono text-[10px] px-1.5 py-0.2 rounded-full bg-[#FF3B30]/15 text-[#C9342C]">
                {{ conteos.rechazados.toLocaleString('es-BO') }}
              </span>
            </button>

            <button
              type="button"
              @click="cambiarEstado('DESCARTADO')"
              class="px-3 py-1.5 rounded-[9px] cursor-pointer transition-all flex items-center gap-1.5 font-bold"
              :class="filtros.estado_auditoria === 'DESCARTADO' ? 'bg-white text-[#636366] shadow-xs border border-black/[0.06]' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
            >
              <span class="w-1.5 h-1.5 rounded-full bg-[#8E8E93]"></span>
              <span>Descartados</span>
              <span class="font-mono text-[10px] px-1.5 py-0.2 rounded-full bg-black/[0.06] text-[#6E6E73]">
                {{ conteos.descartados.toLocaleString('es-BO') }}
              </span>
            </button>
          </div>

          <!-- Buscador Reactivo -->
          <div class="relative w-full sm:w-72">
            <input
              type="text"
              v-model="filtros.busqueda"
              @keyup.enter="consultar"
              placeholder="Buscar cliente, ID, auditor, ruta..."
              class="w-full apple-control-pill text-xs font-medium pl-8 pr-7 py-1.5 rounded-[10px] text-[#1D1D1F] placeholder-[#86868B] focus:outline-none focus:ring-1 focus:ring-[#0071E3]"
            />
            <svg class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-[#86868B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <button
              v-if="filtros.busqueda"
              type="button"
              @click="filtros.busqueda = ''; consultar()"
              class="absolute right-2.5 top-1/2 -translate-y-1/2 text-[#86868B] hover:text-[#1D1D1F] text-xs font-bold"
            >
              ✕
            </button>
          </div>
        </div>

        <!-- NIVEL 2: CHIPS DE CATEGORÍAS -->
        <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar scroll-touch pt-1 border-t border-black/[0.04]">
          <button
            v-for="cat in categoriasDisponibles"
            :key="cat.id"
            type="button"
            @click="seleccionarCategoria(cat.id)"
            class="flex items-center gap-1.5 px-3 py-1.5 rounded-[10px] text-xs font-semibold whitespace-nowrap transition-all duration-150 cursor-pointer shrink-0"
            :class="categoriaActiva === cat.id
              ? 'bg-[#0071E3] text-white shadow-[0_2px_8px_rgba(0,113,227,0.35)]'
              : 'apple-control-pill text-[#6E6E73] hover:text-[#1D1D1F]'"
          >
            <span>{{ cat.nombre }}</span>
            <span
              class="text-[10px] font-mono px-1.5 py-0.2 rounded-full font-bold"
              :class="categoriaActiva === cat.id ? 'bg-white/25 text-white' : 'bg-black/[0.06] text-[#6E6E73]'"
            >
              {{ (conteos.categorias[cat.id] || 0).toLocaleString('es-BO') }}
            </span>
          </button>
        </div>

        <!-- NIVEL 3: FILTROS MULTIFACÉTICOS -->
        <div class="flex flex-wrap items-center gap-2 pt-1 border-t border-black/[0.04]">
          <!-- Selector de Canal -->
          <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1 text-[11px]">
            <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Canal:</span>
            <select
              v-model="filtros.canal"
              @change="consultar"
              class="bg-transparent text-[11px] font-semibold text-[#1D1D1F] focus:outline-none cursor-pointer"
            >
              <option value="TODOS">Todos los Canales</option>
              <option v-for="c in canales" :key="c" :value="c">{{ c }}</option>
            </select>
          </div>

          <!-- Selector de Archivo Base -->
          <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1 text-[11px] max-w-xs">
            <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Archivo:</span>
            <select
              v-model="filtros.archivo_origen"
              @change="consultar"
              class="bg-transparent text-[11px] font-semibold text-[#1D1D1F] focus:outline-none cursor-pointer truncate max-w-[190px]"
            >
              <option value="TODOS">Todos los Archivos</option>
              <option v-for="a in archivos_base" :key="a" :value="a">{{ a }}</option>
            </select>
          </div>

          <!-- Rango Fechas -->
          <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1 text-[11px]">
            <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Desde:</span>
            <input
              type="date"
              v-model="filtros.fecha_inicio"
              @change="consultar"
              class="bg-transparent text-[11px] font-semibold text-[#1D1D1F] focus:outline-none cursor-pointer"
            />
            <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Hasta:</span>
            <input
              type="date"
              v-model="filtros.fecha_fin"
              @change="consultar"
              class="bg-transparent text-[11px] font-semibold text-[#1D1D1F] focus:outline-none cursor-pointer"
            />
          </div>

          <!-- Limpiar Filtros -->
          <button
            type="button"
            @click="restablecerFiltros"
            class="apple-control-pill text-[#6E6E73] hover:text-[#1D1D1F] text-[11px] font-semibold px-2.5 py-1 rounded-[10px] cursor-pointer transition-all"
          >
            Limpiar
          </button>
        </div>
      </div>

      <!-- ESTADO DE CARGA -->
      <div v-if="cargando" class="apple-glass-card rounded-[16px] p-12 text-center border border-white/70">
        <div class="inline-block animate-spin w-8 h-8 border-3 border-[#0071E3] border-t-transparent rounded-full mb-3"></div>
        <div class="text-sm font-bold text-[#1D1D1F]">Consultando histórico de resoluciones...</div>
        <div class="text-xs text-[#86868B] mt-0.5">Recuperando bitácora inmutable de auditoría</div>
      </div>

      <!-- BANDEJA VACÍA -->
      <div
        v-else-if="registros.length === 0"
        class="apple-glass-card rounded-[16px] p-12 text-center border border-white/70"
      >
        <div class="w-12 h-12 rounded-full bg-black/[0.04] flex items-center justify-center mx-auto mb-3">
          <svg class="w-6 h-6 text-[#86868B]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
        </div>
        <div class="text-sm font-bold text-[#1D1D1F]">No hay resoluciones históricas</div>
        <div class="text-xs text-[#86868B] mt-1 max-w-md mx-auto">
          No se encontraron dictámenes previos para los criterios seleccionados en la bitácora.
        </div>
      </div>

      <!-- TABLA DE HISTÓRICO DE RESOLUCIONES -->
      <div v-else class="apple-glass-card rounded-[16px] overflow-hidden border border-white/70 shadow-xs">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="bg-black/[0.03] border-b border-black/[0.06] text-[#6E6E73] text-[10px] uppercase font-bold tracking-wider">
                <th class="py-2.5 px-3">Estado / Dictamen</th>
                <th class="py-2.5 px-3">Tipo de Modificación</th>
                <th class="py-2.5 px-3">Cliente</th>
                <th class="py-2.5 px-3">Ruta / Canal / Día</th>
                <th class="py-2.5 px-3">Detalle del Cambio</th>
                <th class="py-2.5 px-3">Auditor & Fecha</th>
                <th class="py-2.5 px-3">Observaciones de Resolución</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-black/[0.04]">
              <tr
                v-for="reg in registros"
                :key="reg.id"
                class="hover:bg-black/[0.02] transition-colors duration-150"
              >
                <!-- Estado / Dictamen -->
                <td class="py-2.5 px-3 whitespace-nowrap">
                  <span
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border"
                    :class="obtenerBadgeEstadoClase(reg.estado_auditoria)"
                  >
                    {{ reg.estado_auditoria }}
                  </span>
                  <div class="text-[10px] font-mono text-[#86868B] mt-1">
                    ID #{{ reg.id }}
                  </div>
                </td>

                <!-- Tipo de Modificación -->
                <td class="py-2.5 px-3 whitespace-nowrap">
                  <span
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border"
                    :class="obtenerBadgeClase(reg.categoria_clave)"
                  >
                    {{ reg.etiqueta_evento }}
                  </span>
                  <div class="text-[10px] font-mono text-[#86868B] mt-1">
                    Detectado: {{ reg.fecha_auditoria }}
                  </div>
                </td>

                <!-- Cliente -->
                <td class="py-2.5 px-3 min-w-[200px]">
                  <div class="flex items-start gap-1.5">
                    <div>
                      <div class="font-bold text-[#1D1D1F] leading-tight">
                        {{ reg.cliente }}
                      </div>
                      <div class="text-[10px] font-mono text-[#6E6E73] mt-0.5">
                        ID: #{{ reg.cliente_id }}
                      </div>
                      <div class="text-[10px] text-[#86868B] truncate max-w-[220px] mt-0.5">
                        {{ reg.direccion }}
                      </div>
                    </div>

                    <!-- Botón de Fotos -->
                    <button
                      v-if="reg.cant_fotos > 0"
                      type="button"
                      @click="abrirFotos(reg)"
                      class="shrink-0 apple-control-pill px-1.5 py-0.5 rounded-[6px] text-[10px] font-bold text-[#0071E3] hover:bg-[#0071E3]/10 cursor-pointer"
                      title="Ver fotografías de visitas"
                    >
                      📷 {{ reg.cant_fotos }}
                    </button>
                  </div>
                </td>

                <!-- Ruta / Canal / Día -->
                <td class="py-2.5 px-3 whitespace-nowrap">
                  <div class="font-bold text-[#1D1D1F] font-mono">
                    {{ reg.ruta }}
                  </div>
                  <div class="text-[10px] text-[#6E6E73]">
                    Canal: <span class="font-semibold text-[#1D1D1F]">{{ reg.canal }}</span>
                  </div>
                  <div class="text-[10px] text-[#86868B] capitalize">
                    Día: {{ reg.dia }}
                  </div>
                </td>

                <!-- Detalle del Cambio -->
                <td class="py-2.5 px-3 min-w-[200px]">
                  <div class="space-y-1">
                    <div class="text-[10px] font-mono text-[#86868B] uppercase">
                      Campo: <span class="font-bold text-[#1D1D1F]">{{ reg.campo_afectado }}</span>
                    </div>

                    <div v-if="reg.categoria_clave === 'CAMBIOS_RUTA' || reg.categoria_clave === 'CAMBIOS_DIA'" class="flex items-center gap-1.5 text-xs font-mono">
                      <span class="px-1.5 py-0.5 rounded bg-black/[0.04] text-[#6E6E73] line-through">
                        {{ reg.valor_anterior }}
                      </span>
                      <span class="text-[#86868B]">→</span>
                      <span class="px-1.5 py-0.5 rounded bg-[#0071E3]/10 text-[#0071E3] font-bold">
                        {{ reg.valor_nuevo }}
                      </span>
                    </div>

                    <div v-else class="text-[11px]">
                      <div v-if="reg.valor_anterior !== '-'" class="text-[#86868B] text-[10px]">
                        Antes: <span class="line-through text-[#6E6E73]">{{ reg.valor_anterior }}</span>
                      </div>
                      <div class="text-[#1D1D1F] font-semibold">
                        {{ reg.valor_nuevo }}
                      </div>
                    </div>
                  </div>
                </td>

                <!-- Auditor & Fecha -->
                <td class="py-2.5 px-3 whitespace-nowrap">
                  <div class="font-bold text-[#1D1D1F]">
                    {{ reg.auditado_por || 'SUPERVISOR' }}
                  </div>
                  <div class="text-[10px] font-mono text-[#86868B] mt-0.5">
                    {{ reg.fecha_resolucion || '-' }}
                  </div>
                </td>

                <!-- Observaciones -->
                <td class="py-2.5 px-3 min-w-[180px]">
                  <div class="text-[11px] text-[#1D1D1F] font-medium leading-relaxed">
                    {{ reg.observaciones || 'Sin observaciones adicionales.' }}
                  </div>
                  <div class="text-[10px] font-mono text-[#86868B] mt-1">
                    Archivo: {{ reg.archivo_origen }}
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- PIE DE TABLA -->
        <div class="px-4 py-3 bg-black/[0.02] border-t border-black/[0.05] flex items-center justify-between text-xs text-[#6E6E73]">
          <div>
            Mostrando <span class="font-bold text-[#1D1D1F]">{{ registros.length }}</span> de <span class="font-bold text-[#1D1D1F]">{{ totalRegistros.toLocaleString('es-BO') }}</span> eventos resueltos
          </div>

          <div class="text-[11px] text-[#86868B]">
            Bitácora de auditoría histórica
          </div>
        </div>
      </div>

      <!-- MODAL DE FOTOGRAFÍAS -->
      <AuditoriaFotosModal ref="fotosModalRef" />
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import axios from 'axios';
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue';
import AuditoriaSubTabs from '@/Pages/Supervisor/Auditoria/Components/AuditoriaSubTabs.vue';
import AuditoriaFotosModal from '@/Pages/Supervisor/Auditoria/Components/AuditoriaFotosModal.vue';

const props = defineProps({
  canales: { type: Array, default: () => [] },
  rutas: { type: Array, default: () => [] },
  archivos_base: { type: Array, default: () => [] },
  initial_categoria: { type: String, default: 'TODOS' },
});

const categoriasDisponibles = [
  { id: 'TODOS', nombre: 'Todos los Cambios' },
  { id: 'ALTAS_REACTIVACIONES', nombre: 'Altas y Reactivaciones' },
  { id: 'BAJAS_INACTIVADOS', nombre: 'Bajas e Inactivaciones' },
  { id: 'CAMBIOS_RUTA', nombre: 'Cambios de Ruta' },
  { id: 'CAMBIOS_DIA', nombre: 'Cambios de Día' },
  { id: 'CAMBIO_DE_CLUSTER', nombre: 'Cambios de Clúster' },
  { id: 'MODIFICADO_VENDEDOR', nombre: 'Cambios de Vendedor' },
  { id: 'MODIFICADO_COORDENADAS', nombre: 'Coordenadas' },
  { id: 'FRECUENCIA_VISITA', nombre: 'Frecuencia Logística' },
  { id: 'MODIFICACION_DATOS', nombre: 'Modificación de Datos' },
];

const categoriaActiva = ref(props.initial_categoria || 'TODOS');
const cargando = ref(false);
const registros = ref([]);
const totalRegistros = ref(0);
const fotosModalRef = ref(null);

const conteos = reactive({
  total: 0,
  pendientes: 0,
  aprobados: 0,
  rechazados: 0,
  descartados: 0,
  categorias: {},
});

const filtros = reactive({
  estado_auditoria: 'RESUELTOS',
  canal: 'TODOS',
  archivo_origen: 'TODOS',
  fecha_inicio: '',
  fecha_fin: '',
  busqueda: '',
});

function cambiarEstado(nuevoEstado) {
  filtros.estado_auditoria = nuevoEstado;
  consultar();
}

function seleccionarCategoria(catId) {
  categoriaActiva.value = catId;
  consultar();
}

function restablecerFiltros() {
  filtros.estado_auditoria = 'RESUELTOS';
  filtros.canal = 'TODOS';
  filtros.archivo_origen = 'TODOS';
  filtros.fecha_inicio = '';
  filtros.fecha_fin = '';
  filtros.busqueda = '';
  categoriaActiva.value = 'TODOS';
  consultar();
}

async function consultar() {
  cargando.value = true;
  try {
    const params = {
      estado_auditoria: filtros.estado_auditoria,
      categoria: categoriaActiva.value,
      canal: filtros.canal,
      archivo_origen: filtros.archivo_origen,
      fecha_inicio: filtros.fecha_inicio,
      fecha_fin: filtros.fecha_fin,
      busqueda: filtros.busqueda,
      limit: 200,
    };

    const res = await axios.get(route('supervisor.auditoria.historico.consultar'), { params });
    registros.value = res.data.registros || [];
    totalRegistros.value = res.data.total || 0;
    if (res.data.conteos) {
      conteos.total = res.data.conteos.total || 0;
      conteos.pendientes = res.data.conteos.pendientes || 0;
      conteos.aprobados = res.data.conteos.aprobados || 0;
      conteos.rechazados = res.data.conteos.rechazados || 0;
      conteos.descartados = res.data.conteos.descartados || 0;
      conteos.categorias = res.data.conteos.categorias || {};
    }
  } catch (e) {
    console.error('Error consultando histórico de auditoría:', e);
  } finally {
    cargando.value = false;
  }
}

function abrirFotos(reg) {
  if (fotosModalRef.value) {
    fotosModalRef.value.abrir(reg.cliente_id, reg.cliente, reg.ruta);
  }
}

function obtenerBadgeEstadoClase(estado) {
  switch (estado) {
    case 'APROBADO':
      return 'bg-[#EBF9EF] text-[#248A3D] border-[#34C759]/30';
    case 'RECHAZADO':
      return 'bg-[#FDF0EF] text-[#C9342C] border-[#FF3B30]/30';
    case 'DESCARTADO':
      return 'bg-black/[0.05] text-[#636366] border-black/10';
    default:
      return 'bg-black/[0.04] text-[#1D1D1F] border-black/10';
  }
}

function obtenerBadgeClase(catClave) {
  switch (catClave) {
    case 'ALTAS_REACTIVACIONES':
      return 'bg-[#EBF9EF] text-[#1E6B37] border-[#D1F2D9]';
    case 'BAJAS_INACTIVADOS':
      return 'bg-[#FDF0EF] text-[#C9342C] border-[#FADCD5]';
    case 'CAMBIOS_RUTA':
      return 'bg-[#EBF5FF] text-[#0071E3] border-[#B3D7FF]';
    case 'CAMBIOS_DIA':
      return 'bg-[#FFF8E6] text-[#B7791F] border-[#FCE8B3]';
    case 'CAMBIO_DE_CLUSTER':
      return 'bg-[#F5EEF8] text-[#8E44AD] border-[#E8DAEF]';
    case 'MODIFICADO_VENDEDOR':
      return 'bg-[#E8F8F5] text-[#16A085] border-[#D1F2EB]';
    case 'MODIFICADO_COORDENADAS':
      return 'bg-[#FEF5E7] text-[#D35400] border-[#FADBD8]';
    case 'FRECUENCIA_VISITA':
      return 'bg-[#EBF5FB] text-[#2980B9] border-[#D4E6F1]';
    default:
      return 'bg-black/[0.05] text-[#1D1D1F] border-black/10';
  }
}

onMounted(() => {
  consultar();
});
</script>
