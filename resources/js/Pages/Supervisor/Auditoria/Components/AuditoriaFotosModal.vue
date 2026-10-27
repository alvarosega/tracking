<template>
  <Teleport to="body">
    <div
      v-if="abierto"
      @click.self="cerrar"
      class="fixed inset-0 z-[100000] flex items-center justify-center bg-black/60 backdrop-blur-md p-3 sm:p-5 select-none font-sans"
    >
      <div
        class="apple-glass-card rounded-[18px] shadow-2xl w-full max-w-3xl flex flex-col overflow-hidden border border-white/80 backdrop-blur-3xl max-h-[92vh]"
      >
        <!-- Cabecera del Lightbox -->
        <div class="px-4 py-3 bg-white/75 border-b border-black/[0.06] flex items-center justify-between shrink-0">
          <div class="flex items-center gap-2 min-w-0">
            <span class="w-2.5 h-2.5 rounded-full bg-[#0071E3] shrink-0 shadow-[0_0_6px_rgba(0,113,227,0.7)]"></span>
            <div class="min-w-0">
              <h3 class="text-[13px] font-bold text-[#1D1D1F] truncate leading-tight">
                {{ cliente.nombre || 'Fotografía del Cliente' }}
              </h3>
              <p class="text-[10px] text-[#6E6E73] font-mono truncate mt-0.5">
                Código: #{{ cliente.id }}{{ cliente.ruta ? ` · Ruta: ${cliente.ruta}` : '' }}
              </p>
            </div>
          </div>

          <div class="flex items-center gap-2 shrink-0">
            <span class="px-2 py-0.5 rounded-[6px] text-[10px] font-bold font-mono text-[#0071E3] bg-[#EBF5FF] border border-[#B3D7FF]">
              <template v-if="cargando">Cargando fotos...</template>
              <template v-else-if="fotos.length === 0">0 fotografías</template>
              <template v-else>Foto {{ indice + 1 }} de {{ fotos.length }}</template>
            </span>

            <a
              v-if="fotoActual && fotoActual.photo_url"
              :href="fotoActual.photo_url"
              target="_blank"
              rel="noopener noreferrer"
              class="hidden sm:inline-flex items-center gap-1 text-[11px] font-semibold text-[#6E6E73] hover:text-[#0071E3] px-2 py-1 rounded-[6px] apple-control-pill transition-all"
              title="Abrir imagen original"
            >
              <span>Abrir ↗</span>
            </a>

            <button
              type="button"
              @click="cerrar"
              class="w-7 h-7 rounded-full apple-control-pill text-[#1D1D1F] flex items-center justify-center text-xs font-bold transition-all cursor-pointer"
            >
              ✕
            </button>
          </div>
        </div>

        <!-- Cuerpo Central del Lightbox -->
        <div class="p-3 sm:p-4 space-y-3 overflow-y-auto custom-scroll flex-1 bg-white/50">
          <!-- Loading -->
          <div
            v-if="cargando"
            class="w-full h-72 flex flex-col items-center justify-center space-y-3 bg-white/60 rounded-[12px] border border-black/[0.06]"
          >
            <div class="w-8 h-8 border-3 border-[#0071E3] border-t-transparent rounded-full animate-spin"></div>
            <span class="text-xs font-semibold text-[#6E6E73]">Recuperando fotografías del servidor...</span>
          </div>

          <!-- Error -->
          <div
            v-else-if="errorMsg"
            class="w-full py-12 text-center bg-[#FDF0EF] border border-[#FADCD5] rounded-[12px] p-4 text-[#C9342C] space-y-1"
          >
            <div class="font-bold text-xs">Error al cargar fotografías</div>
            <div class="text-[11px]">{{ errorMsg }}</div>
          </div>

          <!-- Sin Fotos -->
          <div
            v-else-if="fotos.length === 0"
            class="w-full py-16 flex flex-col items-center justify-center space-y-2 text-center bg-white/60 rounded-[12px] border border-black/[0.06]"
          >
            <div class="w-12 h-12 rounded-full bg-white flex items-center justify-center text-[#86868B] shadow-xs border border-black/[0.06]">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
              </svg>
            </div>
            <h4 class="text-xs font-bold text-[#1D1D1F]">Sin Fotografías Registradas</h4>
            <p class="text-[11px] text-[#6E6E73] max-w-sm">
              No existen capturas fotográficas en el registro móvil de visitas para este cliente.
            </p>
          </div>

          <!-- Visor de Foto -->
          <template v-else-if="fotoActual">
            <div
              class="relative w-full bg-black/90 rounded-[12px] overflow-hidden flex items-center justify-center min-h-[280px] max-h-[58vh] border border-black/[0.08]"
            >
              <img
                :src="fotoActual.photo_url || fotoActual.url"
                alt="Foto de visita cliente"
                class="max-h-[56vh] max-w-full object-contain transition-transform duration-200 select-none"
                :class="isZoomed ? 'scale-150 cursor-zoom-out' : 'cursor-zoom-in'"
                @click="isZoomed = !isZoomed"
              />

              <button
                v-if="fotos.length > 1"
                type="button"
                @click="anterior"
                class="absolute left-2 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-white/85 hover:bg-white text-[#1D1D1F] flex items-center justify-center shadow-lg font-black transition-all cursor-pointer hover:scale-105 active:scale-95 z-10"
                title="Foto anterior"
              >
                ‹
              </button>
              <button
                v-if="fotos.length > 1"
                type="button"
                @click="siguiente"
                class="absolute right-2 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-white/85 hover:bg-white text-[#1D1D1F] flex items-center justify-center shadow-lg font-black transition-all cursor-pointer hover:scale-105 active:scale-95 z-10"
                title="Siguiente foto"
              >
                ›
              </button>
            </div>

            <!-- Metadata de la Foto -->
            <div class="space-y-1.5">
              <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                <div class="flex flex-wrap items-center gap-2">
                  <div class="flex items-center gap-1.5 apple-control-pill px-2.5 py-1 rounded-[6px]">
                    <span class="text-[10px] font-bold text-[#6E6E73] uppercase">Fecha:</span>
                    <strong class="text-[#1D1D1F] font-mono">{{ fotoActual.fecha ? `${fotoActual.fecha} ${fotoActual.hora || ''}` : (fotoActual.visited_at_fmt || fotoActual.visited_at || '-') }}</strong>
                  </div>
                  <div class="flex items-center gap-1.5 apple-control-pill px-2.5 py-1 rounded-[6px]">
                    <span class="text-[10px] font-bold text-[#6E6E73] uppercase">Ruta:</span>
                    <strong class="text-[#0071E3] font-mono">{{ fotoActual.route || fotoActual.ruta || 'Sin Ruta' }}</strong>
                  </div>
                  <span class="px-2 py-0.5 rounded-[6px] text-[10px] font-bold bg-[#EBF9EF] text-[#1E6B37] border border-[#D1F2D9]">
                    {{ fotoActual.status || 'VISITA' }}
                  </span>
                  <span
                    v-if="fotoActual.is_opportunity"
                    class="px-2 py-0.5 rounded-[6px] text-[10px] font-bold bg-[#FFF5E5] text-[#B25E00] border border-[#FFE0B2]"
                  >
                    OPORTUNIDAD
                  </span>
                </div>
              </div>

              <div
                v-if="fotoActual.comments && String(fotoActual.comments).trim()"
                class="p-2 bg-white/75 rounded-[8px] border border-black/[0.06] text-[11px] text-[#1D1D1F]"
              >
                <span class="font-bold text-[#6E6E73] text-[10px] uppercase block mb-0.5">Observación de Visita:</span>
                <span>{{ fotoActual.comments }}</span>
              </div>
            </div>

            <!-- Carrusel de Miniaturas -->
            <div v-if="fotos.length > 1" class="pt-1 border-t border-black/[0.06]">
              <div class="flex items-center gap-2 overflow-x-auto custom-scroll py-1">
                <button
                  v-for="(f, idx) in fotos"
                  :key="f.id || idx"
                  type="button"
                  @click="indice = idx; isZoomed = false"
                  class="relative shrink-0 w-14 h-14 rounded-[8px] overflow-hidden border-2 transition-all cursor-pointer"
                  :class="indice === idx ? 'border-[#0071E3] scale-105 shadow-md' : 'border-transparent opacity-60 hover:opacity-100'"
                >
                  <img
                    :src="f.photo_url || f.url"
                    alt="Miniatura"
                    class="w-full h-full object-cover"
                  />
                  <span
                    class="absolute bottom-0 right-0 bg-black/70 text-white font-mono text-[8px] font-bold px-1 rounded-tl"
                  >
                    {{ idx + 1 }}
                  </span>
                </button>
              </div>
            </div>
          </template>
        </div>

        <!-- Pie del Lightbox -->
        <div class="px-4 py-2.5 bg-white/80 border-t border-black/[0.06] flex items-center justify-between text-xs">
          <span class="text-[11px] text-[#6E6E73]">
            Usa las flechas <kbd class="px-1 py-0.5 rounded bg-black/[0.06] font-mono text-[10px]">←</kbd> <kbd class="px-1 py-0.5 rounded bg-black/[0.06] font-mono text-[10px]">→</kbd> para navegar y <kbd class="px-1 py-0.5 rounded bg-black/[0.06] font-mono text-[10px]">ESC</kbd> para salir.
          </span>
          <button
            type="button"
            @click="cerrar"
            class="apple-control-pill px-3 py-1 rounded-[8px] font-bold text-xs text-[#1D1D1F] hover:bg-black/[0.06] cursor-pointer"
          >
            Cerrar
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue';

