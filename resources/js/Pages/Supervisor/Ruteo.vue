<template>
  <SupervisorLayout>
    <div class="space-y-4">
      <!-- Filtros Superiores Adaptativos -->
      <div class="bg-white p-3 sm:p-4 rounded-xl shadow-sm border border-gray-100 flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 sm:flex sm:items-center sm:gap-3 w-full sm:w-auto">
          <div class="flex items-center space-x-2">
            <label class="text-xs font-bold text-gray-600 uppercase min-w-[45px] sm:min-w-0">Ruta:</label>
            <select
              v-model="currentRoute"
              @change="fetchData"
              class="w-full sm:w-auto border border-gray-300 rounded-lg p-2 text-sm font-semibold bg-white outline-none focus:ring-2 focus:ring-amber-500"
            >
              <option v-for="r in rutas" :key="r" :value="r">{{ r }}</option>
            </select>
          </div>

          <div class="flex items-center space-x-2">
            <label class="text-xs font-bold text-gray-600 uppercase min-w-[45px] sm:min-w-0">Día:</label>
            <select
              v-model="currentDay"
              @change="fetchData"
              class="w-full sm:w-auto border border-gray-300 rounded-lg p-2 text-sm font-semibold bg-white outline-none focus:ring-2 focus:ring-amber-500"
            >
              <option v-for="d in dias" :key="d" :value="d">{{ d }}</option>
            </select>
          </div>
        </div>

        <div class="flex items-center justify-between sm:justify-end space-x-3 text-xs font-semibold text-gray-600 pt-2 sm:pt-0 border-t sm:border-t-0 border-gray-100">
          <span v-if="currentRoute === 'TODAS'" class="text-slate-500 italic text-[11px] sm:text-xs">
            Fronteras territoriales activas
          </span>
          <div class="bg-slate-50 px-2.5 py-1 rounded-md border border-slate-200">
            Clientes: <span class="font-bold text-gray-900">{{ clientes.length }}</span>
          </div>
        </div>
      </div>

      <!-- Contenedor Principal: Mapa y Cartera de Clientes -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <!-- Mapa -->
        <div class="lg:col-span-2 bg-white p-2 sm:p-3 rounded-xl shadow-sm border border-gray-100">
          <div id="map-ruteo" class="h-[380px] sm:h-[500px] lg:h-[650px] w-full rounded-lg"></div>
        </div>

        <!-- Cartera de Clientes Lateral -->
        <div class="bg-white p-3 sm:p-4 rounded-xl shadow-sm border border-gray-100 flex flex-col h-[480px] sm:h-[550px] lg:h-[670px]">
          <div class="flex justify-between items-center mb-3 pb-2 border-b border-gray-100">
            <h3 class="text-xs sm:text-sm font-bold text-gray-800 uppercase tracking-wide">Cartera de Clientes</h3>
            <span class="text-xs text-gray-400 font-mono">({{ clientes.length }})</span>
          </div>

          <div class="flex-1 overflow-y-auto space-y-2 pr-1">
            <div
              v-for="c in clientes"
              :key="c.id || c.client_id"
              class="p-2.5 sm:p-3 border rounded-lg hover:border-amber-500 transition cursor-pointer"
              :class="c.status === 'Activo' ? 'border-gray-200 bg-white' : 'border-red-200 bg-red-50/50'"
              @click="focusClient(c)"
            >
              <div class="flex justify-between items-start">
                <span class="text-xs font-mono font-bold text-gray-500">#{{ c.client_id }}</span>
                <div class="flex items-center space-x-1.5">
                  <span
                    v-if="currentRoute === 'TODAS'"
                    class="text-[10px] px-1.5 py-0.5 rounded font-bold text-white shadow-xs"
                    :style="{ backgroundColor: getRouteColor(c.route) }"
                  >
                    {{ c.route }}
                  </span>
                  <span
                    class="text-[11px] px-2 py-0.5 rounded font-bold"
                    :class="c.status === 'Activo' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                  >
                    {{ c.status }}
                  </span>
                </div>
              </div>
              <p class="font-bold text-xs sm:text-sm text-gray-900 mt-1 line-clamp-1">{{ c.client_name }}</p>
              <p class="text-[11px] sm:text-xs text-gray-500 mt-0.5 line-clamp-2">{{ c.address || 'Sin dirección registrada' }}</p>
              <div class="flex justify-between items-center mt-1 text-[10px] sm:text-[11px] text-gray-400 pt-1 border-t border-gray-50">
                <span class="truncate max-w-[140px] sm:max-w-[200px]">{{ c.reference ? 'Ref: ' + c.reference : '' }}</span>
                <span class="font-semibold text-slate-600 shrink-0">Día: {{ c.day }}</span>
              </div>
            </div>

            <div v-if="clientes.length === 0" class="text-center text-gray-400 py-16 text-xs">
              No hay clientes asignados para este criterio.
            </div>
          </div>
        </div>
      </div>
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue';
import L from 'leaflet';

