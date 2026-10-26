<template>
  <div class="space-y-3 font-sans select-none">
    <!-- Contenedor Drag & Drop Apple Glass -->
    <div
      class="apple-glass-card rounded-[18px] border-2 border-dashed p-8 text-center transition-all cursor-pointer relative"
      :class="[
        isDragging
          ? 'border-[#0071E3] bg-[#0071E3]/[0.04] scale-[1.01]'
          : 'border-black/[0.12] hover:border-[#0071E3]/50 bg-white/70',
        cargando ? 'pointer-events-none opacity-80' : ''
      ]"
      @dragover.prevent="isDragging = true"
      @dragleave.prevent="isDragging = false"
      @drop.prevent="onDrop"
      @click="triggerFileInput"
    >
      <input
        ref="fileInputRef"
        type="file"
        accept=".xlsx,.csv,.xls,.xlsm"
        class="hidden"
        @change="onFileChange"
      />

      <div class="space-y-3 pointer-events-none">
        <div
          class="w-12 h-12 mx-auto rounded-full bg-black/[0.04] flex items-center justify-center text-[#6E6E73] transition-transform"
          :class="isDragging ? 'scale-110 text-[#0071E3]' : ''"
        >
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5"/>
          </svg>
        </div>

        <div>
          <p class="text-[13px] font-bold text-[#1D1D1F]">
            {{ archivoSeleccionado ? archivoSeleccionado.name : 'Arrastra tu archivo Excel o CSV aquí' }}
          </p>
          <p class="text-[11px] text-[#86868B] mt-0.5 font-num">
            {{ archivoSeleccionado ? formatoTamano(archivoSeleccionado.size) : 'o haz clic para examinar en tu equipo' }}
          </p>
        </div>

        <span class="inline-block text-[9px] font-mono font-bold px-2 py-0.5 rounded bg-black/[0.05] text-[#6E6E73] border border-black/[0.08]">
          Formatos admitidos: .xlsx, .csv
        </span>
      </div>
    </div>

    <!-- Barra de Carga / Spinner Activo -->
    <div v-if="cargando" class="apple-glass-card rounded-[14px] p-3 flex items-center gap-3 bg-white/90 shadow-sm border border-[#0071E3]/20">
      <div class="w-4 h-4 rounded-full border-2 border-[#0071E3] border-t-transparent animate-spin shrink-0"></div>
      <div class="flex-1">
        <div class="text-[11px] font-bold text-[#1D1D1F]">Procesando e ingiriendo datos...</div>
        <div class="text-[10px] text-[#6E6E73]">Ejecutando validación de esquemas, auditoría y actualización atómica en base de datos.</div>
      </div>
    </div>

    <!-- Botón de Envío / Confirmación -->
    <div v-if="archivoSeleccionado && !cargando" class="flex items-center justify-end gap-2 pt-1">
      <button
        type="button"
        @click.stop="cancelarSeleccion"
        class="px-3 py-1.5 text-[11px] font-semibold rounded-[10px] bg-white text-[#6E6E73] border border-black/[0.12] hover:bg-black/[0.03] transition-all cursor-pointer"
      >
        Cancelar
      </button>
      <button
        type="button"
        @click.stop="subir"
        class="px-4 py-1.5 text-[11px] font-bold rounded-[10px] bg-[#0071E3] text-white hover:bg-[#0077ED] transition-all shadow-[0_2px_8px_rgba(0,113,227,0.35)] cursor-pointer flex items-center gap-1.5"
      >
        <span>Procesar Archivo</span>
        <span>→</span>
      </button>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const props = defineProps({
  cargando: {
    type: Boolean,
    default: false,
  },
})

const emit = defineEmits(['archivo-listo'])

const fileInputRef = ref(null)
const archivoSeleccionado = ref(null)
const isDragging = ref(false)

function triggerFileInput() {
  fileInputRef.value?.click()
}

function onFileChange(e) {
  const files = e.target.files
  if (files && files.length > 0) {
    validarYAsignar(files[0])
  }
}

function onDrop(e) {
  isDragging.value = false
  const files = e.dataTransfer?.files
  if (files && files.length > 0) {
    validarYAsignar(files[0])
  }
}

function validarYAsignar(file) {
  const nombre = file.name.toLowerCase()
  if (!nombre.endsWith('.xlsx') && !nombre.endsWith('.csv') && !nombre.endsWith('.xls') && !nombre.endsWith('.xlsm')) {
    alert('Solo se admiten archivos con extensión .xlsx o .csv')
    return
  }
  archivoSeleccionado.value = file
}

function cancelarSeleccion() {
  archivoSeleccionado.value = null
  if (fileInputRef.value) fileInputRef.value.value = ''
}

function subir() {
  if (!archivoSeleccionado.value) return
  emit('archivo-listo', archivoSeleccionado.value)
}

function formatoTamano(bytes) {
  if (!bytes) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]
}

defineExpose({
  resetear: cancelarSeleccion,
})
</script>

