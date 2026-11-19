<template>
  <div class="relative inline-block text-left" ref="containerRef">
    <!-- BOTÓN PÍLDORA DEL SELECTOR -->
    <button
      type="button"
      @click="alternarMenu"
      class="flex items-center gap-1.5 apple-control-pill rounded-[8px] px-2.5 py-1 text-[11px] font-semibold transition-all cursor-pointer select-none"
      :class="estaActivoFiltro ? 'bg-[#0071E3]/10 text-[#0071E3] border border-[#0071E3]/30' : 'text-[#1D1D1F] hover:bg-black/[0.04]'"
      :title="tituloTooltip"
    >
      <span class="text-[9px] font-bold text-[#86868B] uppercase tracking-wider">
        {{ label }}:
      </span>
      <span class="truncate max-w-[120px] font-bold">
        {{ textoSeleccion }}
      </span>
      <svg
        class="w-2.5 h-2.5 text-[#86868B] transition-transform duration-200 shrink-0"
        :class="menuAbierto ? 'rotate-180 text-[#0071E3]' : ''"
        fill="none"
        viewBox="0 0 24 24"
        stroke="currentColor"
        stroke-width="2.5"
      >
        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
      </svg>
    </button>

    <!-- POPOVER / MENÚ FLOTANTE MULTISELECCIÓN -->
    <div
      v-if="menuAbierto"
      class="absolute top-full left-0 mt-1.5 z-50 min-w-[210px] max-w-xs bg-white rounded-[12px] p-2 border border-black/10 shadow-xl space-y-2 text-left font-sans animate-fadeIn select-none"
      @click.stop
    >
      <!-- Buscador Rápido (si searchable = true) -->
      <div v-if="searchable" class="relative">
        <input
          type="text"
          v-model="busqueda"
          :placeholder="searchPlaceholder"
          class="w-full bg-[#F5F5F7] text-[#1D1D1F] text-[11px] rounded-[7px] pl-6 pr-2 py-1 border border-transparent focus:border-[#0071E3] focus:bg-white focus:outline-none"
        />
        <svg class="w-3 h-3 text-[#86868B] absolute left-2 top-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 105.196 5.196a7.5 7.5 0 0010.607 10.607z" />
        </svg>
      </div>

      <!-- Barra de Acciones Rápidas (Todos / Limpiar) -->
      <div class="flex items-center justify-between text-[10px] font-bold px-1 pb-1 border-b border-black/[0.05]">
        <button
          type="button"
          @click="seleccionarTodos"
          class="text-[#0071E3] hover:underline cursor-pointer"
        >
          Marcar Todos
        </button>
        <span class="text-[#86868B]">&bull;</span>
        <button
          type="button"
          @click="limpiarTodos"
          class="text-[#6E6E73] hover:text-[#FF3B30] cursor-pointer"
        >
          Limpiar
        </button>
      </div>

      <!-- Lista Scrollable de Opciones con Checkbox -->
      <div class="max-h-56 overflow-y-auto space-y-0.5 custom-scroll pr-1">
        <div v-if="opcionesFiltradas.length === 0" class="text-center text-[11px] text-[#86868B] py-3">
          No hay coincidencias
        </div>

        <label
          v-for="op in opcionesFiltradas"
          :key="op.value"
          class="flex items-center gap-2 px-1.5 py-1 rounded-[6px] hover:bg-[#F5F5F7] cursor-pointer transition-colors text-xs text-[#1D1D1F]"
        >
          <input
            type="checkbox"
            :value="op.value"
            :checked="seleccionadosSet.has(op.value)"
            @change="toggleOpcion(op.value)"
            class="rounded cursor-pointer accent-[#0071E3] w-3.5 h-3.5 shrink-0"
          />

          <!-- Indicador visual de color o dot si tiene -->
          <span
            v-if="op.color"
            class="w-2 h-2 rounded-full shrink-0"
            :style="{ backgroundColor: op.color }"
          ></span>

          <span class="truncate flex-1 font-medium text-[11px]">
            {{ op.label }}
          </span>

          <!-- Badge auxiliar si existe -->
          <span
            v-if="op.badge"
            class="text-[9px] font-mono font-bold px-1 py-0.2 rounded bg-black/[0.04] text-[#6E6E73] shrink-0"
          >
            {{ op.badge }}
          </span>
        </label>
      </div>

      <!-- Pie con conteo y botón cerrar -->
      <div class="flex items-center justify-between pt-1.5 border-t border-black/[0.05] text-[10px] text-[#86868B]">
        <span>
          {{ seleccionados.length }} de {{ opcionesNormalizadas.length }} seleccionados
        </span>
        <button
          type="button"
          @click="menuAbierto = false"
          class="px-2 py-0.5 rounded bg-black/[0.05] hover:bg-black/[0.1] text-[#1D1D1F] font-bold cursor-pointer"
        >
          Listo
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
  label: {
    type: String,
    required: true,
  },
  modelValue: {
    type: Array,
    default: () => [],
  },
  options: {
    type: Array,
    default: () => [],
  },
  allSelectedLabel: {
    type: String,
    default: 'Todos',
  },
  searchable: {
    type: Boolean,
    default: false,
  },
  searchPlaceholder: {
    type: String,
    default: 'Buscar...',
  },
});

