<template>
  <SupervisorLayout title="Auditoría | Histórico de Resoluciones">
    <div class="space-y-3 max-w-full font-sans select-none pb-6">
      <AuditoriaSubTabs active-tab="historico" />

      <!-- BARRA DE FILTROS SUPERIOR Y BUSCADOR (APPLE LIQUID GLASS) -->
      <div
        class="sticky top-0 z-40 apple-glass-card rounded-[16px] p-2.5 sm:p-3 flex flex-wrap items-center justify-between gap-2.5 shadow-sm border border-white/70"
      >
        <div class="flex flex-wrap items-center gap-2">
          <!-- Estado Auditoría -->
          <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1 text-[11px]">
            <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Estado:</span>
            <select
              v-model="estadoAuditoria"
              @change="consultar"
              class="bg-transparent text-[11px] font-semibold text-[#1D1D1F] focus:outline-none cursor-pointer"
            >
              <option value="APROBADO">Solo Aprobados</option>
              <option value="RECHAZADO">Solo Rechazados</option>
              <option value="TODOS">Todos los Estados</option>
            </select>
          </div>

          <!-- Tipo Evento -->
          <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1 text-[11px]">
            <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Evento:</span>
            <select
              v-model="tipoEvento"
              @change="consultar"
              class="bg-transparent text-[11px] font-semibold text-[#1D1D1F] focus:outline-none cursor-pointer"
            >
              <option value="TODOS">Todos los Eventos</option>
              <option value="INACTIVADO">Inactivaciones / Bajas</option>
              <option value="MODIFICADO_RUTA">Cambios de Ruta</option>
              <option value="MODIFICADO_DATOS">Modificación de Datos</option>
              <option value="MODIFICADO_DIA">Cambios de Día</option>
              <option value="NUEVO">Nuevos Clientes</option>
              <option value="REACTIVADO">Reactivaciones</option>
            </select>
          </div>

          <!-- Selector Canal -->
          <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1 text-[11px]">
            <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Canal:</span>
            <select
              v-model="canalSeleccionado"
              @change="consultar"
              class="bg-transparent text-[11px] font-semibold text-[#1D1D1F] focus:outline-none cursor-pointer"
            >
              <option value="TODOS">Todos los Canales</option>
              <option v-for="c in canales" :key="c" :value="c">{{ c }}</option>
            </select>
          </div>

          <!-- Rango Fechas -->
          <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1 text-[11px]">
            <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Desde:</span>
            <input
              type="date"
              v-model="fechaInicio"
              @change="consultar"
              class="bg-transparent text-[11px] font-semibold text-[#1D1D1F] focus:outline-none cursor-pointer"
            />
            <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">Hasta:</span>
            <input
              type="date"
              v-model="fechaFin"
              @change="consultar"
              class="bg-transparent text-[11px] font-semibold text-[#1D1D1F] focus:outline-none cursor-pointer"
            />
          </div>

          <!-- Botón Limpiar -->
          <button
            type="button"
            @click="restablecerFiltros"
            class="flex items-center gap-1 apple-control-pill text-[#6E6E73] hover:text-[#1D1D1F] text-[11px] font-semibold px-2.5 py-1 rounded-[10px] cursor-pointer"
          >
            <span>Limpiar</span>
          </button>
        </div>

        <!-- Buscador Reactivo -->
        <div class="relative flex-1 min-w-[220px] max-w-sm">
          <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-3 py-1.5">
            <svg class="w-3.5 h-3.5 text-[#86868B] shrink-0 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input
              v-model="busqueda"
              type="text"
              placeholder="Buscar cliente, ruta, dictamen, auditor..."
              class="w-full bg-transparent text-[#1D1D1F] placeholder-[#86868B] text-[11px] focus:outline-none font-medium"
            />
            <button
              v-if="busqueda && busqueda.trim().length > 0"
              type="button"
              @click="busqueda = ''"
              class="text-[10px] bg-black/[0.06] hover:bg-black/[0.12] rounded-full w-4 h-4 flex items-center justify-center font-bold text-[#6E6E73] hover:text-[#1D1D1F] cursor-pointer shrink-0"
            >
              ✕
            </button>
          </div>
        </div>
      </div>

      <!-- TABLA DE HISTÓRICO -->
      <div class="apple-glass-card rounded-[16px] overflow-hidden border border-white/70 shadow-sm">
        <div class="px-4 py-2.5 bg-white/50 border-b border-black/[0.06] flex flex-wrap justify-between items-center gap-2 text-xs">
          <div class="flex items-center gap-2">
            <span class="w-2 h-2 rounded-full bg-[#0071E3]"></span>
            <span class="font-bold text-[#1D1D1F] uppercase tracking-wide text-[11px]">Histórico de Auditoría</span>
          </div>
          <span class="apple-control-pill px-2.5 py-0.5 rounded-[7px] text-[#6E6E73] font-mono font-bold font-num text-[11px]">
            {{ registrosFiltrados.length }} registros resueltos
          </span>
        </div>

        <div class="max-h-[calc(100vh-250px)] min-h-[380px] overflow-y-auto overflow-x-auto custom-scroll relative">
          <table class="w-full min-w-[1100px] text-left border-collapse text-[11px]">
            <thead class="text-[#6E6E73] font-bold uppercase text-[10px] tracking-tight border-b border-black/[0.06] select-none sticky top-0 z-20 bg-white/90 backdrop-blur-md">
              <tr>
                <th class="p-3 font-sans w-20">ID / Fecha</th>
                <th class="p-3 font-sans min-w-[180px]">Cliente</th>
                <th class="p-3 text-center font-sans">Foto</th>
                <th class="p-3 font-sans">Tipo Evento</th>
                <th class="p-3 font-sans min-w-[180px]">Detalle Modificación</th>
                <th class="p-3 font-sans">Ruta / Canal</th>
                <th class="p-3 font-sans">Evaluación Riesgo</th>
                <th class="p-3 min-w-[180px] font-sans">Ventas 5 Meses (fact_ventas)</th>
                <th class="p-3 text-center font-sans">Dictamen</th>
                <th class="p-3 font-sans min-w-[200px]">Justificación / Auditor</th>
              </tr>
            </thead>

            <tbody class="divide-y divide-black/[0.04]">
              <template v-if="cargando">
                <tr v-for="i in 8" :key="`skel-hi-${i}`">
                  <td class="p-3"><div class="h-4 w-16 bg-black/[0.06] rounded animate-pulse"></div></td>
                  <td class="p-3"><div class="h-4 w-36 bg-black/[0.06] rounded animate-pulse mb-1"></div><div class="h-3 w-24 bg-black/[0.06] rounded animate-pulse"></div></td>
                  <td class="p-3 text-center"><div class="h-5 w-14 mx-auto bg-black/[0.06] rounded animate-pulse"></div></td>
                  <td class="p-3"><div class="h-5 w-20 bg-black/[0.06] rounded animate-pulse"></div></td>
                  <td class="p-3"><div class="h-4 w-32 bg-black/[0.06] rounded animate-pulse"></div></td>
                  <td class="p-3"><div class="h-4 w-20 bg-black/[0.06] rounded animate-pulse"></div></td>
                  <td class="p-3"><div class="h-5 w-28 bg-black/[0.06] rounded animate-pulse"></div></td>
                  <td class="p-3"><div class="h-6 w-36 bg-black/[0.06] rounded animate-pulse"></div></td>
                  <td class="p-3 text-center"><div class="h-5 w-20 mx-auto bg-black/[0.06] rounded animate-pulse"></div></td>
                  <td class="p-3"><div class="h-4 w-40 bg-black/[0.06] rounded animate-pulse"></div></td>
                </tr>
              </template>

              <tr v-else-if="errorMsg">
                <td colspan="10" class="p-12 text-center text-[#C9342C] font-semibold">
                  Error al cargar histórico: {{ errorMsg }}
                </td>
              </tr>

              <tr v-else-if="registrosFiltrados.length === 0">
                <td colspan="10" class="py-16 px-4 text-center">
                  <div class="flex flex-col items-center justify-center max-w-sm mx-auto select-none font-sans">
                    <div class="w-12 h-12 rounded-full bg-white/80 border border-black/[0.06] flex items-center justify-center text-[#86868B] mb-3 shadow-xs">
                      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z" />
                      </svg>
                    </div>
                    <h4 class="text-sm font-bold text-[#1D1D1F] mb-1">Sin resoluciones históricas</h4>
                    <p class="text-xs text-[#86868B] mb-4 text-center">
                      No se encontraron registros de auditoría resueltos con los filtros seleccionados.
                    </p>
                    <button
                      type="button"
                      @click="restablecerFiltros"
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-[8px] apple-control-pill text-[#0071E3] text-xs font-semibold cursor-pointer"
                    >
                      <span>Restablecer Filtros</span>
                    </button>
                  </div>
                </td>
              </tr>

              <template v-else>
                <tr
                  v-for="r in registrosFiltrados"
                  :key="r.id"
                  class="hover:bg-black/[0.02] transition-colors select-none"
                >
                  <!-- ID / Fecha -->
                  <td class="p-3 font-mono">
                    <span class="font-bold text-[#1D1D1F]">#{{ r.id }}</span>
                    <span class="text-[9px] text-[#86868B] block mt-0.5">{{ r.fecha_resolucion || r.fecha_auditoria }}</span>
                  </td>

                  <!-- Cliente -->
                  <td class="p-3 font-sans">
                    <div class="font-bold text-[#1D1D1F]">{{ r.cliente }}</div>
                    <div class="text-[10px] text-[#86868B] font-mono">
                      ID: {{ r.cliente_id }} · {{ r.direccion }}
                    </div>
                  </td>

                  <!-- Foto -->
                  <td class="p-3 text-center whitespace-nowrap font-sans">
                    <button
                      v-if="Number(r.cant_fotos || 0) > 0"
                      type="button"
                      @click="abrirFotosCliente(r)"
                      class="group inline-flex items-center gap-1.5 px-2 py-1 rounded-[8px] apple-control-pill cursor-pointer"
                      :title="`Ver ${r.cant_fotos} fotografía${r.cant_fotos > 1 ? 's' : ''}`"
                    >
                      <img
                        v-if="r.foto_reciente_url"
                        :src="r.foto_reciente_url"
                        class="w-5 h-5 rounded-[4px] object-cover border border-black/[0.08]"
                        loading="lazy"
                      />
                      <span class="text-[10px] font-bold text-[#0071E3] font-num">
                        {{ r.cant_fotos }} foto{{ r.cant_fotos > 1 ? 's' : '' }}
                      </span>
                    </button>
                    <span
                      v-else
                      class="inline-flex items-center gap-1 text-[10px] text-[#86868B] font-mono px-2 py-1 rounded-[6px] bg-black/[0.03] border border-black/[0.05] select-none"
                    >
                      <svg class="w-3 h-3 text-[#C7C7CC]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                      </svg>
                      <span>Sin foto</span>
                    </span>
                  </td>

                  <!-- Tipo Evento -->
                  <td class="p-3 font-sans">
                    <span class="px-2 py-0.5 rounded-[5px] text-[10px] font-bold bg-white/80 border border-black/[0.08] text-[#1D1D1F]">
                      {{ r.tipo_evento }}
                    </span>
                  </td>

                  <!-- Detalle Modificación -->
                  <td class="p-3 font-sans">
                    <div v-if="r.tipo_evento === 'MODIFICADO_RUTA'" class="flex items-center gap-1 text-[10px]">
                      <span class="line-through text-[#86868B]">{{ r.ruta_anterior }}</span>
                      <span class="text-[#0071E3] font-bold">&rarr;</span>
                      <strong class="text-[#0071E3]">{{ r.ruta_nueva }}</strong>
                    </div>
                    <div v-else-if="r.tipo_evento === 'MODIFICADO_DIA'" class="flex items-center gap-1 text-[10px]">
                      <span class="line-through text-[#86868B]">{{ r.valor_anterior }}</span>
                      <span class="text-[#0071E3] font-bold">&rarr;</span>
                      <strong class="text-[#0071E3]">{{ r.valor_nuevo }}</strong>
                    </div>
                    <div v-else class="text-[10px] truncate max-w-[200px]" :title="`${r.campo_afectado}: ${r.valor_anterior} → ${r.valor_nuevo}`">
                      <strong v-if="r.campo_afectado">{{ r.campo_afectado }}: </strong>
                      <span class="line-through text-[#86868B]">{{ r.valor_anterior }}</span> &rarr; <strong>{{ r.valor_nuevo }}</strong>
                    </div>
                  </td>

                  <!-- Ruta / Canal -->
                  <td class="p-3 font-sans">
                    <span class="font-bold text-[#1D1D1F]">{{ r.ruta }}</span>
                    <span class="text-[10px] text-[#86868B] block font-mono">{{ r.canal }} · {{ r.dia }}</span>
                  </td>

                  <!-- Evaluación Riesgo -->
                  <td class="p-3 font-sans">
                    <span
                      v-if="r.evaluacion_riesgo"
                      class="inline-block px-2 py-0.5 rounded-[5px] text-[10px] font-bold border"
                      :class="r.evaluacion_riesgo.badge_class || 'bg-[#F5F5F7] text-[#6E6E73] border-[#E5E5EA]'"
                    >
                      {{ r.evaluacion_riesgo.mensaje_evaluacion }}
                    </span>
                  </td>

                  <!-- Ventas 5 Meses -->
                  <td class="p-3 font-sans">
                    <span
                      v-if="!r.historico_ventas || !r.historico_ventas.meses || r.historico_ventas.meses.length === 0"
                      class="text-[#86868B] italic text-[11px]"
                    >
                      Sin ventas
                    </span>
                    <div v-else class="flex items-center gap-2">
                      <div class="flex items-center gap-1">
                        <div
                          v-for="(m, mIdx) in r.historico_ventas.meses"
                          :key="mIdx"
                          class="flex flex-col items-center min-w-[34px] px-1 py-0.5 rounded-[6px] bg-white/75 border border-black/[0.06]"
                        >
                          <span class="text-[9px] text-[#86868B] font-mono">{{ String(m.periodo || '').slice(5) }}</span>
                          <span
                            class="text-[10px] font-num"
                            :class="Number(m.monto || 0) > 0 ? 'text-[#1D1D1F] font-bold' : 'text-[#86868B]'"
                          >
                            {{ Number(m.monto || 0) > 0 ? Math.round(Number(m.monto)) : '-' }}
                          </span>
                        </div>
                      </div>
                      <div class="text-right shrink-0 border-l border-black/[0.08] pl-2">
                        <span class="text-[9px] text-[#86868B] block uppercase font-bold tracking-wider">Total 5m</span>
                        <strong class="text-[11px] font-num text-[#0071E3] font-bold">
                          Bs {{ fmtNum(r.historico_ventas.total_5m) }}
                        </strong>
                      </div>
                    </div>
                  </td>

                  <!-- Dictamen -->
                  <td class="p-3 text-center font-sans">
                    <span
                      v-if="r.estado_auditoria === 'APROBADO'"
                      class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-[#34C759]/15 text-[#248A3D] border border-[#34C759]/30"
                    >APROBADO</span>
                    <span
                      v-else-if="r.estado_auditoria === 'RECHAZADO'"
                      class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-[#FF3B30]/15 text-[#C9342C] border border-[#FF3B30]/30"
                    >RECHAZADO</span>
                    <span
                      v-else
                      class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-[#FF9500]/15 text-[#B26B00] border border-[#FF9500]/30"
                    >{{ r.estado_auditoria }}</span>
                  </td>

                  <!-- Justificación / Auditor -->
                  <td class="p-3 font-sans">
                    <div class="font-medium text-[#1D1D1F] leading-tight">
                      {{ r.observaciones || 'Sin observación' }}
                    </div>
                    <span class="text-[10px] text-[#86868B] font-mono block mt-0.5">
                      Auditor: <strong>{{ r.auditado_por || 'SUPERVISOR' }}</strong> · {{ r.fecha_resolucion || '-' }}
                    </span>
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>

      <AuditoriaFotosModal ref="fotosModalRef" />
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import axios from 'axios';
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue';
import AuditoriaSubTabs from './Components/AuditoriaSubTabs.vue';
import AuditoriaFotosModal from './Components/AuditoriaFotosModal.vue';

const props = defineProps({
  canales: { type: Array, default: () => [] },
  rutas: { type: Array, default: () => [] },
  archivos_base: { type: Array, default: () => [] },
});

const fotosModalRef = ref(null);

const canalSeleccionado = ref('TODOS');
const tipoEvento = ref('TODOS');
const estadoAuditoria = ref('APROBADO');
const fechaInicio = ref('');
const fechaFin = ref('');
const busqueda = ref('');

const cargando = ref(false);
const errorMsg = ref(null);
const registrosCargados = ref([]);

function fmtNum(n) {
  return Number(n || 0).toLocaleString('es-BO', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

async function consultar() {
  cargando.value = true;
  errorMsg.value = null;

  const payload = {
    canal: canalSeleccionado.value !== 'TODOS' ? canalSeleccionado.value : null,
    tipo_evento: tipoEvento.value !== 'TODOS' ? tipoEvento.value : null,
    estado_auditoria: estadoAuditoria.value,
    fecha_inicio: fechaInicio.value || null,
    fecha_fin: fechaFin.value || null,
    limit: 400,
  };

  try {
    const { data } = await axios.post(route('supervisor.auditoria.historico.consultar'), payload);
    registrosCargados.value = data.registros || [];
  } catch (err) {
    console.error('[AuditoriaHistorico] Error:', err);
    errorMsg.value = err.response?.data?.error || err.response?.data?.message || err.message || 'Error al obtener histórico de auditoría';
  } finally {
    cargando.value = false;
  }
}

const registrosFiltrados = computed(() => {
  const query = String(busqueda.value || '').toLowerCase().trim();
  const palabras = query.split(/\s+/).filter(Boolean);
  if (palabras.length === 0) return registrosCargados.value;

  return registrosCargados.value.filter((r) => {
    const searchTarget = [
      r.id || '',
      r.cliente || '',
      r.cliente_id || '',
      r.ruta || '',
      r.canal || '',
      r.tipo_evento || '',
      r.observaciones || '',
      r.auditado_por || '',
      r.estado_auditoria || '',
      r.evaluacion_riesgo?.mensaje_evaluacion || '',
    ]
      .join(' ')
      .toLowerCase();

    return palabras.every((p) => searchTarget.includes(p));
  });
});

function restablecerFiltros() {
  busqueda.value = '';
  canalSeleccionado.value = 'TODOS';
  tipoEvento.value = 'TODOS';
  estadoAuditoria.value = 'APROBADO';
  fechaInicio.value = '';
  fechaFin.value = '';
  consultar();
}

function abrirFotosCliente(r) {
  fotosModalRef.value?.abrir(r.cliente_id, r.cliente, r.ruta);
}

onMounted(() => {
  consultar();
});
</script>

