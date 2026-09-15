<template>
  <SupervisorLayout>
    <div class="space-y-4">
      <!-- Filtros Superiores Adaptativos -->
      <div class="bg-white p-3 sm:p-4 rounded-xl shadow-sm border border-gray-100 flex flex-col lg:flex-row lg:items-center justify-between gap-3">
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 sm:gap-3 w-full lg:w-auto">
          <div>
            <label class="text-[10px] sm:text-[11px] font-bold text-gray-500 uppercase block mb-1">Fecha:</label>
            <input
              type="date"
              v-model="selectedDate"
              @change="applyFilters"
              class="w-full border border-gray-300 rounded-lg p-1.5 sm:p-2 text-xs sm:text-sm font-semibold bg-white outline-none focus:ring-2 focus:ring-amber-500"
            />
          </div>

          <div>
            <label class="text-[10px] sm:text-[11px] font-bold text-gray-500 uppercase block mb-1">Ruta:</label>
            <select
              v-model="selectedRoute"
              @change="applyFilters"
              class="w-full border border-gray-300 rounded-lg p-1.5 sm:p-2 text-xs sm:text-sm font-semibold bg-white outline-none focus:ring-2 focus:ring-amber-500"
            >
              <option :value="null">-- Todas las Rutas --</option>
              <option v-for="r in vendedores" :key="r" :value="r">{{ r }}</option>
            </select>
          </div>

          <div>
            <label class="text-[10px] sm:text-[11px] font-bold text-gray-500 uppercase block mb-1">Conciliación:</label>
            <select
              v-model="selectedAuditoria"
              @change="applyFilters"
              class="w-full border border-gray-300 rounded-lg p-1.5 sm:p-2 text-xs sm:text-sm font-semibold bg-white outline-none focus:ring-2 focus:ring-amber-500"
            >
              <option value="TODAS">Todo el Universo</option>
              <option value="DENTRO">En Sitio (≤ 50m)</option>
              <option value="FUERA">Desvíos (> 50m)</option>
              <option value="NO_VISITADO">No Visitados</option>
              <option value="OPORTUNIDAD">Oportunidades</option>
            </select>
          </div>
        </div>

        <!-- Leyenda Compacta -->
        <div class="flex flex-wrap gap-2 text-[10px] sm:text-xs font-medium text-gray-600 bg-slate-50 p-2 rounded-lg border border-slate-200">
          <span class="flex items-center space-x-1">
            <span class="w-2.5 h-2.5 rounded-full bg-[#10b981]"></span>
            <span>≤ 50m</span>
          </span>
          <span class="flex items-center space-x-1">
            <span class="w-2.5 h-2.5 rounded-full bg-[#ef4444]"></span>
            <span>> 50m</span>
          </span>
          <span class="flex items-center space-x-1">
            <span class="w-2.5 h-2.5 rounded-full bg-[#f59e0b]"></span>
            <span>Pendiente</span>
          </span>
          <span class="flex items-center space-x-1">
            <span class="w-2.5 h-2.5 rounded-full bg-[#8b5cf6]"></span>
            <span>Oportunidad</span>
          </span>
        </div>
      </div>

      <!-- Tarjetas Resumen KPIs -->
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2 sm:gap-3">
        <div class="bg-white p-2.5 sm:p-3.5 rounded-xl shadow-sm border border-gray-100">
          <p class="text-[9px] sm:text-[10px] font-bold text-gray-400 uppercase">Plan del Día</p>
          <p class="text-base sm:text-xl font-black text-slate-800">
            {{ metrics.total_visitados_plan }} <span class="text-xs font-semibold text-slate-400">/ {{ metrics.total_plan }}</span>
          </p>
          <span class="text-[10px] sm:text-[11px] font-bold text-blue-600">{{ metrics.cobertura_pct }}% Cobertura</span>
        </div>

        <div class="bg-white p-2.5 sm:p-3.5 rounded-xl shadow-sm border border-gray-100">
          <p class="text-[9px] sm:text-[10px] font-bold text-gray-400 uppercase">En Sitio</p>
          <p class="text-base sm:text-xl font-black text-emerald-600">{{ metrics.en_rango }}</p>
          <span class="text-[10px] sm:text-[11px] font-semibold text-slate-500">≤ 50 metros</span>
        </div>

        <div class="bg-white p-2.5 sm:p-3.5 rounded-xl shadow-sm border border-gray-100">
          <p class="text-[9px] sm:text-[10px] font-bold text-gray-400 uppercase">Desvíos</p>
          <p class="text-base sm:text-xl font-black text-rose-600">{{ metrics.fuera_rango }}</p>
          <span class="text-[10px] sm:text-[11px] font-semibold text-rose-500">> 50 metros</span>
        </div>

        <div class="bg-white p-2.5 sm:p-3.5 rounded-xl shadow-sm border border-gray-100">
          <p class="text-[9px] sm:text-[10px] font-bold text-gray-400 uppercase">Pendientes</p>
          <p class="text-base sm:text-xl font-black text-amber-600">{{ metrics.no_visitados }}</p>
          <span class="text-[10px] sm:text-[11px] font-semibold text-amber-600">Por visitar</span>
        </div>

        <div class="bg-white p-2.5 sm:p-3.5 rounded-xl shadow-sm border border-gray-100 col-span-2 sm:col-span-1">
          <p class="text-[9px] sm:text-[10px] font-bold text-gray-400 uppercase">Oportunidad</p>
          <p class="text-base sm:text-xl font-black text-purple-600">{{ metrics.oportunidades }}</p>
          <span class="text-[10px] sm:text-[11px] font-semibold text-slate-500">Fuera de plan</span>
        </div>
      </div>

      <!-- Mapa y Detalle/Evidencia -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <!-- Mapa de Conciliación -->
        <div class="lg:col-span-2 bg-white p-2 sm:p-3 rounded-xl shadow-sm border border-gray-100 order-2 lg:order-1">
          <div id="map-visitas" class="h-[360px] sm:h-[450px] lg:h-[540px] w-full rounded-lg"></div>
        </div>

        <!-- Panel de Detalle del Registro Seleccionado -->
        <div class="bg-white p-3 sm:p-4 rounded-xl shadow-sm border border-gray-100 flex flex-col justify-between order-1 lg:order-2 min-h-[300px] lg:h-[540px]">
          <div v-if="selectedItem" class="space-y-2.5 overflow-y-auto pr-1">
            <div class="flex justify-between items-start border-b pb-2 border-gray-100">
              <div class="max-w-[70%]">
                <span class="text-[10px] font-mono font-bold text-slate-400">#{{ selectedItem.client_id || 'OPP' }}</span>
                <h3 class="text-xs sm:text-sm font-bold text-slate-900 leading-tight mt-0.5 truncate">{{ selectedItem.client_name }}</h3>
                <p class="text-[10px] sm:text-xs text-slate-500 truncate">Ruta: {{ selectedItem.route }} | {{ selectedItem.vendedor }}</p>
              </div>
              <span
                class="px-2 py-0.5 text-[9px] sm:text-[10px] font-bold rounded text-white shadow-xs"
                :style="{ backgroundColor: getItemColor(selectedItem) }"
              >
                {{ getStatusLabel(selectedItem) }}
              </span>
            </div>

            <!-- Foto -->
            <div>
              <p class="text-[10px] font-bold text-gray-400 uppercase mb-1">Evidencia Fotográfica</p>
              <div
                v-if="selectedItem.photo_url"
                class="relative h-40 sm:h-44 bg-slate-900 rounded-lg overflow-hidden group cursor-pointer border border-gray-200"
                @click="openModal(selectedItem)"
              >
                <img :src="selectedItem.photo_url" class="w-full h-full object-cover group-hover:scale-105 transition duration-300" />
                <div class="absolute inset-0 bg-black/30 flex items-center justify-center opacity-0 group-hover:opacity-100 transition">
                  <span class="text-white text-[11px] font-bold bg-black/60 px-2.5 py-1 rounded">Pantalla completa</span>
                </div>
              </div>
              <div v-else class="h-28 bg-slate-50 rounded-lg flex flex-col items-center justify-center border border-dashed border-gray-200 text-slate-400 text-xs">
                <span>Sin fotografía</span>
                <span class="text-[10px]">{{ selectedItem.visitado ? 'No adjuntada' : 'Pendiente de visita' }}</span>
              </div>
            </div>

            <!-- Datos Técnicos -->
            <div class="bg-slate-50 p-2.5 rounded-lg border border-slate-100 space-y-1 text-[11px]">
              <div class="flex justify-between">
                <span class="text-slate-500">Hora:</span>
                <span class="font-mono font-bold text-slate-800">{{ selectedItem.visited_at ? formatTime(selectedItem.visited_at) : 'Pendiente' }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-500">Distancia Catastro:</span>
                <span class="font-bold" :class="selectedItem.en_rango ? 'text-emerald-600' : (selectedItem.distancia_metros ? 'text-rose-600' : 'text-slate-600')">
                  {{ selectedItem.distancia_metros !== null ? selectedItem.distancia_metros + ' m' : (selectedItem.is_opportunity ? 'N/A' : 'Pendiente') }}
                </span>
              </div>
              <div v-if="selectedItem.comments" class="text-slate-600 italic border-t border-slate-200 pt-1 mt-1 text-[10px]">
                "{{ selectedItem.comments }}"
              </div>
            </div>
          </div>

          <div v-else class="flex flex-col items-center justify-center h-full text-slate-400 text-xs text-center p-4">
            Selecciona un cliente de la tabla o del mapa para ver el detalle de auditoría.
          </div>

          <div v-if="selectedItem" class="pt-2 border-t border-gray-100">
            <button
              @click="focusItem(selectedItem)"
              class="w-full py-1.5 sm:py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-bold transition"
            >
              Centrar en Mapa
            </button>
          </div>
        </div>
      </div>

      <!-- Tabla Maestra de Conciliación -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-3 sm:p-4 border-b border-gray-100 flex justify-between items-center bg-slate-50">
          <div>
            <h3 class="text-xs sm:text-sm font-bold text-slate-800 uppercase">Conciliación de Jornada</h3>
            <p class="text-[10px] sm:text-xs text-slate-400">Orden cronológico / Catastro</p>
          </div>
          <span class="text-[10px] sm:text-xs font-mono font-bold bg-white px-2 py-0.5 rounded border border-gray-200 text-slate-600">
            {{ items.length }} filas
          </span>
        </div>

        <div class="overflow-x-auto max-h-80 sm:max-h-96">
          <table class="w-full text-left border-collapse text-[11px] sm:text-xs min-w-[620px]">
            <thead class="bg-slate-100 text-slate-600 font-bold sticky top-0 z-10 border-b border-slate-200">
              <tr>
                <th class="py-2 px-2.5 text-center w-12">Foto</th>
                <th class="py-2 px-2.5">Hora</th>
                <th class="py-2 px-2.5">ID</th>
                <th class="py-2 px-2.5">Cliente</th>
                <th class="py-2 px-2.5">Ruta</th>
                <th class="py-2 px-2.5">Estado</th>
                <th class="py-2 px-2.5 text-right">Distancia</th>
                <th class="py-2 px-2.5 text-center">Acción</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr
                v-for="item in sortedItems"
                :key="item.id"
                class="hover:bg-amber-50/40 transition cursor-pointer"
                :class="selectedItem?.id === item.id ? 'bg-amber-50/70 font-medium' : ''"
                @click="selectRow(item)"
              >
                <!-- Foto -->
                <td class="py-1.5 px-2.5 text-center">
                  <div
                    v-if="item.photo_url"
                    class="w-8 h-8 rounded overflow-hidden border border-gray-200 inline-block bg-slate-800"
                    @click.stop="openModal(item)"
                  >
                    <img :src="item.photo_url" class="w-full h-full object-cover" />
                  </div>
                  <span v-else class="text-[10px] text-gray-300 font-mono">-</span>
                </td>

                <td class="py-1.5 px-2.5 font-mono font-bold text-slate-700 whitespace-nowrap">
                  {{ item.visited_at ? formatTime(item.visited_at) : 'Pendiente' }}
                </td>

                <td class="py-1.5 px-2.5 font-mono text-slate-500 font-bold whitespace-nowrap">
                  {{ item.client_id ? '#' + item.client_id : 'OPP' }}
                </td>

                <td class="py-1.5 px-2.5 max-w-[180px] sm:max-w-xs">
                  <div class="font-bold text-slate-900 truncate">{{ item.client_name }}</div>
                  <div class="text-[10px] text-slate-400 truncate">{{ item.address || 'Sin dirección' }}</div>
                </td>

                <td class="py-1.5 px-2.5 font-semibold text-slate-700 whitespace-nowrap">
                  {{ item.route }}
                </td>

                <td class="py-1.5 px-2.5 whitespace-nowrap">
                  <span
                    class="px-1.5 py-0.5 rounded text-[9px] sm:text-[10px] font-bold text-white inline-block"
                    :style="{ backgroundColor: getItemColor(item) }"
                  >
                    {{ getStatusLabel(item) }}
                  </span>
                </td>

                <td class="py-1.5 px-2.5 text-right font-mono font-bold whitespace-nowrap">
                  <span v-if="item.distancia_metros !== null" :class="item.en_rango ? 'text-emerald-600' : 'text-rose-600'">
                    {{ item.distancia_metros }} m
                  </span>
                  <span v-else-if="item.is_opportunity" class="text-purple-500 text-[10px]">
                    Oportunidad
                  </span>
                  <span v-else class="text-slate-300 text-[10px]">
                    -
                  </span>
                </td>

                <td class="py-1.5 px-2.5 text-center whitespace-nowrap">
                  <button
                    class="px-2 py-0.5 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-[10px] sm:text-[11px] transition"
                    @click.stop="focusItem(item)"
                  >
                    Ubicar
                  </button>
                </td>
              </tr>
              <tr v-if="items.length === 0">
                <td colspan="8" class="py-8 text-center text-gray-400 text-xs">
                  No hay datos registrados para los filtros seleccionados.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Modal Fotografía Pantalla Completa -->
    <div
      v-if="modalItem"
      class="fixed inset-0 bg-black/85 backdrop-blur-xs flex items-center justify-center z-50 p-2 sm:p-4"
      @click.self="modalItem = null"
    >
      <div class="bg-white rounded-xl overflow-hidden max-w-xl w-full shadow-2xl">
        <div class="p-3 border-b border-gray-100 flex justify-between items-center bg-slate-50">
          <div class="max-w-[85%]">
            <h4 class="font-bold text-xs sm:text-sm text-slate-900 truncate">{{ modalItem.client_name }}</h4>
            <p class="text-[10px] sm:text-xs text-slate-400">Ruta {{ modalItem.route }} | Vendedor: {{ modalItem.vendedor }}</p>
          </div>
          <button @click="modalItem = null" class="text-slate-400 hover:text-slate-800 font-bold text-xl px-2">
            &times;
          </button>
        </div>

        <div class="max-h-[55vh] sm:max-h-[65vh] bg-black flex items-center justify-center">
          <img :src="modalItem.photo_url" class="max-h-[55vh] sm:max-h-[65vh] w-full object-contain" />
        </div>

        <div class="p-3 sm:p-4 bg-white text-xs space-y-2 border-t border-gray-100">
          <div class="grid grid-cols-2 gap-2 text-[11px]">
            <div>
              <p class="text-slate-400 font-semibold uppercase text-[9px]">Hora Oficial</p>
              <p class="font-bold text-slate-800">{{ modalItem.visited_at }}</p>
            </div>
            <div>
              <p class="text-slate-400 font-semibold uppercase text-[9px]">Distancia Geocerca</p>
              <p class="font-bold" :class="modalItem.en_rango ? 'text-emerald-600' : 'text-rose-600'">
                {{ modalItem.distancia_metros !== null ? modalItem.distancia_metros + ' metros' : 'Oportunidad' }}
              </p>
            </div>
          </div>
          <p v-if="modalItem.comments" class="pt-1.5 border-t border-gray-100 text-slate-600 text-[11px]">
            <strong>Comentario:</strong> "{{ modalItem.comments }}"
          </p>
        </div>
      </div>
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue';
import L from 'leaflet';

const props = defineProps({
  vendedores: Array,
  selected_date: String,
  selected_route: String,
  selected_auditoria: String,
  items: Array,
  metrics: Object,
});

const selectedDate = ref(props.selected_date);
const selectedRoute = ref(props.selected_route);
const selectedAuditoria = ref(props.selected_auditoria);
const selectedItem = ref(null);
const modalItem = ref(null);

let map = null;
let mapLayers = null;
const markersMap = new Map();

const sortedItems = computed(() => {
  return [...props.items].sort((a, b) => {
    if (a.visited_at && b.visited_at) {
      return a.visited_at.localeCompare(b.visited_at);
    }
    if (a.visited_at && !b.visited_at) return -1;
    if (!a.visited_at && b.visited_at) return 1;
    return (a.client_id || 0) - (b.client_id || 0);
  });
});

function formatTime(dt) {
  if (!dt) return '--:--';
  const parts = dt.split(' ');
  return parts.length > 1 ? parts[1] : dt;
}

function getItemColor(item) {
  if (item.tipo_auditoria === 'DENTRO') return '#10b981';
  if (item.tipo_auditoria === 'FUERA') return '#ef4444';
  if (item.tipo_auditoria === 'OPORTUNIDAD') return '#8b5cf6';
  return '#f59e0b';
}

function getStatusLabel(item) {
  if (item.tipo_auditoria === 'DENTRO') return 'En Sitio';
  if (item.tipo_auditoria === 'FUERA') return 'Desvío';
  if (item.tipo_auditoria === 'OPORTUNIDAD') return 'Oportunidad';
  return 'Pendiente';
}

function applyFilters() {
  router.get(
    route('supervisor.visitas.index'),
    {
      date: selectedDate.value,
      route: selectedRoute.value,
      auditoria: selectedAuditoria.value,
    },
    {
      preserveState: true,
      preserveScroll: true,
      only: ['items', 'metrics', 'selected_route', 'selected_date', 'selected_auditoria'],
    }
  );
}

function selectRow(item) {
  selectedItem.value = item;
}

function openModal(item) {
  modalItem.value = item;
}

function renderMap() {
  if (!map || !mapLayers) return;

  mapLayers.clearLayers();
  markersMap.clear();

  const bounds = [];

  props.items.forEach((item) => {
    const lat = item.visitado ? item.visita_lat : item.official_lat;
    const lon = item.visitado ? item.visita_lon : item.official_lon;

    if (!lat || !lon) return;

    bounds.push([lat, lon]);

    const markerColor = getItemColor(item);
    const marker = L.circleMarker([lat, lon], {
      radius: item.visitado ? 6 : 4.5,
      fillColor: markerColor,
      color: '#ffffff',
      weight: 1.2,
      fillOpacity: 0.9,
    });

    marker.bindPopup(`
      <div class="font-sans text-xs space-y-1 max-w-[190px]">
        <div class="font-bold text-slate-900">${item.client_name}</div>
        <div class="text-[10px] font-mono text-slate-500">
          ${item.visited_at ? item.visited_at : 'PENDIENTE'} | Ruta: ${item.route}
        </div>
        <div class="pt-0.5">
          <span class="px-1.5 py-0.5 rounded text-[9px] font-bold text-white" style="background-color: ${markerColor}">
            ${getStatusLabel(item)}
          </span>
        </div>
        ${item.distancia_metros !== null ? `<div class="text-[10px] text-slate-700">Dist: <strong>${item.distancia_metros} m</strong></div>` : ''}
      </div>
    `);

    marker.on('click', () => {
      selectedItem.value = item;
    });

    mapLayers.addLayer(marker);
    markersMap.set(item.id, marker);

    if (item.tipo_auditoria === 'FUERA' && item.official_lat && item.official_lon) {
      bounds.push([item.official_lat, item.official_lon]);

      const officialMarker = L.circleMarker([item.official_lat, item.official_lon], {
        radius: 4,
        fillColor: '#64748b',
        color: '#ffffff',
        weight: 1,
        fillOpacity: 0.8,
      }).bindPopup(`
        <div class="font-sans text-xs">
          <strong>Ubicación Oficial Catastro</strong><br/>
          <span>${item.client_name}</span>
        </div>
      `);
      mapLayers.addLayer(officialMarker);

      const dashLine = L.polyline([[item.official_lat, item.official_lon], [item.visita_lat, item.visita_lon]], {
        color: '#ef4444',
        weight: 2,
        dashArray: '4, 5',
        opacity: 0.7,
      });
      mapLayers.addLayer(dashLine);
    }
  });

  if (bounds.length > 0) {
    map.fitBounds(bounds, { padding: [25, 25], maxZoom: 16 });
  }
}

function focusItem(item) {
  selectedItem.value = item;
  const lat = item.visitado ? item.visita_lat : item.official_lat;
  const lon = item.visitado ? item.visita_lon : item.official_lon;

  if (!map || !lat || !lon) return;

  map.setView([lat, lon], 17, { animate: true });
  const marker = markersMap.get(item.id);
  if (marker) marker.openPopup();
}

watch(
  () => props.items,
  () => {
    renderMap();
    if (props.items.length > 0 && !selectedItem.value) {
      selectedItem.value = props.items[0];
    }
  },
  { deep: true }
);

onMounted(() => {
  map = L.map('map-visitas').setView([-16.5000, -68.1500], 13);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© OpenStreetMap contributors',
  }).addTo(map);

  mapLayers = L.featureGroup().addTo(map);
  renderMap();

  if (props.items.length > 0) {
    selectedItem.value = props.items[0];
  }
});
</script>