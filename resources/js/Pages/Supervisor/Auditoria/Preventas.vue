<template>
  <SupervisorLayout title="Auditoría | Preventas Intra-día">
    <div class="space-y-3 max-w-full font-sans select-none pb-6">
      <!-- Navegación de Submódulos Auditoría -->
      <AuditoriaSubTabs active-tab="preventas" />

      <!-- BARRA DE FILTROS SUPERIOR Y BUSCADOR (APPLE LIQUID GLASS) -->
      <div
        class="sticky top-0 z-40 apple-glass-card rounded-[16px] p-2.5 sm:p-3 flex flex-wrap items-center justify-between gap-2.5 shadow-sm border border-white/70"
      >
        <div class="flex flex-wrap items-center gap-2">
          <!-- Selector Tipo de Evento -->
          <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-2.5 py-1.5 text-[11px]">
            <label class="font-bold text-[#6E6E73] text-[10px] uppercase tracking-wider">Evento:</label>
            <select
              v-model="tipoEvento"
              @change="consultar"
              class="bg-transparent font-semibold text-[#1D1D1F] focus:outline-none cursor-pointer text-[11px]"
            >
              <option value="TODOS">Todos los eventos</option>
              <option value="CANCELADO">Solo Cancelados / Eliminados</option>
              <option value="MODIFICADO">Solo Modificados / Editados</option>
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

          <!-- Botón Limpiar Filtros -->
          <button
            type="button"
            @click="restablecerFiltros"
            class="flex items-center gap-1.5 apple-control-pill text-[#6E6E73] hover:text-[#1D1D1F] text-[11px] font-semibold px-2.5 py-1.5 rounded-[10px] cursor-pointer"
            title="Restablecer todos los filtros"
          >
            <svg class="w-3.5 h-3.5 text-[#6E6E73]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            <span>Limpiar</span>
          </button>
        </div>

        <!-- Buscador Reactivo Inteligente -->
        <div class="relative flex-1 min-w-[240px] max-w-sm">
          <div class="flex items-center gap-1.5 apple-control-pill rounded-[10px] px-3 py-1.5">
            <svg class="w-3.5 h-3.5 text-[#86868B] shrink-0 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input
              v-model="busqueda"
              type="text"
              placeholder="Buscar por N° preventa, cliente, vendedor, producto..."
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

      <!-- TABLA PRINCIPAL DE NOVEDADES EN PREVENTAS -->
      <div class="apple-glass-card rounded-[16px] overflow-hidden border border-white/70 shadow-sm">
        <div class="px-4 py-2.5 bg-white/50 border-b border-black/[0.06] flex flex-wrap justify-between items-center gap-2 text-xs">
          <div class="flex items-center gap-3 flex-wrap">
            <span class="w-2 h-2 rounded-full bg-[#0071E3] shadow-[0_0_6px_rgba(0,113,227,0.7)]"></span>
            <span class="font-bold text-[#1D1D1F] uppercase tracking-wide text-[11px]">Novedades de Preventa Intra-día</span>
            <span v-if="resumen.total_cancelados > 0" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#FF3B30]/15 text-[#C9342C]">
              {{ resumen.total_cancelados }} Canceladas ({{ fmtBs(resumen.monto_cancelado_total) }})
            </span>
            <span v-if="resumen.total_modificados > 0" class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-[#FF9500]/15 text-[#B26B00]">
              {{ resumen.total_modificados }} Editadas
            </span>
          </div>
          <div class="flex items-center gap-2">
            <span
              v-if="registrosFiltrados.length > 0"
              class="apple-control-pill px-2.5 py-0.5 rounded-[7px] font-mono font-bold font-num text-[11px]"
              :class="diferenciaNetaTotal < 0 ? 'text-[#C9342C]' : 'text-[#248A3D]'"
            >
              Dif. Neta: {{ diferenciaNetaTotal >= 0 ? '+' : '' }}{{ fmtBs(diferenciaNetaTotal) }}
            </span>
            <span class="apple-control-pill px-2.5 py-0.5 rounded-[7px] text-[#6E6E73] font-mono font-bold font-num text-[11px]">
              {{ registrosFiltrados.length }} registros
            </span>
          </div>
        </div>

        <div class="max-h-[calc(100vh-250px)] min-h-[380px] overflow-y-auto overflow-x-auto custom-scroll relative">
          <table class="w-full min-w-[1040px] text-left border-collapse font-num text-[11px]">
            <thead class="sticky top-0 z-20 bg-white/90 backdrop-blur-md text-[#6E6E73] font-bold text-[10px] uppercase tracking-tight border-b border-black/[0.06]">
              <tr>
                <th class="py-2.5 px-3 font-sans">Preventa</th>
                <th class="py-2.5 px-3 text-center font-sans">Evento</th>
                <th class="py-2.5 px-3 font-sans">Fecha Preventa</th>
                <th class="py-2.5 px-3 min-w-[160px] font-sans">Cliente</th>
                <th class="py-2.5 px-3 text-center font-sans">Foto</th>
                <th class="py-2.5 px-3 font-sans">Vendedor</th>
                <th class="py-2.5 px-3 text-center font-sans">Prod ID</th>
                <th class="py-2.5 px-3 text-center font-sans">Variación Cant.</th>
                <th class="py-2.5 px-3 text-right font-sans">Monto Previo &rarr; Nuevo</th>
                <th class="py-2.5 px-3 text-right font-sans">Diferencia</th>
                <th class="py-2.5 px-3 text-center font-sans">Detectado En</th>
              </tr>
            </thead>

            <tbody class="divide-y divide-black/[0.04]">
              <!-- Skeleton Loading -->
              <template v-if="cargando">
                <tr v-for="i in 8" :key="`skel-pv-${i}`">
                  <td class="py-2.5 px-3"><div class="h-4 w-20 bg-black/[0.06] rounded animate-pulse"></div></td>
                  <td class="py-2.5 px-3 text-center"><div class="h-5 w-16 mx-auto bg-black/[0.06] rounded animate-pulse"></div></td>
                  <td class="py-2.5 px-3"><div class="h-3.5 w-20 bg-black/[0.06] rounded animate-pulse"></div></td>
                  <td class="py-2.5 px-3"><div class="h-4 w-36 bg-black/[0.06] rounded animate-pulse"></div></td>
                  <td class="py-2.5 px-3 text-center"><div class="h-5 w-14 mx-auto bg-black/[0.06] rounded animate-pulse"></div></td>
                  <td class="py-2.5 px-3"><div class="h-3.5 w-24 bg-black/[0.06] rounded animate-pulse"></div></td>
                  <td class="py-2.5 px-3 text-center"><div class="h-3.5 w-12 mx-auto bg-black/[0.06] rounded animate-pulse"></div></td>
                  <td class="py-2.5 px-3 text-center"><div class="h-4 w-24 mx-auto bg-black/[0.06] rounded animate-pulse"></div></td>
                  <td class="py-2.5 px-3 text-right"><div class="h-4 w-28 ml-auto bg-black/[0.06] rounded animate-pulse"></div></td>
                  <td class="py-2.5 px-3 text-right"><div class="h-4 w-20 ml-auto bg-black/[0.06] rounded animate-pulse"></div></td>
                  <td class="py-2.5 px-3 text-center"><div class="h-3.5 w-24 mx-auto bg-black/[0.06] rounded animate-pulse"></div></td>
                </tr>
              </template>

              <!-- Error -->
              <tr v-else-if="errorMsg">
                <td colspan="11" class="py-16 text-center text-[#991B1B] font-semibold text-[11px]">
                  Error al consultar auditoría de preventas: {{ errorMsg }}
                </td>
              </tr>

              <!-- Empty State -->
              <tr v-else-if="registrosFiltrados.length === 0">
                <td colspan="11" class="py-16 px-4 text-center">
                  <div class="flex flex-col items-center justify-center max-w-sm mx-auto select-none font-sans">
                    <div class="w-12 h-12 rounded-full bg-white/80 border border-black/[0.06] flex items-center justify-center text-[#86868B] mb-3 shadow-xs">
                      <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                      </svg>
                    </div>
                    <h4 class="text-sm font-bold text-[#1D1D1F] mb-1">Sin modificaciones de preventa</h4>
                    <p class="text-xs text-[#86868B] mb-4 text-center">
                      No se encontraron eventos intra-día de preventas con los filtros actuales.
                    </p>
                    <button
                      type="button"
                      @click="restablecerFiltros"
                      class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-[8px] apple-control-pill text-[#0071E3] text-xs font-semibold cursor-pointer"
                    >
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                      </svg>
                      <span>Restablecer Búsqueda y Filtros</span>
                    </button>
                  </div>
                </td>
              </tr>

              <!-- Filas de Datos -->
              <template v-else>
                <tr
                  v-for="(item, idx) in registrosFiltrados"
                  :key="`${item.nro_preventa}_${item.producto_id}_${idx}`"
                  class="hover:bg-black/[0.02] transition-colors"
                >
                  <td class="py-2.5 px-3 font-bold text-[#1D1D1F]">
                    #{{ item.nro_preventa }}
                    <span class="block text-[9px] text-[#86868B] font-mono truncate max-w-[120px]">
                      {{ item.archivo_origen || '-' }}
                    </span>
                  </td>

                  <td class="py-2.5 px-3 text-center font-sans">
                    <span
                      v-if="item.tipo_evento === 'CANCELADO'"
                      class="px-2 py-0.5 rounded-[4px] font-bold text-[9px] bg-[#FFF0F0] text-[#991B1B] border border-[#FFCDCD] uppercase tracking-wider"
                    >ELIMINADO</span>
                    <span
                      v-else
                      class="px-2 py-0.5 rounded-[4px] font-bold text-[9px] bg-[#FFFBEB] text-[#92400E] border border-[#FDE68A] uppercase tracking-wider"
                    >EDITADO</span>
                  </td>

                  <td class="py-2.5 px-3 font-medium text-[#1D1D1F] font-mono">
                    {{ item.fecha_preventa }}
                  </td>

                  <td class="py-2.5 px-3 font-semibold text-[#1D1D1F] font-sans">
                    {{ item.cliente || 'Cliente sin nombre' }}
                    <span class="block text-[9px] text-[#6E6E73] font-mono">
                      ID {{ item.cliente_id || '-' }}
                    </span>
                  </td>

                  <!-- Foto -->
                  <td class="py-2.5 px-3 text-center whitespace-nowrap font-sans">
                    <button
                      v-if="Number(item.cant_fotos || 0) > 0"
                      type="button"
                      @click="abrirFotosCliente(item)"
                      class="group inline-flex items-center gap-1.5 px-2 py-1 rounded-[8px] apple-control-pill cursor-pointer"
                      :title="`Ver ${item.cant_fotos} fotografía${item.cant_fotos > 1 ? 's' : ''} de este cliente`"
                    >
                      <img
                        v-if="item.foto_reciente_url"
                        :src="item.foto_reciente_url"
                        class="w-5 h-5 rounded-[4px] object-cover border border-black/[0.08]"
                        loading="lazy"
                      />
                      <span class="text-[10px] font-bold text-[#0071E3] font-num">
                        {{ item.cant_fotos }} foto{{ item.cant_fotos > 1 ? 's' : '' }}
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

                  <td class="py-2.5 px-3 text-[#1D1D1F] font-medium font-sans">
                    {{ item.vendedor || '—' }}
                  </td>

                  <td class="py-2.5 px-3 text-center font-mono text-[#6E6E73] font-semibold">
                    {{ item.producto_id }}
                  </td>

                  <td class="py-2.5 px-3 text-center font-mono font-bold">
                    <span class="text-[#6E6E73]">{{ item.cantidad_anterior }}</span>
                    <span class="text-[#86868B] mx-0.5">&rarr;</span>
                    <span class="text-[#1D1D1F]">{{ item.cantidad_nueva }}</span>
                    <span
                      class="text-[10px] ml-1"
                      :class="Number(item.diferencia_cantidad || 0) < 0 ? 'text-[#991B1B]' : 'text-[#1E6B37]'"
                    >
                      ({{ Number(item.diferencia_cantidad || 0) > 0 ? `+${item.diferencia_cantidad}` : item.diferencia_cantidad || 0 }})
                    </span>
                  </td>

                  <td class="py-2.5 px-3 text-right font-mono font-medium">
                    <span class="text-[#6E6E73] line-through text-[10px]">{{ fmtBs(item.monto_anterior) }}</span>
                    <span class="text-[#86868B] mx-0.5">&rarr;</span>
                    <strong class="text-[#1D1D1F]">{{ fmtBs(item.monto_nuevo) }}</strong>
                  </td>

                  <td
                    class="py-2.5 px-3 text-right font-mono font-bold"
                    :class="
                      floatVal(item.diferencia_monto) < 0
                        ? 'text-[#991B1B]'
                        : floatVal(item.diferencia_monto) > 0
                          ? 'text-[#1E6B37]'
                          : 'text-[#6E6E73]'
                    "
                  >
                    {{ floatVal(item.diferencia_monto) > 0 ? `+${fmtBs(item.diferencia_monto)}` : fmtBs(item.diferencia_monto) }}
                  </td>

                  <td class="py-2.5 px-3 text-center text-[#6E6E73] font-mono text-[10px]">
                    {{ item.detectado_en }}
                  </td>
                </tr>
              </template>
            </tbody>
          </table>
        </div>
      </div>

      <!-- MODAL GLOBAL VISOR DE FOTOS -->
      <AuditoriaFotosModal ref="fotosModalRef" />
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
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

const tipoEvento = ref('TODOS');
const fechaInicio = ref('');
const fechaFin = ref('');
const busqueda = ref('');
const cargando = ref(false);
const errorMsg = ref(null);
const registrosCargados = ref([]);

const resumen = reactive({
  total_eventos: 0,
  total_cancelados: 0,
  total_modificados: 0,
  monto_cancelado_total: 0.0,
  monto_diferencia_modificados: 0.0,
});

function floatVal(v) {
  const f = parseFloat(v);
  return isNaN(f) ? 0.0 : f;
}

function fmtBs(val) {
  const v = floatVal(val);
  return `Bs ${v.toLocaleString('es-BO', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })}`;
}

async function consultar() {
  cargando.value = true;
  errorMsg.value = null;

  try {
    const { data } = await axios.post(route('supervisor.auditoria.preventas.consultar'), {
      tipo_evento: tipoEvento.value,
      fecha_inicio: fechaInicio.value || null,
      fecha_fin: fechaFin.value || null,
      limit: 500,
    });

    registrosCargados.value = data.registros || [];
    if (data.resumen) {
      resumen.total_eventos = Number(data.resumen.total_eventos || 0);
      resumen.total_cancelados = Number(data.resumen.total_cancelados || 0);
      resumen.total_modificados = Number(data.resumen.total_modificados || 0);
      resumen.monto_cancelado_total = Number(data.resumen.monto_cancelado_total || 0);
      resumen.monto_diferencia_modificados = Number(data.resumen.monto_diferencia_modificados || 0);
    }
  } catch (err) {
    console.error('[AuditoriaPreventas] Error:', err);
    errorMsg.value = err.response?.data?.error || err.response?.data?.message || err.message || 'Fallo al obtener novedades de preventas';
  } finally {
    cargando.value = false;
  }
}

const registrosFiltrados = computed(() => {
  const rawQuery = String(busqueda.value || '').toLowerCase().trim();
  if (!rawQuery) return registrosCargados.value;

  const tokens = rawQuery.split(/\s+/).filter(Boolean);
  return registrosCargados.value.filter((item) => {
    const searchTarget = [
      item.nro_preventa || '',
      item.cliente || '',
      item.cliente_id || '',
      item.vendedor || '',
      item.producto_id || '',
      item.archivo_origen || '',
      item.tipo_evento || '',
    ]
      .join(' ')
      .toLowerCase();

    return tokens.every((token) => searchTarget.includes(token));
  });
});

const diferenciaNetaTotal = computed(() => {
  return registrosFiltrados.value.reduce(
    (acc, it) => acc + floatVal(it.diferencia_monto),
    0.0
  );
});

function restablecerFiltros() {
  busqueda.value = '';
  tipoEvento.value = 'TODOS';
  fechaInicio.value = '';
  fechaFin.value = '';
  consultar();
}

function abrirFotosCliente(item) {
  fotosModalRef.value?.abrir(item.cliente_id, item.cliente, item.vendedor);
}

onMounted(() => {
  consultar();
});
</script>

