<template>
  <SupervisorLayout>
    <div class="space-y-4">
      <!-- Filtros Superiores -->
      <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex flex-wrap gap-4 items-center justify-between">
        <div class="flex items-center space-x-3">
          <label class="text-xs font-bold text-gray-600 uppercase">Ruta:</label>
          <select v-model="currentRoute" @change="fetchData" class="border border-gray-300 rounded-lg p-2 text-sm font-semibold">
            <option v-for="r in rutas" :key="r" :value="r">{{ r }}</option>
          </select>

          <label class="text-xs font-bold text-gray-600 uppercase ml-2">Día:</label>
          <select v-model="currentDay" @change="fetchData" class="border border-gray-300 rounded-lg p-2 text-sm font-semibold">
            <option v-for="d in dias" :key="d" :value="d">{{ d }}</option>
          </select>
        </div>

        <div class="flex items-center space-x-4 text-xs font-semibold text-gray-600">
          <div v-if="currentRoute === 'TODAS'" class="text-slate-500 italic">
            Visualizando fronteras territoriales por color
          </div>
          <div>
            Total Clientes: <span class="font-bold text-gray-900">{{ clientes.length }}</span>
          </div>
        </div>
      </div>

      <!-- Mapa y Lista de Clientes -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 bg-white p-3 rounded-xl shadow-sm border border-gray-100">
          <div id="map-ruteo" class="h-[650px] w-full rounded-lg"></div>
        </div>

        <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex flex-col h-[670px]">
          <div class="flex justify-between items-center mb-3">
            <h3 class="text-sm font-bold text-gray-800 uppercase">Cartera de Clientes</h3>
            <span class="text-xs text-gray-400 font-mono">({{ clientes.length }})</span>
          </div>

          <div class="flex-1 overflow-y-auto space-y-2 pr-2">
            <div
              v-for="c in clientes"
              :key="c.id || c.client_id"
              class="p-3 border rounded-lg hover:border-amber-500 transition cursor-pointer"
              :class="c.status === 'Activo' ? 'border-gray-200 bg-white' : 'border-red-200 bg-red-50'"
              @click="focusClient(c)"
            >
              <div class="flex justify-between items-start">
                <span class="text-xs font-mono font-bold text-gray-500">#{{ c.client_id }}</span>
                <div class="flex items-center space-x-1.5">
                  <span
                    v-if="currentRoute === 'TODAS'"
                    class="text-[10px] px-1.5 py-0.5 rounded font-bold text-white"
                    :style="{ backgroundColor: getRouteColor(c.route) }"
                  >
                    {{ c.route }}
                  </span>
                  <span
                    class="text-xs px-2 py-0.5 rounded font-bold"
                    :class="c.status === 'Activo' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'"
                  >
                    {{ c.status }}
                  </span>
                </div>
              </div>
              <p class="font-bold text-sm text-gray-900 mt-1">{{ c.client_name }}</p>
              <p class="text-xs text-gray-500 mt-0.5">{{ c.address || 'Sin dirección registrada' }}</p>
              <div class="flex justify-between items-center mt-1 text-[11px] text-gray-400">
                <span>{{ c.reference ? 'Ref: ' + c.reference : '' }}</span>
                <span class="font-semibold text-slate-600">Día: {{ c.day }}</span>
              </div>
            </div>

            <div v-if="clientes.length === 0" class="text-center text-gray-400 py-16 text-xs">
              No hay clientes asignados para este criterio de búsqueda.
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

// Paleta cromática fija para distinguir fronteras de rutas
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
      <div class="font-sans text-xs space-y-1">
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
    map.fitBounds(bounds, { padding: [35, 35], maxZoom: 16 });
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