const abierto = ref(false);
const cargando = ref(false);
const errorMsg = ref(null);
const fotos = ref([]);
const indice = ref(0);
const isZoomed = ref(false);

const cliente = reactive({
  id: null,
  nombre: '',
  ruta: '',
});

const fotoActual = computed(() => fotos.value[indice.value] || null);

function anterior() {
  if (fotos.value.length === 0) return;
  indice.value = (indice.value - 1 + fotos.value.length) % fotos.value.length;
  isZoomed.value = false;
}

function siguiente() {
  if (fotos.value.length === 0) return;
  indice.value = (indice.value + 1) % fotos.value.length;
  isZoomed.value = false;
}

function cerrar() {
  abierto.value = false;
  fotos.value = [];
  indice.value = 0;
  isZoomed.value = false;
  errorMsg.value = null;
}

async function abrir(clienteId, nombre = '', ruta = '') {
  cliente.id = clienteId;
  cliente.nombre = nombre;
  cliente.ruta = ruta;
  abierto.value = true;
  cargando.value = true;
  errorMsg.value = null;
  fotos.value = [];
  indice.value = 0;
  isZoomed.value = false;

  try {
    const res = await fetch(route('supervisor.auditoria.fotos-cliente', clienteId));
    if (!res.ok) throw new Error(`HTTP ${res.status}`);
    const data = await res.json();
    fotos.value = Array.isArray(data.fotos) ? data.fotos : [];
  } catch (err) {
    console.error('[AuditoriaFotosModal] Error:', err);
    errorMsg.value = err.message || 'Error al obtener fotos del cliente';
  } finally {
    cargando.value = false;
  }
}

function manejarTeclas(e) {
  if (!abierto.value) return;
  if (e.key === 'Escape') cerrar();
  else if (e.key === 'ArrowLeft') anterior();
  else if (e.key === 'ArrowRight') siguiente();
}

onMounted(() => {
  window.addEventListener('keydown', manejarTeclas);
});

onUnmounted(() => {
  window.removeEventListener('keydown', manejarTeclas);
});

defineExpose({
  abrir,
  cerrar,
});
</script>

