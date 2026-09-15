<template>
  <SupervisorLayout>
    <div class="space-y-4">
      <!-- Filtros Superiores y Controles de Telemetría -->
      <div class="bg-white p-3 sm:p-4 rounded-xl shadow-sm border border-gray-100 space-y-3 sm:space-y-4">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
          <div class="grid grid-cols-1 sm:grid-cols-2 lg:flex lg:items-center gap-2.5 sm:gap-3 w-full lg:w-auto">
            <div class="w-full sm:w-auto">
              <label class="text-[10px] sm:text-[11px] font-bold text-gray-500 uppercase block mb-1">Vendedor / Ruta:</label>
              <select
                v-model="selectedUser"
                @change="applyFilter"
                class="w-full border border-gray-300 rounded-lg p-2 text-xs sm:text-sm font-semibold bg-white outline-none focus:ring-2 focus:ring-amber-500"
              >
                <option :value="null">-- Todos los Vendedores --</option>
                <option v-for="u in vendedores" :key="u.id" :value="u.id">{{ u.username }}</option>
              </select>
            </div>

            <div class="w-full sm:w-auto">
              <label class="text-[10px] sm:text-[11px] font-bold text-gray-500 uppercase block mb-1">Fecha de Operación:</label>
              <input
                type="date"
                v-model="selectedDate"
                @change="applyFilter"
                class="w-full border border-gray-300 rounded-lg p-1.5 sm:p-2 text-xs sm:text-sm font-semibold bg-white outline-none focus:ring-2 focus:ring-amber-500"
              />
            </div>

            <!-- Controles de Refresco: Switch y Botón Manual -->
            <div class="flex items-center space-x-3 pt-1 sm:pt-4 col-span-1 sm:col-span-2 lg:col-span-1">
              <label class="relative inline-flex items-center cursor-pointer select-none">
                <input type="checkbox" v-model="livePolling" class="sr-only peer" />
                <div class="w-9 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-600"></div>
                <span class="ml-2 text-xs font-bold text-gray-700 flex items-center gap-1.5">
                  <span v-if="livePolling" class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                  En Vivo
                </span>
              </label>

              <button
                v-if="!livePolling"
                @click="manualRefresh"
                :disabled="isRefreshing"
                class="flex items-center space-x-1.5 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 active:bg-slate-300 text-slate-700 rounded-lg text-xs font-bold transition disabled:opacity-50"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  class="h-3.5 w-3.5"
                  :class="{ 'animate-spin': isRefreshing }"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                >
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span>{{ isRefreshing ? '...' : 'Actualizar' }}</span>
              </button>
            </div>
          </div>

          <!-- Leyenda de Estados Móvil/Desktop -->
          <div class="flex flex-wrap gap-2 text-[10px] sm:text-xs font-medium text-gray-600 bg-slate-50 p-2 sm:p-2.5 rounded-lg border border-slate-200">
            <span class="flex items-center space-x-1">
              <span class="w-2.5 h-2.5 rounded-full bg-[#16a34a]"></span>
              <span>Legítimo</span>
            </span>
            <span class="flex items-center space-x-1">
              <span class="w-2.5 h-2.5 rounded-full bg-[#ea580c]"></span>
              <span>Sospechoso</span>
            </span>
            <span class="flex items-center space-x-1">
              <span class="w-2.5 h-2.5 rounded-full bg-[#dc2626]"></span>
              <span>Mock</span>
            </span>
            <span class="flex items-center space-x-1">
              <span class="w-2.5 h-2.5 rounded-full bg-[#2563eb]"></span>
              <span>Detenido</span>
            </span>
          </div>
        </div>

        <!-- Resumen de Telemetría -->
        <div v-if="puntos.length > 0" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2 sm:gap-3 pt-2 border-t border-gray-100">
          <div class="bg-slate-50 p-2 sm:p-3 rounded-lg border border-slate-100">
            <p class="text-[9px] sm:text-[11px] font-bold text-gray-400 uppercase">Puntos</p>
            <p class="text-sm sm:text-base font-black text-slate-800">{{ puntos.length }}</p>
          </div>
          <div class="bg-slate-50 p-2 sm:p-3 rounded-lg border border-slate-100">
            <p class="text-[9px] sm:text-[11px] font-bold text-gray-400 uppercase">Distancia</p>
            <p class="text-sm sm:text-base font-black text-slate-800">{{ totalDistanceKm }} km</p>
          </div>
          <div class="bg-slate-50 p-2 sm:p-3 rounded-lg border border-slate-100">
            <p class="text-[9px] sm:text-[11px] font-bold text-gray-400 uppercase">Batería</p>
            <p class="text-sm sm:text-base font-black" :class="lastBatteryLevel !== null && lastBatteryLevel <= 20 ? 'text-red-600' : 'text-slate-800'">
              {{ lastBatteryLevel !== null ? lastBatteryLevel + '%' : 'N/D' }}
            </p>
          </div>
          <div class="bg-slate-50 p-2 sm:p-3 rounded-lg border border-slate-100">
            <p class="text-[9px] sm:text-[11px] font-bold text-gray-400 uppercase">Inicio</p>
            <p class="text-[11px] sm:text-xs font-bold text-slate-700 mt-0.5 truncate">{{ formatTime(puntos[0]?.recorded_at) }}</p>
          </div>
          <div class="bg-slate-50 p-2 sm:p-3 rounded-lg border border-slate-100 col-span-2 sm:col-span-1">
            <p class="text-[9px] sm:text-[11px] font-bold text-gray-400 uppercase">Último Reporte</p>
            <p class="text-[11px] sm:text-xs font-bold text-slate-700 mt-0.5 truncate">{{ formatTime(puntos[puntos.length - 1]?.recorded_at) }}</p>
          </div>
        </div>
      </div>

      <!-- Mapa -->
      <div class="bg-white p-2 sm:p-3 rounded-xl shadow-sm border border-gray-100 relative">
        <div id="map-tracking" class="h-[380px] sm:h-[480px] lg:h-[550px] w-full rounded-lg"></div>
        <div
          v-if="puntos.length === 0"
          class="absolute inset-0 bg-white/85 backdrop-blur-xs z-[500] flex flex-col items-center justify-center p-4 text-center rounded-lg"
        >
          <p class="text-xs sm:text-sm font-bold text-gray-600">No hay telemetría registrada para este criterio.</p>
          <p class="text-[11px] text-gray-400 mt-0.5">Verifica la fecha seleccionada o el estado del GPS en el dispositivo móvil.</p>
        </div>
      </div>

      <!-- Tabla Resumen con scroll horizontal en móviles -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-3 sm:p-4 border-b border-gray-100 flex justify-between items-center bg-slate-50">
          <div>
            <h3 class="text-xs sm:text-sm font-bold text-slate-800 uppercase">Registro Detallado</h3>
            <p class="text-[10px] sm:text-xs text-slate-400">Orden cronológico descendente</p>
          </div>
          <span class="text-[10px] sm:text-xs font-mono font-bold bg-white px-2 py-0.5 rounded border border-gray-200 text-slate-600">
            {{ puntos.length }} pts
          </span>
        </div>

        <div class="overflow-x-auto max-h-72 sm:max-h-80">
          <table class="w-full text-left border-collapse text-[11px] sm:text-xs min-w-[550px]">
            <thead class="bg-slate-100 text-slate-600 font-bold sticky top-0 z-10 border-b border-slate-200">
              <tr>
                <th class="py-2 px-3">Hora</th>
                <th class="py-2 px-3">Ruta</th>
                <th class="py-2 px-3">Estado</th>
                <th class="py-2 px-3 text-right">Vel.</th>
                <th class="py-2 px-3 text-right">Varianza</th>
                <th class="py-2 px-3 text-right">Pasos</th>
                <th class="py-2 px-3 text-center">Batería</th>
                <th class="py-2 px-3 text-center">Acción</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr
                v-for="p in reversedPuntos"
                :key="p.id"
                class="hover:bg-amber-50/40 transition cursor-pointer"
                @click="centerOnPoint(p)"
              >
                <td class="py-1.5 px-3 font-mono font-bold text-slate-700 whitespace-nowrap">
                  {{ formatTime(p.recorded_at) }}
                </td>
                <td class="py-1.5 px-3 font-semibold text-slate-800 whitespace-nowrap">
                  {{ p.vendedor_ruta }}
                </td>
                <td class="py-1.5 px-3 whitespace-nowrap">
                  <span
                    class="px-1.5 py-0.5 rounded text-[9px] sm:text-[10px] font-bold text-white inline-block"
                    :style="{ backgroundColor: getPointColor(p) }"
                  >
                    {{ getPointStatus(p) }}
                  </span>
                </td>
                <td class="py-1.5 px-3 text-right font-mono text-slate-700 whitespace-nowrap">
                  {{ (p.speed * 3.6).toFixed(1) }} km/h
                </td>
                <td class="py-1.5 px-3 text-right font-mono text-slate-600 whitespace-nowrap">
                  {{ parseFloat(p.motion_variance || 0).toFixed(4) }}
                </td>
                <td class="py-1.5 px-3 text-right font-mono text-slate-600 whitespace-nowrap">
                  {{ p.step_count || 0 }}
                </td>
                <td class="py-1.5 px-3 text-center whitespace-nowrap">
                  <span
                    class="font-mono font-bold"
                    :class="p.battery_level !== null && p.battery_level <= 20 ? 'text-red-600' : 'text-slate-700'"
                  >
                    {{ p.battery_level !== null ? p.battery_level + '%' : '-' }}
                  </span>
                </td>
                <td class="py-1.5 px-3 text-center whitespace-nowrap">
                  <button
                    class="text-amber-600 hover:text-amber-700 font-bold text-[11px] underline"
                    @click.stop="centerOnPoint(p)"
                  >
                    Ubicar
                  </button>
                </td>
              </tr>
              <tr v-if="puntos.length === 0">
                <td colspan="8" class="py-8 text-center text-gray-400">
                  Sin datos registrados.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </SupervisorLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue';
