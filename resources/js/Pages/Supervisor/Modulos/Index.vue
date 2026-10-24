<template>
  <SupervisorLayout>
    <div class="space-y-6">
      <!-- Encabezado Principal y Pestañas -->
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
          <h1 class="text-xl font-semibold tracking-tight text-[#1D1D1F]">Control de Módulos por Ruta</h1>
          <p class="text-xs text-[#6E6E73] mt-0.5">Habilita o deshabilita funciones en Android para cada vendedor/ruta comercial y define parámetros operativos.</p>
        </div>

        <!-- Segmented Control Apple Style -->
        <div class="inline-flex p-0.5 bg-[#E5E5EA]/70 rounded-[8px] border border-[#E5E5EA]">
          <button
            @click="activeTab = 'matriz'"
            class="px-3.5 py-1.5 text-xs font-medium rounded-[6px] transition-all flex items-center gap-1.5"
            :class="activeTab === 'matriz' ? 'bg-white text-[#1D1D1F] shadow-xs font-semibold' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
          >
            <span>Matriz por Ruta</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono bg-emerald-100 text-emerald-800 font-bold">
              {{ rutas.length }} rutas
            </span>
          </button>
          <button
            @click="activeTab = 'global'"
            class="px-3.5 py-1.5 text-xs font-medium rounded-[6px] transition-all"
            :class="activeTab === 'global' ? 'bg-white text-[#1D1D1F] shadow-xs font-semibold' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
          >
            Ajustes Globales
          </button>
          <button
            @click="activeTab = 'inventarios'"
            class="px-3.5 py-1.5 text-xs font-medium rounded-[6px] transition-all flex items-center gap-1.5"
            :class="activeTab === 'inventarios' ? 'bg-white text-[#1D1D1F] shadow-xs font-semibold' : 'text-[#6E6E73] hover:text-[#1D1D1F]'"
          >
            <span>Stock PDV</span>
            <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono bg-sky-100 text-sky-800">
              Auditoría
            </span>
          </button>
        </div>
      </div>

      <!-- Toast Feedback -->
      <div
        v-if="toast.show"
        class="fixed bottom-5 right-5 z-50 px-4 py-2.5 rounded-[8px] shadow-sm text-xs font-medium flex items-center gap-2 border transition-all"
        :class="toast.type === 'error'
          ? 'bg-[#FEF2F2] border-[#FEE2E2] text-[#991B1B]'
          : 'bg-[#F0FDF4] border-[#DCFCE7] text-[#166534]'"
      >
        <span class="w-1.5 h-1.5 rounded-full" :class="toast.type === 'error' ? 'bg-[#EF4444]' : 'bg-[#22C55E]'"></span>
        {{ toast.message }}
      </div>

      <!-- ========================================== -->
      <!-- TAB 1: MATRIZ DE MÓDULOS POR RUTA          -->
      <!-- ========================================== -->
      <div v-if="activeTab === 'matriz'" class="space-y-4">
        <!-- Barra de Búsqueda y Filtros Rápidos -->
        <div class="bg-white border border-slate-200/90 rounded-xl p-4 shadow-2xs flex flex-wrap items-center justify-between gap-3">
          <div class="flex items-center gap-3">
            <div class="relative">
              <input
                type="text"
                v-model="searchQuery"
                placeholder="Buscar ruta o vendedor..."
                class="w-64 text-xs rounded-lg border-slate-200 pl-8 pr-3 py-1.5 focus:border-slate-900 focus:ring-slate-900 font-mono"
              />
              <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
              </svg>
            </div>

            <!-- Filtro por Canal -->
            <select
              v-model="filterCanal"
              class="text-xs rounded-lg border-slate-200 py-1.5 px-3 focus:border-slate-900 focus:ring-slate-900 font-mono text-slate-700"
            >
              <option value="">Todos los canales</option>
              <option v-for="c in availableCanales" :key="c" :value="c">{{ c }}</option>
            </select>
          </div>

          <div class="flex items-center gap-2">
            <span class="text-[11px] font-mono text-slate-500">
              Mostrando <strong class="text-slate-900">{{ filteredRutas.length }}</strong> de {{ rutas.length }} rutas
            </span>
          </div>
        </div>

        <!-- Tabla Matriz de Control por Ruta -->
        <div class="bg-white border border-slate-200/90 rounded-xl overflow-hidden shadow-2xs">
          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
              <thead>
                <tr class="bg-slate-50/80 border-b border-slate-200/80 text-[10px] font-mono uppercase text-slate-500">
                  <th class="py-3 px-4 font-semibold sticky left-0 bg-slate-50 z-10 w-56 border-r border-slate-200/60 shadow-xs">
                    Ruta / Vendedor
                  </th>
                  <th
                    v-for="mod in modules"
                    :key="mod.id"
                    class="py-3 px-3 font-semibold text-center whitespace-nowrap min-w-[130px]"
                  >
                    <div>{{ mod.name }}</div>
                    <span class="text-[9px] font-normal text-slate-400 font-mono">{{ mod.code }}</span>
                  </th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100 font-mono">
                <tr v-if="filteredRutas.length === 0">
                  <td :colspan="modules.length + 1" class="py-8 text-center text-slate-400 text-xs">
                    No se encontraron rutas con el filtro ingresado.
                  </td>
                </tr>
                <tr
                  v-for="item in filteredRutas"
                  :key="item.ruta"
                  class="hover:bg-slate-50/60 transition-colors"
                >
                  <!-- Celda Fija: Ruta y Vendedor -->
                  <td class="py-3 px-4 sticky left-0 bg-white z-10 border-r border-slate-200/60 shadow-xs">
                    <div class="flex items-center gap-2">
                      <span class="font-bold text-slate-900 text-xs bg-slate-100 px-2 py-0.5 rounded border border-slate-200">
                        {{ item.ruta }}
                      </span>
                      <span class="text-[10px] text-slate-400 uppercase font-sans">{{ item.canal }}</span>
                    </div>
                    <div class="text-[11px] text-slate-600 truncate max-w-[200px] mt-0.5 font-sans">
                      {{ item.vendedor }}
                    </div>
                  </td>

                  <!-- Columnas de Cada Módulo -->
                  <td
                    v-for="mod in modules"
                    :key="mod.id"
                    class="py-2.5 px-3 text-center align-middle"
                  >
                    <div class="inline-flex flex-col items-center">
                      <!-- Switch por Ruta -->
                      <button
                        type="button"
                        @click="toggleRouteModule(item.ruta, mod.code)"
                        :disabled="isUpdating(item.ruta, mod.code)"
                        class="relative inline-flex h-4.5 w-8 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                        :class="isModuleActiveForRoute(item.ruta, mod) ? 'bg-slate-900' : 'bg-slate-300'"
                        :title="isModuleActiveForRoute(item.ruta, mod) ? 'Habilitado' : 'Deshabilitado'"
                      >
                        <span
                          class="pointer-events-none inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out"
                          :class="isModuleActiveForRoute(item.ruta, mod) ? 'translate-x-3.5' : 'translate-x-0'"
                        />
                      </button>

                      <!-- Badge de Override / Heredado -->
                      <span
                        v-if="hasOverride(item.ruta, mod.code)"
                        class="text-[8px] font-bold text-sky-600 uppercase mt-0.5"
                      >
                        Personalizado
                      </span>
                    </div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ========================================== -->
      <!-- TAB 2: AJUSTES GLOBALES Y POR DEFECTO      -->
      <!-- ========================================== -->
      <div v-if="activeTab === 'global'" class="space-y-6">
        <div class="bg-white border border-slate-200/90 rounded-xl p-4 shadow-2xs flex items-start justify-between gap-4">
          <div class="flex items-start gap-3">
            <div class="p-2 rounded-lg bg-slate-100 text-slate-700 mt-0.5">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-.778.099-1.533.284-2.253" />
              </svg>
            </div>
            <div>
              <h2 class="text-sm font-bold text-slate-900">Comportamiento Global por Defecto</h2>
              <p class="text-xs text-slate-500 mt-0.5 leading-relaxed">
                Cuando una ruta no tenga una regla específica definida en la matriz, heredará automáticamente esta configuración base.
              </p>
            </div>
          </div>
        </div>

        <!-- Grid de Módulos Globales -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
          <div
            v-for="module in localModules"
            :key="module.id"
            class="bg-white border rounded-xl p-4 transition-all shadow-2xs flex flex-col justify-between"
            :class="module.is_active ? 'border-slate-200' : 'border-slate-200/60 bg-slate-50/60 opacity-80'"
          >
            <div>
              <div class="flex items-center justify-between mb-2">
                <span class="text-[10px] font-mono font-bold px-2 py-0.5 rounded"
                  :class="module.is_active ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-500 border border-slate-200'"
                >
                  {{ module.is_active ? 'GLOBALMENTE ACTIVO' : 'GLOBALMENTE DESACTIVADO' }}
                </span>

                <button
                  type="button"
                  @click="toggleGlobalModule(module)"
                  class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none"
                  :class="module.is_active ? 'bg-slate-900' : 'bg-slate-300'"
                >
                  <span
                    class="pointer-events-none inline-block h-4 w-4 transform rounded-full bg-white shadow-sm ring-0 transition duration-200 ease-in-out"
                    :class="module.is_active ? 'translate-x-4' : 'translate-x-0'"
                  />
                </button>
              </div>

              <h3 class="text-sm font-bold text-slate-900 flex items-center gap-1.5">
                {{ module.name }}
                <span class="text-[10px] font-mono text-slate-400 font-normal">({{ module.code }})</span>
              </h3>
              <p class="text-xs text-slate-500 mt-1 leading-relaxed">
                {{ module.description }}
              </p>

              <!-- Configuración específica de inventario cliente -->
              <div
                v-if="module.code === 'inventario_cliente'"
                class="mt-3.5 pt-3 border-t border-slate-100 bg-sky-50/50 -mx-4 -mb-4 p-4 rounded-b-xl"
              >
                <div class="flex items-center justify-between mb-1.5">
                  <label class="text-[11px] font-bold text-slate-800 uppercase tracking-wider font-mono">
                    Días Históricos de Ventas (Global)
                  </label>
                  <span class="text-[11px] font-mono font-semibold text-sky-700">
                    {{ module.config?.dias_historial || 30 }} días
                  </span>
                </div>
                <div class="flex items-center gap-2">
                  <select
                    v-model="tempInventarioDiasGlobal"
                    class="text-xs font-mono rounded-lg border-slate-300 bg-white py-1.5 px-2.5 text-slate-800"
                  >
                    <option :value="15">Últimos 15 días</option>
                    <option :value="30">Últimos 30 días</option>
                    <option :value="45">Últimos 45 días</option>
                    <option :value="60">Últimos 60 días (2 meses)</option>
                    <option :value="90">Últimos 90 días (3 meses)</option>
                    <option :value="120">Últimos 120 días (4 meses)</option>
                  </select>

                  <button
                    type="button"
                    @click="saveGlobalConfig(module)"
                    class="px-3 py-1.5 bg-slate-900 hover:bg-slate-800 text-white text-xs font-semibold rounded-lg transition-colors cursor-pointer"
                  >
                    Guardar
                  </button>
                </div>
              </div>
            </div>

            <div class="mt-3 pt-2 text-[10px] font-mono text-slate-400 flex items-center justify-between">
              <span>Orden: #{{ module.order }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- ========================================== -->
      <!-- TAB 3: AUDITORÍA DE INVENTARIOS EN PDV     -->
      <!-- ========================================== -->
      <div v-else-if="activeTab === 'inventarios'" class="space-y-4">
        <!-- Filtros Rápidos -->
        <div class="bg-white border border-slate-200/90 rounded-xl p-4 shadow-2xs flex flex-wrap items-center justify-between gap-3">
          <div class="flex flex-wrap items-center gap-3">
            <div>
              <label class="block text-[10px] font-mono uppercase text-slate-400 font-semibold mb-1">Filtrar Fecha</label>
              <input
                type="date"
                v-model="filterFecha"
                @change="loadInventarios"
                class="text-xs rounded-lg border-slate-200 py-1.5 px-2.5 focus:border-slate-900 focus:ring-slate-900 font-mono"
              />
            </div>
            <div>
              <label class="block text-[10px] font-mono uppercase text-slate-400 font-semibold mb-1">Filtrar Ruta</label>
              <input
                type="text"
                v-model="filterRuta"
                @input="debounceLoadInventarios"
                placeholder="Ej: TDB 6A"
                class="text-xs rounded-lg border-slate-200 py-1.5 px-2.5 focus:border-slate-900 focus:ring-slate-900 font-mono w-32"
              />
            </div>
            <div>
              <label class="block text-[10px] font-mono uppercase text-slate-400 font-semibold mb-1">ID Cliente</label>
              <input
                type="number"
                v-model="filterClienteId"
                @input="debounceLoadInventarios"
                placeholder="ID numérico"
                class="text-xs rounded-lg border-slate-200 py-1.5 px-2.5 focus:border-slate-900 focus:ring-slate-900 font-mono w-28"
              />
            </div>
          </div>

          <button
            type="button"
            @click="loadInventarios"
            :disabled="loadingInventarios"
            class="px-3 py-1.5 bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 text-xs font-semibold rounded-lg transition-colors flex items-center gap-1.5 cursor-pointer shadow-2xs"
          >
            <svg class="w-3.5 h-3.5" :class="loadingInventarios ? 'animate-spin' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
            </svg>
            Refrescar
          </button>
        </div>

        <!-- Tabla de Censos Realizados -->
        <div class="bg-white border border-slate-200/90 rounded-xl overflow-hidden shadow-2xs">
          <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
              <thead>
                <tr class="bg-slate-50/80 border-b border-slate-200/80 text-[10px] font-mono uppercase text-slate-500">
                  <th class="py-2.5 px-4 font-semibold">Fecha / Hora</th>
                  <th class="py-2.5 px-4 font-semibold">Ruta / Vendedor</th>
                  <th class="py-2.5 px-4 font-semibold">Cliente</th>
                  <th class="py-2.5 px-4 font-semibold text-center">Ítems Censados</th>
                  <th class="py-2.5 px-4 font-semibold">Ubicación GPS</th>
                  <th class="py-2.5 px-4 font-semibold text-right">Detalle</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-if="loadingInventarios">
                  <td colspan="6" class="py-8 text-center text-slate-400 font-mono text-xs">
                    Cargando registros de inventario en campo...
                  </td>
                </tr>
                <tr v-else-if="inventariosList.length === 0">
                  <td colspan="6" class="py-8 text-center text-slate-400 font-mono text-xs">
                    No se encontraron registros de inventario para los filtros seleccionados.
                  </td>
                </tr>
                <tr
                  v-for="inv in inventariosList"
                  :key="inv.id"
                  class="hover:bg-slate-50/60 transition-colors"
                >
                  <td class="py-3 px-4 font-mono text-slate-700 whitespace-nowrap">
                    {{ formatDateTime(inv.fecha_registro) }}
                  </td>
                  <td class="py-3 px-4 whitespace-nowrap">
                    <span class="font-mono font-bold text-slate-900 bg-slate-100 px-1.5 py-0.5 rounded text-[11px] border border-slate-200">
                      {{ inv.ruta || 'S/R' }}
                    </span>
                    <span class="text-slate-500 text-[11px] ml-1.5">{{ inv.vendedor }}</span>
                  </td>
                  <td class="py-3 px-4">
                    <div class="font-semibold text-slate-900">{{ inv.cliente_nombre || 'Cliente #' + inv.cliente_id }}</div>
                    <div class="text-[10px] font-mono text-slate-400">ID: {{ inv.cliente_id }} {{ inv.codigo_cliente ? '| ' + inv.codigo_cliente : '' }}</div>
                  </td>
                  <td class="py-3 px-4 text-center">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold bg-sky-50 text-sky-700 border border-sky-200 font-mono">
                      {{ inv.total_items_censados }} productos
                    </span>
                  </td>
                  <td class="py-3 px-4 text-[11px] font-mono text-slate-500 whitespace-nowrap">
                    <div v-if="inv.latitude && inv.longitude">
                      {{ Number(inv.latitude).toFixed(5) }}, {{ Number(inv.longitude).toFixed(5) }}
                      <span v-if="inv.is_mock_location" class="text-rose-500 font-bold ml-1">(MOCK GPS)</span>
                    </div>
                    <div v-else class="text-slate-300">Sin GPS</div>
                  </td>
                  <td class="py-3 px-4 text-right whitespace-nowrap">
                    <button
                      type="button"
                      @click="selectedInventario = inv"
                      class="px-2.5 py-1 text-[11px] font-semibold text-sky-600 hover:text-sky-800 hover:bg-sky-50 rounded-lg transition-colors cursor-pointer"
                    >
                      Ver productos &rarr;
                    </button>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Modal de Detalle de Productos Censados -->
      <div
        v-if="selectedInventario"
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/40 backdrop-blur-xs"
      >
        <div class="bg-white rounded-2xl max-w-2xl w-full max-h-[85vh] flex flex-col shadow-xl border border-slate-200 overflow-hidden">
          <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
            <div>
              <h3 class="text-sm font-bold text-slate-900">
                Detalle de Stock PDV - {{ selectedInventario.cliente_nombre }}
              </h3>
              <p class="text-xs font-mono text-slate-500 mt-0.5">
                Ruta: {{ selectedInventario.ruta }} | Fecha: {{ formatDateTime(selectedInventario.fecha_registro) }}
              </p>
            </div>
            <button
              type="button"
              @click="selectedInventario = null"
              class="p-1 rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors cursor-pointer"
            >
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <div class="p-5 overflow-y-auto flex-1 space-y-4">
            <div v-if="selectedInventario.observaciones" class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs text-amber-800">
              <span class="font-bold">Observación del vendedor:</span> {{ selectedInventario.observaciones }}
            </div>

            <table class="w-full text-left text-xs border-collapse">
              <thead>
                <tr class="bg-slate-50 border-b border-slate-200 text-[10px] font-mono uppercase text-slate-500">
                  <th class="py-2 px-3 font-semibold">Producto</th>
                  <th class="py-2 px-3 font-semibold text-center">Stock Físico (Contado)</th>
                  <th class="py-2 px-3 font-semibold text-center">Histórico Comprado</th>
                  <th class="py-2 px-3 font-semibold text-right">Última Compra</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-slate-100">
                <tr v-for="item in selectedInventario.items" :key="item.id">
                  <td class="py-2.5 px-3">
                    <div class="font-semibold text-slate-800">{{ item.producto_nombre }}</div>
                    <div class="text-[10px] font-mono text-slate-400">{{ item.codigo_producto }} {{ item.categoria ? '• ' + item.categoria : '' }}</div>
                  </td>
                  <td class="py-2.5 px-3 text-center">
                    <span class="font-mono font-bold text-sm" :class="item.stock_fisico === 0 ? 'text-rose-600' : 'text-slate-900'">
                      {{ item.stock_fisico }}
                    </span>
                    <span v-if="item.stock_fisico === 0" class="block text-[9px] font-bold text-rose-500 uppercase">Agotado</span>
                  </td>
                  <td class="py-2.5 px-3 text-center font-mono text-slate-600">
                    {{ item.cantidad_historica || '-' }}
                  </td>
                  <td class="py-2.5 px-3 text-right font-mono text-[11px] text-slate-500">
                    <div v-if="item.ultima_compra_fecha">
                      {{ item.ultima_compra_fecha }}
                      <span class="text-slate-400 block text-[10px]">Cant: {{ item.ultima_compra_cantidad }}</span>
                    </div>
                    <div v-else class="text-slate-300">-</div>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <div class="px-5 py-3 border-t border-slate-100 bg-slate-50 flex justify-end">
            <button
              type="button"
              @click="selectedInventario = null"
              class="px-4 py-1.5 bg-slate-900 text-white rounded-lg text-xs font-semibold hover:bg-slate-800 transition-colors cursor-pointer"
            >
              Cerrar
            </button>
          </div>
        </div>
      </div>
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue';

