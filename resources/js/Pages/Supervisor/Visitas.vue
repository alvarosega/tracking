<template>
  <SupervisorLayout>
    <div class="space-y-4">
      <!-- Filtros Superiores -->
      <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex flex-wrap gap-4 items-center justify-between">
        <div class="flex flex-wrap items-center gap-3">
          <div>
            <label class="text-[11px] font-bold text-gray-500 uppercase block mb-1">Fecha de Visita:</label>
            <input
              type="date"
              v-model="selectedDate"
              @change="applyFilters"
              class="border border-gray-300 rounded-lg p-2 text-sm font-semibold bg-white outline-none focus:ring-2 focus:ring-amber-500"
            />
          </div>

          <div>
            <label class="text-[11px] font-bold text-gray-500 uppercase block mb-1">Ruta / Vendedor:</label>
            <select
              v-model="selectedRoute"
              @change="applyFilters"
              class="border border-gray-300 rounded-lg p-2 text-sm font-semibold bg-white min-w-[190px] outline-none focus:ring-2 focus:ring-amber-500"
            >
              <option :value="null">-- Todas las Rutas --</option>
              <option v-for="r in vendedores" :key="r" :value="r">{{ r }}</option>
            </select>
          </div>

          <div>
            <label class="text-[11px] font-bold text-gray-500 uppercase block mb-1">Filtro de Conciliación:</label>
            <select
              v-model="selectedAuditoria"
              @change="applyFilters"
              class="border border-gray-300 rounded-lg p-2 text-sm font-semibold bg-white outline-none focus:ring-2 focus:ring-amber-500"
            >
              <option value="TODAS">Todo el Universo (Plan + Visitas)</option>
              <option value="DENTRO">Visitados En Sitio (≤ 50m)</option>
              <option value="FUERA">Visitados Fuera de Rango (> 50m)</option>
              <option value="NO_VISITADO">Clientes No Visitados (Pendientes)</option>
              <option value="OPORTUNIDAD">Clientes Nuevos / Oportunidad</option>
            </select>
          </div>
        </div>

        <!-- Leyenda cromática -->
        <div class="flex flex-wrap gap-3 text-xs font-medium text-gray-600 bg-slate-50 p-2 rounded-lg border border-slate-200">
          <span class="flex items-center space-x-1">
            <span class="w-3 h-3 rounded-full bg-[#10b981]"></span>
            <span>En Sitio (≤50m)</span>
          </span>
          <span class="flex items-center space-x-1">
            <span class="w-3 h-3 rounded-full bg-[#ef4444]"></span>
            <span>Desvío (>50m)</span>
          </span>
          <span class="flex items-center space-x-1">
            <span class="w-3 h-3 rounded-full bg-[#f59e0b]"></span>
            <span>No Visitado</span>
          </span>
          <span class="flex items-center space-x-1">
            <span class="w-3 h-3 rounded-full bg-[#8b5cf6]"></span>
            <span>Oportunidad</span>
          </span>
        </div>
      </div>

      <!-- Tarjetas Resumen de Conciliación -->
      <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
        <div class="bg-white p-3 rounded-xl shadow-sm border border-gray-100">
          <p class="text-[10px] font-bold text-gray-400 uppercase">Plan del Día</p>
          <p class="text-xl font-black text-slate-800">
            {{ metrics.total_visitados_plan }} <span class="text-xs font-semibold text-slate-400">/ {{ metrics.total_plan }}</span>
          </p>
          <span class="text-[11px] font-bold text-blue-600">{{ metrics.cobertura_pct }}% Cobertura</span>
        </div>

        <div class="bg-white p-3 rounded-xl shadow-sm border border-gray-100">
          <p class="text-[10px] font-bold text-gray-400 uppercase">Visitados En Sitio</p>
          <p class="text-xl font-black text-emerald-600">{{ metrics.en_rango }}</p>
          <span class="text-[11px] font-semibold text-slate-500">Dentro de 50m</span>
        </div>

        <div class="bg-white p-3 rounded-xl shadow-sm border border-gray-100">
          <p class="text-[10px] font-bold text-gray-400 uppercase">Desvíos</p>
          <p class="text-xl font-black text-rose-600">{{ metrics.fuera_rango }}</p>
          <span class="text-[11px] font-semibold text-rose-500">> 50m del catastro</span>
        </div>

        <div class="bg-white p-3 rounded-xl shadow-sm border border-gray-100">
          <p class="text-[10px] font-bold text-gray-400 uppercase">No Visitados</p>
          <p class="text-xl font-black text-amber-600">{{ metrics.no_visitados }}</p>
          <span class="text-[11px] font-semibold text-amber-600">Pendientes</span>
        </div>

        <div class="bg-white p-3 rounded-xl shadow-sm border border-gray-100">
          <p class="text-[10px] font-bold text-gray-400 uppercase">Oportunidades</p>
          <p class="text-xl font-black text-purple-600">{{ metrics.oportunidades }}</p>
          <span class="text-[11px] font-semibold text-slate-500">Fuera de plan</span>
        </div>
      </div>

      <!-- Sección Principal: Mapa y Panel de Detalle / Fotografía -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <!-- Mapa de Conciliación -->
        <div class="lg:col-span-2 bg-white p-3 rounded-xl shadow-sm border border-gray-100">
          <div id="map-visitas" class="h-[520px] w-full rounded-lg"></div>
        </div>

        <!-- Panel de Detalle del Registro Seleccionado -->
        <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex flex-col justify-between h-[540px]">
          <div v-if="selectedItem" class="space-y-3 overflow-y-auto pr-1">
            <div class="flex justify-between items-start border-b pb-2 border-gray-100">
              <div>
                <span class="text-xs font-mono font-bold text-slate-400">#{{ selectedItem.client_id || 'OPP' }}</span>
                <h3 class="text-sm font-bold text-slate-900 leading-tight mt-0.5">{{ selectedItem.client_name }}</h3>
                <p class="text-xs text-slate-500 mt-0.5">Ruta: {{ selectedItem.route }} | Vendedor: {{ selectedItem.vendedor }}</p>
              </div>
              <span
                class="px-2 py-0.5 text-[10px] font-bold rounded text-white"
                :style="{ backgroundColor: getItemColor(selectedItem) }"
              >
                {{ getStatusLabel(selectedItem) }}
              </span>
            </div>

            <!-- Evidencia Fotográfica -->
            <div class="space-y-1">
              <p class="text-[11px] font-bold text-gray-400 uppercase">Evidencia Fotográfica del Día</p>
              <div
                v-if="selectedItem.photo_url"
                class="relative h-48 bg-slate-900 rounded-lg overflow-hidden group cursor-pointer border border-gray-200"
                @click="openModal(selectedItem)"
              >
                <img :src="selectedItem.photo_url" class="w-full h-full object-cover group-hover:scale-105 transition duration-300" />
                <div class="absolute inset-0 bg-black/30 flex items-center justify-center opacity-0 group-hover:opacity-100 transition">
                  <span class="text-white text-xs font-bold bg-black/60 px-3 py-1.5 rounded-lg">Click para pantalla completa</span>
                </div>
              </div>
              <div v-else class="h-32 bg-slate-50 rounded-lg flex flex-col items-center justify-center border border-dashed border-gray-200 text-slate-400 text-xs">
                <span>Sin fotografía registrada</span>
                <span class="text-[10px] mt-0.5">{{ selectedItem.visitado ? 'No se cargó imagen' : 'Cliente aún no visitado' }}</span>
              </div>
            </div>

            <!-- Datos Técnicos de Geocerca -->
            <div class="bg-slate-50 p-3 rounded-lg border border-slate-100 space-y-1.5 text-xs">
              <div class="flex justify-between">
                <span class="text-slate-500 font-medium">Hora de Visita:</span>
                <span class="font-mono font-bold text-slate-800">{{ selectedItem.visited_at ? formatTime(selectedItem.visited_at) : 'Pendiente' }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-slate-500 font-medium">Distancia a Catastro:</span>
                <span class="font-bold" :class="selectedItem.en_rango ? 'text-emerald-600' : (selectedItem.distancia_metros ? 'text-rose-600' : 'text-slate-600')">
                  {{ selectedItem.distancia_metros !== null ? selectedItem.distancia_metros + ' metros' : (selectedItem.is_opportunity ? 'N/A (Oportunidad)' : 'Pendiente') }}
                </span>
              </div>
              <div v-if="selectedItem.accuracy" class="flex justify-between">
                <span class="text-slate-500 font-medium">Precisión GPS Foto:</span>
                <span class="font-mono text-slate-700">±{{ Math.round(selectedItem.accuracy) }} m</span>
              </div>
              <div v-if="selectedItem.comments" class="pt-1 text-[11px] text-slate-600 italic border-t border-slate-200 mt-1">
                "{{ selectedItem.comments }}"
              </div>
            </div>
          </div>

          <!-- Estado Vacío -->
          <div v-else class="flex flex-col items-center justify-center h-full text-slate-400 text-xs text-center p-6">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-slate-300 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
            </svg>
            Selecciona un cliente de la tabla o un marcador del mapa para auditar su fotografía y geolocalización.
          </div>

          <div v-if="selectedItem" class="pt-3 border-t border-gray-100 flex justify-end">
            <button
              @click="focusItem(selectedItem)"
              class="w-full py-2 bg-slate-900 hover:bg-slate-800 text-white rounded-lg text-xs font-bold transition"
            >
              Centrar en el Mapa
            </button>
          </div>
        </div>
      </div>

      <!-- Tabla Maestra de Conciliación y Auditoría -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-4 border-b border-gray-100 flex justify-between items-center bg-slate-50">
          <div>
            <h3 class="text-sm font-bold text-slate-800 uppercase">Tabla de Conciliación de Jornada</h3>
            <p class="text-xs text-slate-400 mt-0.5">Cruce ordenado cronológicamente entre el Plan de Ruteo y Visitas registradas</p>
          </div>
          <span class="text-xs font-mono font-bold bg-white px-2.5 py-1 rounded border border-gray-200 text-slate-600">
            {{ items.length }} registros
          </span>
        </div>

        <div class="max-h-96 overflow-y-auto">
          <table class="w-full text-left border-collapse text-xs">
            <thead class="bg-slate-100 text-slate-600 font-bold sticky top-0 z-10 border-b border-slate-200">
              <tr>
                <th class="py-2.5 px-3 text-center">Foto</th>
                <th class="py-2.5 px-3">Hora</th>
                <th class="py-2.5 px-3">ID</th>
                <th class="py-2.5 px-3">Cliente</th>
                <th class="py-2.5 px-3">Ruta</th>
                <th class="py-2.5 px-3">Estado Auditoría</th>
                <th class="py-2.5 px-3 text-right">Distancia a Catastro</th>
                <th class="py-2.5 px-3 text-center">Acción</th>
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
                <!-- Miniatura de Foto -->
                <td class="py-1.5 px-3 text-center">
                  <div
                    v-if="item.photo_url"
                    class="w-10 h-10 rounded-lg overflow-hidden border border-gray-200 inline-block bg-slate-800"
                    @click.stop="openModal(item)"
                  >
                    <img :src="item.photo_url" class="w-full h-full object-cover hover:opacity-80 transition" />
                  </div>
                  <span v-else class="text-[10px] text-gray-300 font-mono">-</span>
                </td>

                <!-- Hora de Visita -->
                <td class="py-2 px-3 font-mono font-bold text-slate-700 whitespace-nowrap">
                  {{ item.visited_at ? formatTime(item.visited_at) : 'Pendiente' }}
                </td>

                <!-- ID Cliente -->
                <td class="py-2 px-3 font-mono text-slate-500 font-bold">
                  {{ item.client_id ? '#' + item.client_id : 'OPP' }}
                </td>

                <!-- Nombre Cliente y Dirección -->
                <td class="py-2 px-3">
                  <div class="font-bold text-slate-900 leading-snug">{{ item.client_name }}</div>
                  <div class="text-[11px] text-slate-400 truncate max-w-xs">{{ item.address || 'Sin dirección' }}</div>
                </td>

                <!-- Ruta -->
                <td class="py-2 px-3 font-semibold text-slate-700 whitespace-nowrap">
                  {{ item.route }}
                </td>

                <!-- Badge Estado Auditoría -->
                <td class="py-2 px-3 whitespace-nowrap">
                  <span
                    class="px-2 py-0.5 rounded text-[10px] font-bold text-white inline-block shadow-sm"
                    :style="{ backgroundColor: getItemColor(item) }"
                  >
                    {{ getStatusLabel(item) }}
                  </span>
                </td>

                <!-- Distancia Calculada -->
                <td class="py-2 px-3 text-right font-mono font-bold">
                  <span v-if="item.distancia_metros !== null" :class="item.en_rango ? 'text-emerald-600' : 'text-rose-600'">
                    {{ item.distancia_metros }} m
                  </span>
                  <span v-else-if="item.is_opportunity" class="text-purple-500 text-[11px]">
                    Oportunidad
                  </span>
                  <span v-else class="text-slate-300 text-[11px]">
                    No evaluado
                  </span>
                </td>

                <!-- Botón Ubicar -->
                <td class="py-2 px-3 text-center whitespace-nowrap">
                  <button
                    class="px-2 py-1 rounded bg-slate-100 hover:bg-slate-200 text-slate-700 font-bold text-[11px] transition"
                    @click.stop="focusItem(item)"
                  >
                    Ubicar
                  </button>
                </td>
              </tr>
              <tr v-if="items.length === 0">
                <td colspan="8" class="py-12 text-center text-gray-400 text-xs">
                  No hay datos registrados para los filtros seleccionados.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Modal Fotografía en Pantalla Completa con Detalle Forense -->
    <div
      v-if="modalItem"
      class="fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center z-50 p-4"
      @click.self="modalItem = null"
    >
      <div class="bg-white rounded-xl overflow-hidden max-w-2xl w-full shadow-2xl">
        <div class="p-3.5 border-b border-gray-100 flex justify-between items-center bg-slate-50">
          <div>
            <h4 class="font-bold text-sm text-slate-900">{{ modalItem.client_name }}</h4>
            <p class="text-xs text-slate-400">Ruta {{ modalItem.route }} | Vendedor: {{ modalItem.vendedor }}</p>
          </div>
          <button @click="modalItem = null" class="text-slate-400 hover:text-slate-800 font-bold text-xl px-2">
            &times;
          </button>
        </div>

        <div class="max-h-[65vh] bg-black flex items-center justify-center">
          <img :src="modalItem.photo_url" class="max-h-[65vh] object-contain" />
        </div>

        <div class="p-4 bg-white text-xs space-y-2 border-t border-gray-100">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <p class="text-slate-400 font-semibold uppercase text-[10px]">Hora Oficial de Captura</p>
              <p class="font-bold text-slate-800">{{ modalItem.visited_at }}</p>
            </div>
            <div>
              <p class="text-slate-400 font-semibold uppercase text-[10px]">Distancia de Conciliación</p>
              <p class="font-bold" :class="modalItem.en_rango ? 'text-emerald-600' : 'text-rose-600'">
                {{ modalItem.distancia_metros !== null ? modalItem.distancia_metros + ' metros' : 'Cliente Oportunidad' }}
              </p>
            </div>
            <div>
              <p class="text-slate-400 font-semibold uppercase text-[10px]">Coordenadas Fotografía</p>
              <p class="font-mono text-slate-700">{{ modalItem.visita_lat }}, {{ modalItem.visita_lon }} (±{{ Math.round(modalItem.accuracy || 0) }}m)</p>
            </div>
            <div v-if="modalItem.official_lat">
              <p class="text-slate-400 font-semibold uppercase text-[10px]">Coordenadas Catastro</p>
              <p class="font-mono text-slate-700">{{ modalItem.official_lat }}, {{ modalItem.official_lon }}</p>
            </div>
          </div>
          <p v-if="modalItem.comments" class="pt-2 border-t border-gray-100 text-slate-600">
            <strong>Observación del Vendedor:</strong> "{{ modalItem.comments }}"
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

// Orden cronológico: Primero los que tienen visita (ordenados por visited_at asc), luego los no visitados
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
  if (item.tipo_auditoria === 'DENTRO') return '#10b981'; // Verde: En Sitio
  if (item.tipo_auditoria === 'FUERA') return '#ef4444';  // Rojo: Desvío
  if (item.tipo_auditoria === 'OPORTUNIDAD') return '#8b5cf6'; // Púrpura: Oportunidad
  return '#f59e0b'; // Amarillo: No Visitado
}

