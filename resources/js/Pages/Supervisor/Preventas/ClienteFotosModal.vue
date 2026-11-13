<template>
  <Teleport to="body">
    <div
      v-if="show"
      class="fixed inset-0 z-[99999] bg-slate-900/80 backdrop-blur-xs flex items-center justify-center p-3 sm:p-5 animate-fadeIn"
      @click="$emit('close')"
    >
      <div
        class="bg-white rounded-2xl overflow-hidden max-w-2xl w-full shadow-2xl border border-slate-200 flex flex-col max-h-[90vh]"
        @click.stop
      >
        <!-- Encabezado Modal -->
        <div class="px-5 py-3.5 bg-slate-900 text-white flex items-center justify-between shrink-0">
          <div class="min-w-0 pr-3">
            <div class="flex items-center gap-2">
              <span class="text-xs font-mono font-bold bg-sky-500/20 text-sky-400 border border-sky-500/30 px-2 py-0.5 rounded">
                Cód. {{ cliente?.codigo_cliente || cliente?.cliente_id || cliente?.id || '-' }}
              </span>
              <h3 class="text-sm font-bold truncate text-white">{{ cliente?.cliente || cliente?.nombre || 'Cliente' }}</h3>
            </div>
            <p class="text-[11px] text-slate-400 font-mono mt-0.5 truncate">
              {{ cliente?.direccion || 'Sin dirección registrada' }} • {{ cliente?.tipo_negocio || 'Comercio General' }}
            </p>
          </div>
          <button
            type="button"
            @click="$emit('close')"
            class="w-7 h-7 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center transition-colors cursor-pointer shrink-0 font-bold"
            aria-label="Cerrar modal"
          >
            ✕
          </button>
        </div>

        <!-- Cuerpo del Modal -->
        <div class="p-4 sm:p-6 overflow-y-auto flex-1 min-h-[220px]">
          <!-- Estado de Carga -->
          <div v-if="cargando" class="flex flex-col items-center justify-center py-12 text-slate-400">
            <span class="w-7 h-7 border-3 border-sky-500 border-t-transparent rounded-full animate-spin mb-3"></span>
            <p class="text-xs font-mono">Cargando evidencia fotográfica...</p>
          </div>

          <!-- Error de Carga -->
          <div v-else-if="errorMsg" class="flex flex-col items-center justify-center py-10 text-rose-500 text-center px-4">
            <div class="w-10 h-10 rounded-full bg-rose-50 flex items-center justify-center text-rose-500 mb-2">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
              </svg>
            </div>
            <p class="text-xs font-bold text-rose-700">Error al consultar fotografías</p>
            <p class="text-[11px] text-slate-500 mt-0.5 max-w-sm">{{ errorMsg }}</p>
            <button
              type="button"
              @click="clienteId && cargarFotos(clienteId)"
              class="mt-3 px-3 py-1 bg-slate-900 hover:bg-slate-800 text-white rounded text-xs font-semibold cursor-pointer"
            >
              Reintentar
            </button>
          </div>

          <!-- Sin Fotos -->
          <div v-else-if="fotos.length === 0" class="flex flex-col items-center justify-center py-12 text-slate-400 text-center">
            <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-2">
              <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316zM16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z" />
              </svg>
            </div>
            <p class="text-xs font-semibold text-slate-700">No hay fotos registradas</p>
            <p class="text-[11px] text-slate-400 mt-0.5">El preventista no capturó evidencia fotográfica en esta visita.</p>
          </div>

          <!-- Galería Fotográfica -->
          <div v-else class="space-y-4">
            <div
              v-for="foto in fotos"
              :key="foto.id"
              class="border border-slate-200/90 rounded-xl overflow-hidden bg-slate-50/50 shadow-2xs"
            >
              <div class="px-3.5 py-2 bg-slate-100/80 border-b border-slate-200/80 flex items-center justify-between text-xs font-mono">
                <div class="flex items-center gap-2">
                  <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                  <span class="font-bold text-slate-800">{{ foto.fecha }} {{ foto.hora }}</span>
                  <span class="text-slate-400">•</span>
                  <span class="text-slate-600">Ruta: {{ foto.ruta }}</span>
                </div>
                <span class="text-[10px] uppercase font-semibold px-2 py-0.5 rounded bg-emerald-100 text-emerald-800">
                  {{ foto.status || 'VISITADO' }}
                </span>
              </div>

              <!-- Imagen con zoom -->
              <div class="p-3 bg-slate-950 flex items-center justify-center max-h-96 overflow-hidden">
                <img
                  :src="foto.photo_url"
                  :alt="'Evidencia visita ' + foto.fecha"
                  class="max-h-92 w-auto object-contain rounded-lg shadow-md hover:scale-105 transition-transform duration-300"
                />
              </div>

              <!-- Footer con comentarios y metadata -->
              <div class="p-3 bg-white flex items-center justify-between text-xs">
                <p class="text-slate-600 italic truncate pr-2">
                  {{ foto.comments ? `"${foto.comments}"` : 'Sin comentarios de campo' }}
                </p>
                <a
                  :href="foto.photo_url"
                  target="_blank"
                  class="text-[11px] font-mono text-sky-600 hover:text-sky-800 font-semibold underline shrink-0 cursor-pointer"
                >
                  Abrir HD ↗
                </a>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer Modal -->
        <div class="px-5 py-3 bg-slate-50 border-t border-slate-200 flex items-center justify-between shrink-0">
          <span class="text-xs font-mono text-slate-500">
            Total evidencias: <strong class="text-slate-900">{{ fotos.length }}</strong>
          </span>
          <button
            type="button"
            @click="$emit('close')"
            class="px-4 py-1.5 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-semibold shadow-xs cursor-pointer"
          >
            Cerrar
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import axios from 'axios';