const props = defineProps({
  modules: {
    type: Array,
    default: () => [],
  },
  rutas: {
    type: Array,
    default: () => [],
  },
  routeOverrides: {
    type: Array,
    default: () => [],
  },
});

const activeTab = ref('matriz');
const localModules = ref([...props.modules]);
const searchQuery = ref('');
const filterCanal = ref('');
const updatingKeys = ref(new Set());

// Mapeo dinámico de overrides: "RUTA_MODULECODE" -> boolean
const overridesMap = ref(new Map());

props.routeOverrides.forEach((ro) => {
  const key = `${ro.ruta.trim()}_${ro.module_code.trim()}`;
  overridesMap.value.set(key, ro);
});

// Canales únicos
const availableCanales = computed(() => {
  const set = new Set(props.rutas.map(r => r.canal).filter(Boolean));
  return Array.from(set).sort();
});

// Rutas filtradas
const filteredRutas = computed(() => {
  return props.rutas.filter((r) => {
    const matchesSearch = !searchQuery.value
      || r.ruta.toLowerCase().includes(searchQuery.value.toLowerCase())
      || (r.vendedor && r.vendedor.toLowerCase().includes(searchQuery.value.toLowerCase()));

    const matchesCanal = !filterCanal.value || r.canal === filterCanal.value;

    return matchesSearch && matchesCanal;
  });
});