function getStatusLabel(item) {
  if (item.tipo_auditoria === 'DENTRO') return `En Sitio (≤50m)`;
  if (item.tipo_auditoria === 'FUERA') return `Desvío (${item.distancia_metros}m)`;
  if (item.tipo_auditoria === 'OPORTUNIDAD') return 'Oportunidad';
  return 'No Visitado';
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
      radius: item.visitado ? 6.5 : 5,
      fillColor: markerColor,
      color: '#ffffff',
      weight: 1.5,
      fillOpacity: 0.9,
    });

    marker.bindPopup(`
      <div class="font-sans text-xs space-y-1">
        <div class="font-bold text-slate-900">${item.client_name}</div>
        <div class="text-[10px] font-mono text-slate-500">
          ${item.visited_at ? item.visited_at : 'PENDIENTE'} | Ruta: ${item.route}
        </div>
        <div class="pt-1">
          <span class="px-1.5 py-0.5 rounded text-[10px] font-bold text-white" style="background-color: ${markerColor}">
            ${getStatusLabel(item)}
          </span>
        </div>
        ${item.distancia_metros !== null ? `<div class="text-[11px] text-slate-700">Distancia: <strong>${item.distancia_metros} m</strong></div>` : ''}
      </div>
    `);

    marker.on('click', () => {
      selectedItem.value = item;
    });

    mapLayers.addLayer(marker);
    markersMap.set(item.id, marker);

    // Si tiene desvío, trazar la línea entre posición de la foto y posición oficial
    if (item.tipo_auditoria === 'FUERA' && item.official_lat && item.official_lon) {
      bounds.push([item.official_lat, item.official_lon]);

      const officialMarker = L.circleMarker([item.official_lat, item.official_lon], {
        radius: 4.5,
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
        dashArray: '4, 6',
        opacity: 0.7,
      });
      mapLayers.addLayer(dashLine);
    }
  });

  if (bounds.length > 0) {
    map.fitBounds(bounds, { padding: [35, 35], maxZoom: 16 });
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