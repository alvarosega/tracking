<template>
  <Teleport to="body">
    <div
      v-if="show"
      class="fixed inset-0 z-[999999] flex flex-col bg-slate-950/95 backdrop-blur-md text-white select-none animate-fadeIn font-sans"
      @keydown.esc="cerrar"
      @keydown.left="girarIzquierda"
      @keydown.right="girarDerecha"
      tabindex="0"
      ref="modalRoot"
    >
      <!-- Cabecera Superior del Visor -->
      <header class="h-14 px-4 sm:px-6 bg-slate-900/80 border-b border-slate-800 flex items-center justify-between shrink-0 z-30">
        <div class="flex items-center space-x-3 min-w-0">
          <div class="w-8 h-8 rounded-lg bg-slate-800 border border-slate-700 flex items-center justify-center text-sky-400 shrink-0">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316zM16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z" />
            </svg>
          </div>
          <div class="min-w-0">
            <h3 class="text-xs sm:text-sm font-bold text-slate-100 truncate">
              {{ title || 'Fotografía de Evidencia' }}
            </h3>
            <p v-if="subtitle" class="text-[11px] font-mono text-slate-400 truncate">
              {{ subtitle }}
            </p>
          </div>
        </div>

        <!-- Botones de Acción Superiores -->
        <div class="flex items-center space-x-2 shrink-0">
          <a
            v-if="photoUrl"
            :href="photoUrl"
            target="_blank"
            rel="noopener noreferrer"
            class="px-2.5 py-1.5 text-xs font-medium text-slate-300 hover:text-white bg-slate-800 hover:bg-slate-700 border border-slate-700 rounded-lg transition-colors flex items-center gap-1.5"
            title="Abrir imagen original en nueva pestaña"
          >
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
            </svg>
            <span class="hidden sm:inline">Original</span>
          </a>

          <button
            type="button"
            @click="cerrar"
            class="w-8 h-8 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-300 hover:text-white flex items-center justify-center transition-colors cursor-pointer"
            title="Cerrar visor (ESC)"
          >
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>
        </div>
      </header>

      <!-- Área Central: Lienzo de la Fotografía -->
      <div
        class="flex-1 min-h-0 relative flex items-center justify-center p-4 sm:p-8 overflow-hidden cursor-grab active:cursor-grabbing"
        @click.self="cerrar"
      >
        <div
          class="relative max-w-full max-h-full transition-transform duration-200 ease-out flex items-center justify-center"
          :style="{
            transform: `rotate(${rotacion}deg) scale(${zoom})`,
          }"
        >
          <img
            :src="photoUrl"
            :alt="title || 'Evidencia'"
            class="max-w-[85vw] max-h-[70vh] object-contain rounded-lg shadow-2xl border border-slate-800/80 pointer-events-none"
            @error="onErrorImagen"
          />
        </div>

        <!-- Mensaje de Error si la imagen falla -->
        <div v-if="errorCarga" class="text-center text-xs text-rose-400 p-6 bg-rose-950/40 border border-rose-800 rounded-xl">
          <p class="font-bold">No se pudo cargar la imagen</p>
          <p class="text-[11px] text-rose-300/80 mt-1">El archivo podría no estar disponible en el almacenamiento.</p>
        </div>
      </div>

      <!-- Barra Inferior de Controles (Rotación Visual & Zoom) -->
      <footer class="h-16 px-4 bg-slate-900/90 border-t border-slate-800 flex items-center justify-between shrink-0 z-30">
        
        <!-- Indicador de Rotación & Zoom -->
        <div class="hidden sm:flex items-center space-x-2 text-[11px] font-mono text-slate-400">
          <span class="px-2 py-0.5 bg-slate-800 rounded border border-slate-700">
            Giro: <strong class="text-white">{{ rotacionNormalizada }}°</strong>
          </span>
          <span class="px-2 py-0.5 bg-slate-800 rounded border border-slate-700">
            Zoom: <strong class="text-white">{{ Math.round(zoom * 100) }}%</strong>
          </span>
        </div>

        <!-- Botonera Central de Rotación & Ajuste -->
        <div class="flex items-center space-x-1.5 sm:space-x-2 mx-auto sm:mx-0">
          
          <!-- Girar Izquierda (-90°) -->
          <button
            type="button"
            @click="girarIzquierda"
            class="px-2.5 sm:px-3 py-1.5 text-xs font-semibold bg-slate-800 hover:bg-slate-700 active:bg-slate-600 border border-slate-700 text-slate-200 rounded-lg transition-all flex items-center gap-1.5 cursor-pointer shadow-xs"
            title="Girar 90° a la izquierda (←)"
          >
            <svg class="w-3.5 h-3.5 text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3" />
            </svg>
            <span>-90°</span>
          </button>

          <!-- Girar Derecha (+90°) -->
          <button
            type="button"
            @click="girarDerecha"
            class="px-2.5 sm:px-3 py-1.5 text-xs font-semibold bg-slate-800 hover:bg-slate-700 active:bg-slate-600 border border-slate-700 text-slate-200 rounded-lg transition-all flex items-center gap-1.5 cursor-pointer shadow-xs"
            title="Girar 90° a la derecha (→)"
          >
            <svg class="w-3.5 h-3.5 text-sky-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 15l6-6m0 0l-6-6m6 6H9a6 6 0 000 12h3" />
            </svg>
            <span>+90°</span>
          </button>

          <!-- Invertir 180° -->
          <button
            type="button"
            @click="girar180"
            class="px-2.5 sm:px-3 py-1.5 text-xs font-semibold bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 rounded-lg transition-all cursor-pointer hidden sm:flex items-center gap-1"
            title="Invertir foto 180°"
          >
            <span>180°</span>
          </button>

          <!-- Restablecer Rotación -->
          <button
            type="button"
            @click="restablecer"
            :disabled="rotacion === 0 && zoom === 1"
            class="px-2.5 sm:px-3 py-1.5 text-xs font-semibold bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-300 disabled:opacity-40 disabled:cursor-not-allowed rounded-lg transition-all cursor-pointer"
            title="Restablecer posición original"
          >
            Restablecer
          </button>

          <span class="w-px h-6 bg-slate-800 mx-1 hidden sm:block"></span>

          <!-- Zoom In / Out -->
          <button
            type="button"
            @click="zoomIn"
            class="w-8 h-8 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-300 flex items-center justify-center cursor-pointer transition-colors"
            title="Acercar (+)"
          >
            <span class="text-base font-bold leading-none">+</span>
          </button>

          <button
            type="button"
            @click="zoomOut"
            class="w-8 h-8 rounded-lg bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-300 flex items-center justify-center cursor-pointer transition-colors"
            title="Alejar (-)"
          >
            <span class="text-base font-bold leading-none">&minus;</span>
          </button>
        </div>

        <!-- Comentarios o Información de Visita -->
        <div v-if="comments" class="hidden md:block max-w-xs text-right truncate">
          <p class="text-[11px] text-slate-400 italic truncate" :title="comments">
            "{{ comments }}"
          </p>
        </div>
      </footer>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue';

