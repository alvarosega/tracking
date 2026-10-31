<template>
  <div class="px-4 py-3 bg-black/[0.02] border-t border-black/[0.05] flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-[#6E6E73] select-none">
    <!-- INFORMACIÓN DE REGISTROS, SELECTOR POR PÁGINA Y SELECCIÓN -->
    <div class="flex flex-wrap items-center gap-3">
      <div>
        Mostrando
        <span class="font-bold text-[#1D1D1F]">{{ desde.toLocaleString('es-BO') }}</span>
        -
        <span class="font-bold text-[#1D1D1F]">{{ hasta.toLocaleString('es-BO') }}</span>
        de
        <span class="font-bold text-[#1D1D1F]">{{ total.toLocaleString('es-BO') }}</span>
        {{ etiqueta }}
      </div>

      <div class="flex items-center gap-1.5 apple-control-pill rounded-[8px] px-2 py-0.5 text-[11px]">
        <span class="text-[9px] font-bold text-[#86868B] uppercase">Mostrar:</span>
        <select
          :value="perPage"
          @change="onPerPageChange($event.target.value)"
          class="bg-transparent font-semibold text-[#1D1D1F] focus:outline-none cursor-pointer"
        >
          <option v-for="opt in perPageOptions" :key="opt" :value="opt">
            {{ opt }} / pág.
          </option>
        </select>
      </div>

      <div v-if="seleccionadosCount > 0" class="text-[11px] font-semibold text-[#0071E3] flex items-center gap-1">
        <span class="w-1.5 h-1.5 rounded-full bg-[#0071E3] animate-pulse"></span>
        <span>{{ seleccionadosCount }} seleccionado(s)</span>
      </div>
    </div>

    <!-- CONTROLES DE NAVEGACIÓN ENTRE PÁGINAS -->
    <div v-if="totalPaginas > 1" class="flex items-center gap-1">
      <!-- Primera Página -->
      <button
        type="button"
        @click="irAPagina(1)"
        :disabled="page <= 1 || loading"
        class="apple-control-pill px-2 py-1 rounded-[8px] text-[11px] font-bold transition-all"
        :class="page <= 1 || loading ? 'opacity-30 cursor-not-allowed' : 'text-[#1D1D1F] hover:bg-black/[0.06] cursor-pointer'"
        title="Primera página"
      >
        &laquo;
      </button>

      <!-- Página Anterior -->
      <button
        type="button"
        @click="irAPagina(page - 1)"
        :disabled="page <= 1 || loading"
        class="apple-control-pill px-2.5 py-1 rounded-[8px] text-[11px] font-bold transition-all"
        :class="page <= 1 || loading ? 'opacity-30 cursor-not-allowed' : 'text-[#1D1D1F] hover:bg-black/[0.06] cursor-pointer'"
        title="Página anterior"
      >
        &lsaquo; Ant.
      </button>

      <!-- Botones numéricos de página (Pantallas medianas y grandes) -->
      <div class="hidden sm:flex items-center gap-1">
        <template v-for="(p, idx) in paginasVisibles" :key="idx">
          <span v-if="p === '...'" class="px-1 text-[11px] text-[#86868B]">...</span>
          <button
            v-else
            type="button"
            @click="irAPagina(p)"
            :disabled="loading"
            class="min-w-[28px] h-7 px-1.5 rounded-[8px] text-[11px] font-bold transition-all cursor-pointer flex items-center justify-center"
            :class="page === p
              ? 'bg-[#0071E3] text-white shadow-xs'
              : 'apple-control-pill text-[#6E6E73] hover:text-[#1D1D1F] hover:bg-black/[0.05]'"
          >
            {{ p }}
          </button>
        </template>
      </div>

      <!-- Indicador móvil compacto -->
      <span class="sm:hidden text-[11px] font-semibold text-[#1D1D1F] px-1">
        {{ page }} / {{ totalPaginas }}
      </span>

      <!-- Página Siguiente -->
      <button
        type="button"
        @click="irAPagina(page + 1)"
        :disabled="page >= totalPaginas || loading"
        class="apple-control-pill px-2.5 py-1 rounded-[8px] text-[11px] font-bold transition-all"
        :class="page >= totalPaginas || loading ? 'opacity-30 cursor-not-allowed' : 'text-[#1D1D1F] hover:bg-black/[0.06] cursor-pointer'"
        title="Página siguiente"
      >
        Sig. &rsaquo;
      </button>

      <!-- Última Página -->
      <button
        type="button"
        @click="irAPagina(totalPaginas)"
        :disabled="page >= totalPaginas || loading"
        class="apple-control-pill px-2 py-1 rounded-[8px] text-[11px] font-bold transition-all"
        :class="page >= totalPaginas || loading ? 'opacity-30 cursor-not-allowed' : 'text-[#1D1D1F] hover:bg-black/[0.06] cursor-pointer'"
        title="Última página"
      >
        &raquo;
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
  total: { type: Number, default: 0 },
  page: { type: Number, default: 1 },
  perPage: { type: Number, default: 50 },
  perPageOptions: { type: Array, default: () => [25, 50, 100, 200, 500] },
  loading: { type: Boolean, default: false },
  etiqueta: { type: String, default: 'eventos' },
  seleccionadosCount: { type: Number, default: 0 },
});

const emit = defineEmits(['update:page', 'update:perPage', 'change']);

const totalPaginas = computed(() => Math.max(1, Math.ceil(props.total / props.perPage)));

const desde = computed(() => {
  if (props.total === 0) return 0;
  return (props.page - 1) * props.perPage + 1;
});

const hasta = computed(() => {
  return Math.min(props.page * props.perPage, props.total);
});

const paginasVisibles = computed(() => {
  const total = totalPaginas.value;
  const actual = props.page;
  if (total <= 7) {
    return Array.from({ length: total }, (_, i) => i + 1);
  }

  const items = [];
  items.push(1);

  if (actual > 3) {
    items.push('...');
  }

  const start = Math.max(2, actual - 1);
  const end = Math.min(total - 1, actual + 1);

  for (let i = start; i <= end; i++) {
    items.push(i);
  }

  if (actual < total - 2) {
    items.push('...');
  }

  items.push(total);
  return items;
});

function irAPagina(p) {
  if (p < 1 || p > totalPaginas.value || p === props.page) return;
  emit('update:page', p);
  emit('change', { page: p, perPage: props.perPage });
}

function onPerPageChange(val) {
  const newPerPage = parseInt(val, 10) || 50;
  emit('update:perPage', newPerPage);
  emit('update:page', 1);
  emit('change', { page: 1, perPage: newPerPage });
}
</script>

