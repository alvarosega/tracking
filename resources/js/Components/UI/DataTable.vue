<template>
  <div class="bg-white border border-slate-200/90 rounded-xl overflow-hidden shadow-2xs">
    <!-- Header de la Tabla (Búsqueda & Filtros rápidos) -->
    <div
      v-if="$slots.filters || searchable || title"
      class="p-3 sm:px-4 sm:py-3 border-b border-slate-100 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-50/40"
    >
      <div class="flex items-center space-x-2">
        <h3 v-if="title" class="text-xs font-semibold text-slate-900 tracking-tight">
          {{ title }}
        </h3>
        <span v-if="totalCount !== null && totalCount !== undefined" class="text-[11px] font-mono text-slate-500 bg-slate-200/70 px-1.5 py-0.5 rounded-md">
          {{ totalCount }}
        </span>
      </div>

      <div class="flex items-center space-x-2 flex-1 sm:flex-initial justify-end">
        <slot name="filters" />
      </div>
    </div>

    <!-- Contenedor Scrollable con Sticky Header -->
    <div class="overflow-x-auto">
      <table class="w-full text-left border-collapse">
        <thead>
          <tr class="bg-slate-50/80 border-b border-slate-200/80 text-[11px] font-semibold text-slate-500 uppercase tracking-wider font-mono select-none">
            <slot name="header" />
          </tr>
        </thead>

        <!-- Estado: Cargando (Skeleton) -->
        <tbody v-if="loading" class="divide-y divide-slate-100">
          <tr v-for="n in skeletonRows" :key="n" class="animate-pulse">
            <td :colspan="columnsCount" class="py-3 px-4">
              <div class="h-4 bg-slate-100 rounded-md w-full"></div>
            </td>
          </tr>
        </tbody>

        <!-- Estado: Vacío -->
        <tbody v-else-if="empty" class="divide-y divide-slate-100">
          <tr>
            <td :colspan="columnsCount" class="py-10 px-4 text-center">
              <div class="max-w-xs mx-auto space-y-2">
                <div class="w-10 h-10 rounded-full bg-slate-100 flex items-center justify-center mx-auto text-slate-400">
                  <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m6 4.125l2.25 2.25m0 0l2.25 2.25M12 13.875l2.25-2.25M12 13.875l-2.25 2.25M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                  </svg>
                </div>
                <p class="text-xs font-semibold text-slate-800">{{ emptyTitle }}</p>
                <p class="text-[11px] text-slate-500">{{ emptyMessage }}</p>
              </div>
            </td>
          </tr>
        </tbody>

        <!-- Filas de Datos -->
        <tbody v-else class="divide-y divide-slate-100 text-xs">
          <slot />
        </tbody>
      </table>
    </div>

    <!-- Paginación o Footer -->
    <div
      v-if="$slots.pagination"
      class="p-3 border-t border-slate-100 bg-slate-50/40 flex items-center justify-between text-xs"
    >
      <slot name="pagination" />
    </div>
  </div>
</template>

<script setup>
defineProps({
  title: {
    type: String,
    default: null,
  },
  totalCount: {
    type: [Number, String],
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  empty: {
    type: Boolean,
    default: false,
  },
  emptyTitle: {
    type: String,
    default: 'No se encontraron registros',
  },
  emptyMessage: {
    type: String,
    default: 'No hay datos disponibles para los filtros seleccionados.',
  },
  searchable: {
    type: Boolean,
    default: false,
  },
  columnsCount: {
    type: Number,
    default: 8,
  },
  skeletonRows: {
    type: Number,
    default: 5,
  }
});
</script>