const props = defineProps({
  rutas: Array,
  dias: Array,
  selected_route: String,
  selected_day: String,
  clientes: Array,
});

const currentRoute = ref(props.selected_route);
const currentDay = ref(props.selected_day);

let map = null;
let markersLayer = null;
const clientMarkersMap = new Map();

const ROUTE_PALETTE = [
  '#2563eb', '#ea580c', '#16a34a', '#9333ea', '#0891b2',
  '#dc2626', '#d97706', '#4f46e5', '#059669', '#be123c',
  '#475569', '#84cc16'
];

function getRouteColor(routeName) {
  if (!routeName) return '#6b7280';
  let hash = 0;
  for (let i = 0; i < routeName.length; i++) {
    hash = routeName.charCodeAt(i) + ((hash << 5) - hash);
  }
  const index = Math.abs(hash) % ROUTE_PALETTE.length;
  return ROUTE_PALETTE[index];
}

function fetchData() {
  router.get(
    route('supervisor.ruteo.index'),
    {
      route: currentRoute.value,
      day: currentDay.value,
    },
    {
      preserveState: true,
      preserveScroll: true,
      only: ['clientes', 'selected_route', 'selected_day'],
    }
  );
}

function renderMarkers() {
  if (!map || !markersLayer) return;

  markersLayer.clearLayers();
  clientMarkersMap.clear();

  const bounds = [];
  const isAllRoutes = currentRoute.value === 'TODAS';

  props.clientes.forEach((c) => {
    const lat = parseFloat(c.latitude);
    const lng = parseFloat(c.longitude);

    if (isNaN(lat) || isNaN(lng)) return;

    const markerColor = isAllRoutes
      ? getRouteColor(c.route)
      : (c.status === 'Activo' ? '#16a34a' : '#9ca3af');

    const marker = L.circleMarker([lat, lng], {
      radius: isAllRoutes ? 5 : 6,
      fillColor: markerColor,
      color: '#ffffff',
      weight: 1.2,
      fillOpacity: 0.85,
    });

    marker.bindPopup(`
      <div class="font-sans text-xs space-y-1 max-w-[200px]">
        <div class="font-bold text-slate-800">#${c.client_id} - ${c.client_name}</div>
        <div class="text-slate-600">${c.address || 'Sin dirección'}</div>
        <div class="text-[11px] text-slate-500">
          <span class="font-semibold">Ruta:</span> ${c.route} | <span class="font-semibold">Día:</span> ${c.day}
        </div>
        <div class="text-[11px]">
          <span class="font-semibold">Estado:</span> 
          <span class="${c.status === 'Activo' ? 'text-green-600 font-bold' : 'text-red-600 font-bold'}">${c.status}</span>
        </div>
      </div>
    `);

    markersLayer.addLayer(marker);
    clientMarkersMap.set(c.client_id, marker);
    bounds.push([lat, lng]);
  });

  if (bounds.length > 0) {
    map.fitBounds(bounds, { padding: [25, 25], maxZoom: 16 });
  }
}

function focusClient(client) {
  const lat = parseFloat(client.latitude);
  const lng = parseFloat(client.longitude);

  if (isNaN(lat) || isNaN(lng) || !map) return;

  map.setView([lat, lng], 17, { animate: true });
  const marker = clientMarkersMap.get(client.client_id);
  if (marker) {
    marker.openPopup();
  }
}

watch(
  () => props.clientes,
  () => {
    renderMarkers();
  },
  { deep: true }
);

onMounted(() => {
  map = L.map('map-ruteo').setView([-16.5000, -68.1500], 12);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© OpenStreetMap contributors',
  }).addTo(map);

  markersLayer = L.featureGroup().addTo(map);
  renderMarkers();
});
</script>