const props = defineProps({
  show: { type: Boolean, default: false },
  cliente: { type: Object, default: null },
  endpointUrl: { type: String, default: null },
});

defineEmits(['close']);

const fotos = ref([]);
const cargando = ref(false);
const errorMsg = ref(null);

const clienteId = computed(() => {
  if (!props.cliente) return null;
  return props.cliente.cliente_id ?? props.cliente.id ?? props.cliente.client_id ?? props.cliente.codigo_cliente ?? null;
});

watch(
  [() => props.show, () => clienteId.value],
  async ([show, id]) => {
    if (show && id) {
      await cargarFotos(id);
    } else if (!show) {
      fotos.value = [];
      errorMsg.value = null;
    }
  },
  { immediate: true }
);

async function cargarFotos(id) {
  if (!id) return;
  cargando.value = true;
  fotos.value = [];
  errorMsg.value = null;

  try {
    let url;
    if (props.endpointUrl) {
      url = `${props.endpointUrl.replace(/\/$/, '')}/${id}`;
    } else {
      url = typeof route === 'function'
        ? route('supervisor.preventas.cumplimiento.fotos', id)
        : `/supervisor/preventas/cumplimiento/fotos/${id}`;
    }

    const res = await axios.post(url);
    const data = res.data;
    if (data?.success && Array.isArray(data.fotos)) {
      fotos.value = data.fotos;
    } else if (Array.isArray(data)) {
      fotos.value = data;
    } else if (data?.fotos && Array.isArray(data.fotos)) {
      fotos.value = data.fotos;
    } else if (data?.visitas && Array.isArray(data.visitas)) {
      fotos.value = data.visitas;
    } else {
      fotos.value = [];
    }
  } catch (error) {
    console.error('Error al cargar fotos del cliente:', error);
    errorMsg.value = error.response?.data?.message || error.response?.data?.error || error.message || 'Error de comunicación al consultar fotografías.';
  } finally {
    cargando.value = false;
  }
}
</script>