// Verifica si un módulo está activo para una ruta dada
const isModuleActiveForRoute = (ruta, mod) => {
  const key = `${ruta.trim()}_${mod.code.trim()}`;
  if (overridesMap.value.has(key)) {
    return Boolean(overridesMap.value.get(key).is_active);
  }
  return Boolean(mod.is_active);
};

const hasOverride = (ruta, moduleCode) => {
  const key = `${ruta.trim()}_${moduleCode.trim()}`;
  return overridesMap.value.has(key);
};

const isUpdating = (ruta, moduleCode) => {
  const key = `${ruta.trim()}_${moduleCode.trim()}`;
  return updatingKeys.value.has(key);
};

// Toggle por ruta
const toggleRouteModule = async (ruta, moduleCode) => {
  const key = `${ruta.trim()}_${moduleCode.trim()}`;
  const mod = props.modules.find(m => m.code === moduleCode);
  const currentActive = isModuleActiveForRoute(ruta, mod);
  const nextActive = !currentActive;

  updatingKeys.value.add(key);

  try {
    const response = await axios.post('/supervisor/modulos/route/toggle', {
      ruta: ruta.trim(),
      module_code: moduleCode,
      is_active: nextActive,
    });

    if (response.data.success) {
      overridesMap.value.set(key, response.data.override);
      showToast(`Ruta ${ruta}: Módulo ${moduleCode} ${nextActive ? 'activado' : 'desactivado'}`);
    }
  } catch (err) {
    showToast('Error al modificar el módulo para esta ruta.', 'error');
  } finally {
    updatingKeys.value.delete(key);
  }
};