import L from 'leaflet';

const props = defineProps({
  vendedores: Array,
  selected_date: String,
  selected_user_id: [Number, String, null],
  puntos: Array,
});

const selectedUser = ref(props.selected_user_id ? Number(props.selected_user_id) : null);
const selectedDate = ref(props.selected_date);
const livePolling = ref(false);
const isRefreshing = ref(false);

let map = null;
let trackingLayers = null;
let pollTimer = null;
const pointMarkersMap = new Map();

const reversedPuntos = computed(() => [...props.puntos].reverse());

const totalDistanceKm = computed(() => {
  if (!props.puntos || props.puntos.length < 2) return '0.00';
  let totalMeters = 0;
  for (let i = 1; i < props.puntos.length; i++) {
    totalMeters += calculateHaversine(
      props.puntos[i - 1].latitude,
      props.puntos[i - 1].longitude,
      props.puntos[i].latitude,
      props.puntos[i].longitude
    );
  }
  return (totalMeters / 1000).toFixed(2);
});

const lastBatteryLevel = computed(() => {
  if (!props.puntos || props.puntos.length === 0) return null;
  return props.puntos[props.puntos.length - 1].battery_level;
});

function calculateHaversine(lat1, lon1, lat2, lon2) {
  const R = 6371e3;
  const phi1 = (lat1 * Math.PI) / 180;
  const phi2 = (lat2 * Math.PI) / 180;
  const deltaPhi = ((lat2 - lat1) * Math.PI) / 180;
  const deltaLambda = ((lon2 - lon1) * Math.PI) / 180;

  const a =
    Math.sin(deltaPhi / 2) * Math.sin(deltaPhi / 2) +
    Math.cos(phi1) * Math.cos(phi2) * Math.sin(deltaLambda / 2) * Math.sin(deltaLambda / 2);
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

  return R * c;
}