const props = defineProps({
  show: { type: Boolean, default: false },
  photoUrl: { type: String, default: '' },
  title: { type: String, default: '' },
  subtitle: { type: String, default: '' },
  comments: { type: String, default: '' },
});

const emit = defineEmits(['close']);

const modalRoot = ref(null);
const rotacion = ref(0);
const zoom = ref(1);
const errorCarga = ref(false);

const rotacionNormalizada = computed(() => {
  const norm = ((rotacion.value % 360) + 360) % 360;
  return norm;
});

watch(() => props.show, (val) => {
  if (val) {
    rotacion.value = 0;
    zoom.value = 1;
    errorCarga.value = false;
    nextTick(() => {
      modalRoot.value?.focus();
    });
  }
});

function girarDerecha() {
  rotacion.value += 90;
}

function girarIzquierda() {
  rotacion.value -= 90;
}

function girar180() {
  rotacion.value += 180;
}

function restablecer() {
  rotacion.value = 0;
  zoom.value = 1;
}

function zoomIn() {
  if (zoom.value < 3) {
    zoom.value = Number((zoom.value + 0.25).toFixed(2));
  }
}

function zoomOut() {
  if (zoom.value > 0.5) {
    zoom.value = Number((zoom.value - 0.25).toFixed(2));
  }
}

function onErrorImagen() {
  errorCarga.value = true;
}

function cerrar() {
  emit('close');
}
</script>

<style scoped>
@keyframes fadeIn {
  from { opacity: 0; transform: scale(0.98); }
  to { opacity: 1; transform: scale(1); }
}
.animate-fadeIn {
  animation: fadeIn 0.15s ease-out forwards;
}
</style>