// Global toggles
const toggleGlobalModule = async (module) => {
  try {
    const response = await axios.post(`/supervisor/modulos/global/${module.id}/toggle`);
    if (response.data.success) {
      module.is_active = response.data.module.is_active;
      showToast(response.data.message);
    }
  } catch (err) {
    showToast('Error al cambiar configuración global.', 'error');
  }
};

// Inventario global días
const tempInventarioDiasGlobal = ref(
  props.modules.find(m => m.code === 'inventario_cliente')?.config?.dias_historial || 30
);

const saveGlobalConfig = async (module) => {
  try {
    const response = await axios.post(`/supervisor/modulos/global/${module.id}/config`, {
      config: {
        dias_historial: parseInt(tempInventarioDiasGlobal.value, 10),
      },
    });
    if (response.data.success) {
      module.config = response.data.module.config;
      showToast(`Días de historial global actualizados a ${tempInventarioDiasGlobal.value} días.`);
    }
  } catch (err) {
    showToast('Error al guardar configuración global.', 'error');
  }
};

// Toast
const toast = ref({ show: false, message: '', type: 'success' });
const showToast = (message, type = 'success') => {
  toast.value = { show: true, message, type };
  setTimeout(() => {
    toast.value.show = false;
  }, 3000);
};

// Auditoría de inventarios
const inventariosList = ref([]);
const loadingInventarios = ref(false);
const filterFecha = ref('');
const filterRuta = ref('');
const filterClienteId = ref('');
const selectedInventario = ref(null);
let debounceTimer = null;

const loadInventarios = async () => {
  loadingInventarios.value = true;
  try {
    const response = await axios.post('/supervisor/modulos/inventarios/data', {
      fecha: filterFecha.value,
      ruta: filterRuta.value,
      cliente_id: filterClienteId.value,
    });
    if (response.data.success) {
      inventariosList.value = response.data.data.data || [];
    }
  } catch (err) {
    showToast('Error al cargar inventarios.', 'error');
  } finally {
    loadingInventarios.value = false;
  }
};

const debounceLoadInventarios = () => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    loadInventarios();
  }, 400);
};

const formatDateTime = (dateStr) => {
  if (!dateStr) return '-';
  const d = new Date(dateStr);
  return d.toLocaleString('es-BO', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};
</script>