function calculateBearing(lat1, lon1, lat2, lon2) {
  const y = Math.sin((lon2 - lon1) * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180);
  const x =
    Math.cos(lat1 * Math.PI / 180) * Math.sin(lat2 * Math.PI / 180) -
    Math.sin(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.cos((lon2 - lon1) * Math.PI / 180);
  const theta = Math.atan2(y, x);
  return (theta * 180 / Math.PI + 360) % 360;
}

function formatTime(dtString) {
  if (!dtString) return '--:--';
  const parts = dtString.split(' ');
  return parts.length > 1 ? parts[1] : dtString;
}

function getPointColor(p) {
  if (p.is_mock) return '#dc2626';
  if (p.is_moving) {
    return p.motion_variance >= 0.60 ? '#16a34a' : '#ea580c';
  }
  return '#2563eb';
}

function getPointStatus(p) {
  if (p.is_mock) return 'MOCK';
  if (p.is_moving) {
    return p.motion_variance >= 0.60 ? 'Legítimo' : 'Sospechoso';
  }
  return 'Detenido';
}

function applyFilter() {
  router.get(
    route('supervisor.tracking.index'),
    {
      user_id: selectedUser.value || '',
      date: selectedDate.value,
    },
    {
      preserveState: true,
      preserveScroll: true,
      only: ['puntos', 'selected_user_id', 'selected_date'],
    }
  );
}

function manualRefresh() {
  isRefreshing.value = true;
  router.reload({
    only: ['puntos'],
    onFinish: () => {
      isRefreshing.value = false;
    },
  });
}

function renderTracking() {
  if (!map || !trackingLayers) return;

  trackingLayers.clearLayers();
  pointMarkersMap.clear();

  if (!props.puntos || props.puntos.length === 0) return;

  const coords = [];

  props.puntos.forEach((p, index) => {
    const lat = parseFloat(p.latitude);
    const lng = parseFloat(p.longitude);
    if (isNaN(lat) || isNaN(lng)) return;

    coords.push([lat, lng]);

    const markerColor = getPointColor(p);
    const statusLabel = getPointStatus(p);

    const isFirst = index === 0;
    const isLast = index === props.puntos.length - 1;

    const marker = L.circleMarker([lat, lng], {
      radius: isFirst || isLast ? 7 : 4.5,
      fillColor: isFirst ? '#059669' : (isLast ? '#2563eb' : markerColor),
      color: '#ffffff',
      weight: isFirst || isLast ? 2 : 1,
      fillOpacity: 0.9,
    });

    marker.bindPopup(`
      <div class="font-sans text-xs space-y-1 max-w-[190px]">
        <div class="font-bold text-slate-800">${p.vendedor_ruta}</div>
        <div class="text-[10px] font-mono text-slate-500">${p.recorded_at}</div>
        <div class="pt-0.5">
          <span class="px-1 py-0.5 rounded text-[9px] font-bold text-white" style="background-color: ${markerColor}">
            ${statusLabel}
          </span>
        </div>
        <div class="text-[10px] text-slate-600 pt-0.5">
          Vel: ${(p.speed * 3.6).toFixed(1)} km/h | Bat: ${p.battery_level !== null ? p.battery_level + '%' : 'N/D'}
        </div>
      </div>
    `);

    trackingLayers.addLayer(marker);
    pointMarkersMap.set(p.id, marker);

    if (selectedUser.value && index > 0 && index % 3 === 0) {
      const prevLat = parseFloat(props.puntos[index - 1].latitude);
      const prevLng = parseFloat(props.puntos[index - 1].longitude);
      const bearing = calculateBearing(prevLat, prevLng, lat, lng);

      const arrowIcon = L.divIcon({
        className: 'tracking-arrow',
        html: `<div style="transform: rotate(${bearing}deg); width: 10px; height: 10px; display: flex; align-items: center; justify-content: center; color: #0284c7; font-size: 9px; font-weight: bold;">▲</div>`,
        iconSize: [10, 10],
        iconAnchor: [5, 5],
      });

      trackingLayers.addLayer(L.marker([lat, lng], { icon: arrowIcon }));
    }
  });

  if (coords.length > 1) {
    const polyline = L.polyline(coords, {
      color: selectedUser.value ? '#0284c7' : '#94a3b8',
      weight: selectedUser.value ? 3.5 : 2,
      opacity: 0.8,
      lineJoin: 'round',
    });
    trackingLayers.addLayer(polyline);
    map.fitBounds(polyline.getBounds(), { padding: [25, 25], maxZoom: 16 });
  } else if (coords.length === 1) {
    map.setView(coords[0], 15);
  }
}

function centerOnPoint(point) {
  const lat = parseFloat(point.latitude);
  const lng = parseFloat(point.longitude);
  if (isNaN(lat) || isNaN(lng) || !map) return;

  map.setView([lat, lng], 17, { animate: true });
  const marker = pointMarkersMap.get(point.id);
  if (marker) marker.openPopup();
}

watch(
  () => props.puntos,
  () => {
    renderTracking();
  },
  { deep: true }
);

watch(livePolling, (enabled) => {
  if (enabled) {
    pollTimer = setInterval(() => {
      router.reload({ only: ['puntos'] });
    }, 15000);
  } else {
    if (pollTimer) clearInterval(pollTimer);
  }
});

onMounted(() => {
  map = L.map('map-tracking').setView([-16.5000, -68.1500], 13);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© OpenStreetMap contributors',
  }).addTo(map);

  trackingLayers = L.featureGroup().addTo(map);
  renderTracking();
});

onUnmounted(() => {
  if (pollTimer) clearInterval(pollTimer);
});
</script>