const emit = defineEmits(['update:modelValue', 'change']);

const menuAbierto = ref(false);
const busqueda = ref('');
const containerRef = ref(null);

const opcionesNormalizadas = computed(() => {
  return props.options.map((op) => {
    if (typeof op === 'string') {
      return { value: op, label: op };
    }
    return {
      value: op.value ?? op.id,
      label: op.label ?? op.nombre ?? String(op.value ?? op.id),
      color: op.color,
      badge: op.badge,
    };
  });
});

const opcionesFiltradas = computed(() => {
  if (!busqueda.value.trim()) return opcionesNormalizadas.value;
  const term = busqueda.value.toLowerCase().trim();
  return opcionesNormalizadas.value.filter((op) =>
    op.label.toLowerCase().includes(term) || String(op.value).toLowerCase().includes(term)
  );
});

const seleccionados = computed(() => props.modelValue || []);
const seleccionadosSet = computed(() => new Set(seleccionados.value));

// Si no hay nada seleccionado o todos están seleccionados, conceptualmente es "Todos"
const esTodos = computed(() => {
  return (
    seleccionados.value.length === 0 ||
    seleccionados.value.length === opcionesNormalizadas.value.length
  );
});

const estaActivoFiltro = computed(() => {
  return !esTodos.value && seleccionados.value.length > 0;
});

const textoSeleccion = computed(() => {
  if (esTodos.value) {
    return props.allSelectedLabel;
  }
  if (seleccionados.value.length === 1) {
    const val = seleccionados.value[0];
    const op = opcionesNormalizadas.value.find((o) => o.value === val);
    return op ? op.label : String(val);
  }
  return `${props.label} (${seleccionados.value.length})`;
});

const tituloTooltip = computed(() => {
  if (esTodos.value) return `${props.label}: Todos`;
  return `${props.label} seleccionados: ${seleccionados.value.join(', ')}`;
});

function alternarMenu() {
  menuAbierto.value = !menuAbierto.value;
  if (menuAbierto.value) {
    busqueda.value = '';
  }
}

function toggleOpcion(val) {
  let nueva;
  if (seleccionadosSet.value.has(val)) {
    nueva = seleccionados.value.filter((v) => v !== val);
  } else {
    nueva = [...seleccionados.value, val];
  }
  emit('update:modelValue', nueva);
  emit('change', nueva);
}

function seleccionarTodos() {
  const todos = opcionesNormalizadas.value.map((o) => o.value);
  emit('update:modelValue', todos);
  emit('change', todos);
}

function limpiarTodos() {
  emit('update:modelValue', []);
  emit('change', []);
}

function onClickAfuera(event) {
  if (containerRef.value && !containerRef.value.contains(event.target)) {
    menuAbierto.value = false;
  }
}

onMounted(() => {
  document.addEventListener('click', onClickAfuera);
});

onBeforeUnmount(() => {
  document.removeEventListener('click', onClickAfuera);
});
</script>

<style scoped>
@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(-4px) scale(0.98);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

.animate-fadeIn {
  animation: fadeIn 0.15s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

.custom-scroll::-webkit-scrollbar {
  width: 4px;
}
.custom-scroll::-webkit-scrollbar-thumb {
  background: rgba(0, 0, 0, 0.15);
  border-radius: 4px;
}
</style>
