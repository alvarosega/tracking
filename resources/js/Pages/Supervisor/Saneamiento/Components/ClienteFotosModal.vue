<template>
  <Teleport to="body">
    <div
      v-if="visible"
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
                ID {{ clienteId }}
              </span>
              <h3 class="text-sm font-bold truncate text-white">{{ clienteNombre || 'Cliente' }}</h3>
            </div>
            <p v-if="ruta" class="text-[11px] text-slate-400 font-mono mt-0.5 truncate">
              Ruta: {{ ruta }}
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
            <p class="text-xs font-mono">Cargando fotografías de visitas...</p>
          </div>

          <!-- Sin Fotos -->
          <div v-else-if="fotos.length === 0" class="flex flex-col items-center justify-center py-12 text-slate-400 text-center">
            <div class="w-12 h-12 rounded-full bg-slate-100 flex items-center justify-center text-slate-400 mb-2">
              <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316zM16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0z" />
              </svg>
            </div>
            <p class="text-xs font-semibold text-slate-700">No hay fotografías registradas</p>
            <p class="text-[11px] text-slate-400 mt-0.5">El preventista no capturó evidencia fotográfica en las visitas de este cliente.</p>
          </div>

          <!-- Galería Fotográfica -->
          <div v-else class="space-y-4">
            <div
              v-for="foto in fotos"
              :key="foto.id"
              class="border border-slate-200/90 rounded-xl overflow-hidden bg-slate-50/50 shadow-2xs"
            >
              <div class="p-2 sm:p-3 flex items-center justify-between bg-white border-b border-slate-100 text-xs font-mono">
                <span class="font-bold text-slate-700">{{ foto.visited_at || foto.fecha || 'Fecha N/D' }}</span>
                <span class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 font-bold border border-emerald-200 text-[10px]">
                  {{ foto.status || 'COMPLETADO' }}
                </span>
              </div>
              <div class="relative bg-slate-950 flex items-center justify-center min-h-[200px] max-h-[420px] overflow-hidden">
                <img
                  :src="foto.photo_url || foto.url"
                  :alt="clienteNombre || 'Foto visita'"
                  class="w-full h-auto max-h-[420px] object-contain hover:scale-[1.02] transition-transform duration-200"
                  loading="lazy"
                />
              </div>
              <div v-if="foto.comments && foto.comments !== 'Sin comentarios'" class="p-2.5 bg-white text-xs text-slate-600 border-t border-slate-100">
                <span class="font-semibold text-slate-900">Comentario:</span> {{ foto.comments }}
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="px-5 py-3 bg-slate-50 border-t border-slate-200 flex justify-end shrink-0">
          <button
            type="button"
            @click="$emit('close')"
            class="px-4 py-1.5 rounded-lg bg-slate-200 hover:bg-slate-300 text-slate-800 text-xs font-semibold transition-colors cursor-pointer"
          >
            Cerrar
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, watch } from 'vue'
import axios from 'axios'

const props = defineProps({
  visible: {
    type: Boolean,
    default: false,
  },
  clienteId: {
    type: [Number, String],
    default: null,
  },
  clienteNombre: {
    type: String,
    default: '',
  },
  ruta: {
    type: String,
    default: '',
  },
})

defineEmits(['close'])

const cargando = ref(false)
const fotos = ref([])

watch(
  () => [props.visible, props.clienteId],
  async ([isVisible, cId]) => {
    if (isVisible && cId) {
      cargando.value = true
      fotos.value = []
      try {
        const { data } = await axios.get(route('supervisor.saneamiento.clientes.fotos'), {
          params: { cliente_id: cId }
        })
        fotos.value = data?.fotos || []
      } catch (err) {
        console.error('Error al cargar fotos del cliente:', err)
        fotos.value = []
      } finally {
        cargando.value = false
      }
    }
  },
  { immediate: true }
)
</script>

