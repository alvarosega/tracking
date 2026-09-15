<template>
  <SupervisorLayout>
    <div class="space-y-4">
      <!-- Filtros Superiores, Controles de Tiempo Real y Métricas -->
      <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 space-y-4">
        <div class="flex flex-wrap gap-4 items-center justify-between">
          <div class="flex flex-wrap items-center gap-3">
            <div>
              <label class="text-[11px] font-bold text-gray-500 uppercase block mb-1">Vendedor / Ruta:</label>
              <select
                v-model="selectedUser"
                @change="applyFilter"
                class="border border-gray-300 rounded-lg p-2 text-sm font-semibold bg-white min-w-[210px] focus:ring-2 focus:ring-amber-500 outline-none"
              >
                <option :value="null">-- Todos los Vendedores --</option>
                <option v-for="u in vendedores" :key="u.id" :value="u.id">{{ u.username }}</option>
              </select>
            </div>

            <div>
              <label class="text-[11px] font-bold text-gray-500 uppercase block mb-1">Fecha de Operación:</label>
              <input
                type="date"
                v-model="selectedDate"
                @change="applyFilter"
                class="border border-gray-300 rounded-lg p-2 text-sm font-semibold bg-white focus:ring-2 focus:ring-amber-500 outline-none"
              />
            </div>

            <!-- Controles de Refresco: Switch y Botón Manual -->
            <div class="flex items-center space-x-3 pt-5">
              <!-- Switch Modo En Vivo -->
              <label class="relative inline-flex items-center cursor-pointer select-none">
                <input type="checkbox" v-model="livePolling" class="sr-only peer" />
                <div class="w-10 h-5 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:bg-emerald-600"></div>
                <span class="ml-2.5 text-xs font-bold text-gray-700 flex items-center gap-1.5">
                  <span v-if="livePolling" class="w-2 h-2 rounded-full bg-emerald-500 animate-ping"></span>
                  En Vivo
                </span>
              </label>

              <!-- Botón Actualizar (Solo cuando En Vivo está inactivo) -->
              <button
                v-if="!livePolling"
                @click="manualRefresh"
                :disabled="isRefreshing"
                class="flex items-center space-x-1.5 px-3 py-1.5 bg-slate-100 hover:bg-slate-200 active:bg-slate-300 text-slate-700 rounded-lg text-xs font-bold transition disabled:opacity-50"
                title="Sincronizar telemetría actual"
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
                <span>{{ isRefreshing ? 'Actualizando...' : 'Actualizar' }}</span>
              </button>
            </div>
          </div>

          <!-- Leyenda de Estados -->
          <div class="flex flex-wrap gap-3 text-xs font-medium text-gray-600 bg-slate-50 p-2.5 rounded-lg border border-slate-200">
            <span class="flex items-center space-x-1.5">
              <span class="w-3 h-3 rounded-full bg-[#16a34a] border border-white shadow-sm"></span>
              <span>Legítimo</span>
            </span>
            <span class="flex items-center space-x-1.5">
              <span class="w-3 h-3 rounded-full bg-[#ea580c] border border-white shadow-sm"></span>
              <span>Sospechoso</span>
            </span>
            <span class="flex items-center space-x-1.5">
              <span class="w-3 h-3 rounded-full bg-[#dc2626] border border-white shadow-sm"></span>
              <span>Mock GPS</span>
            </span>
            <span class="flex items-center space-x-1.5">
              <span class="w-3 h-3 rounded-full bg-[#2563eb] border border-white shadow-sm"></span>
              <span>Detenido</span>
            </span>
          </div>
        </div>

        <!-- Barra de Telemetría Acumulada -->
        <div v-if="puntos.length > 0" class="grid grid-cols-2 sm:grid-cols-5 gap-3 pt-2 border-t border-gray-100">
          <div class="bg-slate-50 p-3 rounded-lg border border-slate-100">
            <p class="text-[11px] font-bold text-gray-400 uppercase">Puntos Capturados</p>
            <p class="text-base font-black text-slate-800">{{ puntos.length }}</p>
          </div>
          <div class="bg-slate-50 p-3 rounded-lg border border-slate-100">
            <p class="text-[11px] font-bold text-gray-400 uppercase">Distancia Total</p>
            <p class="text-base font-black text-slate-800">{{ totalDistanceKm }} km</p>
          </div>
          <div class="bg-slate-50 p-3 rounded-lg border border-slate-100">
            <p class="text-[11px] font-bold text-gray-400 uppercase">Batería Terminal</p>
            <p class="text-base font-black" :class="lastBatteryLevel !== null && lastBatteryLevel <= 20 ? 'text-red-600' : 'text-slate-800'">
              {{ lastBatteryLevel !== null ? lastBatteryLevel + '%' : 'N/D' }}
            </p>
          </div>
          <div class="bg-slate-50 p-3 rounded-lg border border-slate-100">
            <p class="text-[11px] font-bold text-gray-400 uppercase">Inicio Registro</p>
            <p class="text-xs font-bold text-slate-700 mt-1">{{ formatTime(puntos[0]?.recorded_at) }}</p>
          </div>
          <div class="bg-slate-50 p-3 rounded-lg border border-slate-100">
            <p class="text-[11px] font-bold text-gray-400 uppercase">Último Reporte</p>
            <p class="text-xs font-bold text-slate-700 mt-1">{{ formatTime(puntos[puntos.length - 1]?.recorded_at) }}</p>
          </div>
        </div>
      </div>

      <!-- Contenedor del Mapa -->
      <div class="bg-white p-3 rounded-xl shadow-sm border border-gray-100 relative">
        <div id="map-tracking" class="h-[550px] w-full rounded-lg"></div>
        <div
          v-if="puntos.length === 0"
          class="absolute inset-0 bg-white/80 backdrop-blur-sm z-[500] flex flex-col items-center justify-center rounded-lg"
        >
          <p class="text-sm font-bold text-gray-600">No hay telemetría registrada para este criterio.</p>
          <p class="text-xs text-gray-400 mt-1">Selecciona otra fecha o aguarda la sincronización de los dispositivos.</p>
        </div>
      </div>

      <!-- Tabla Resumen de Puntos de Auditoría -->
      <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
        <div class="p-4 border-b border-gray-100 flex justify-between items-center bg-slate-50">
          <div>
            <h3 class="text-sm font-bold text-slate-800 uppercase">Registro Detallado de Telemetría</h3>
            <p class="text-xs text-slate-400 mt-0.5">Listado cronológico en orden descendente</p>
          </div>
          <span class="text-xs font-mono font-bold bg-white px-2.5 py-1 rounded border border-gray-200 text-slate-600">
            {{ puntos.length }} registros
          </span>
        </div>

        <div class="max-h-80 overflow-y-auto">
          <table class="w-full text-left border-collapse text-xs">
            <thead class="bg-slate-100 text-slate-600 font-bold sticky top-0 z-10 border-b border-slate-200">
              <tr>
                <th class="py-2.5 px-4">Hora</th>
                <th class="py-2.5 px-4">Ruta / Vendedor</th>
                <th class="py-2.5 px-4">Estado / Auditoría</th>
                <th class="py-2.5 px-4 text-right">Velocidad</th>
                <th class="py-2.5 px-4 text-right">Varianza (σ²)</th>
                <th class="py-2.5 px-4 text-right">Pasos</th>
                <th class="py-2.5 px-4 text-center">Batería</th>
                <th class="py-2.5 px-4 text-center">Acción</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
              <tr
                v-for="p in reversedPuntos"
                :key="p.id"
                class="hover:bg-amber-50/40 transition cursor-pointer"
                @click="centerOnPoint(p)"
              >
                <td class="py-2 px-4 font-mono font-bold text-slate-700 whitespace-nowrap">
                  {{ formatTime(p.recorded_at) }}
                </td>
                <td class="py-2 px-4 font-semibold text-slate-800">
                  {{ p.vendedor_ruta }}
                </td>
                <td class="py-2 px-4">
                  <span
                    class="px-2 py-0.5 rounded text-[10px] font-bold text-white whitespace-nowrap inline-block"
                    :style="{ backgroundColor: getPointColor(p) }"
                  >
                    {{ getPointStatus(p) }}
                  </span>
                </td>
                <td class="py-2 px-4 text-right font-mono text-slate-700">
                  {{ (p.speed * 3.6).toFixed(1) }} km/h
                </td>
                <td class="py-2 px-4 text-right font-mono text-slate-600">
                  {{ parseFloat(p.motion_variance || 0).toFixed(4) }}
                </td>
                <td class="py-2 px-4 text-right font-mono text-slate-600">
                  {{ p.step_count || 0 }}
                </td>
                <td class="py-2 px-4 text-center">
                  <span
                    class="font-mono font-bold"
                    :class="p.battery_level !== null && p.battery_level <= 20 ? 'text-red-600' : 'text-slate-700'"
                  >
                    {{ p.battery_level !== null ? p.battery_level + '%' : '-' }}
                  </span>
                </td>
                <td class="py-2 px-4 text-center">
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

// Invertir lista para la tabla sin mutar el prop original
const reversedPuntos = computed(() => {
  return [...props.puntos].reverse();
});

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
  if (p.is_mock) return '#dc2626'; // Rojo = Mock
  if (p.is_moving) {
    return p.motion_variance >= 0.60 ? '#16a34a' : '#ea580c'; // Verde = Legítimo, Naranja = Sospechoso
  }
  return '#2563eb'; // Azul = Detenido
}

function getPointStatus(p) {
  if (p.is_mock) return 'MOCK GPS DETECTADO';
  if (p.is_moving) {
    return p.motion_variance >= 0.60 ? 'En Movimiento (Legítimo)' : 'Sospechoso (Baja Varianza)';
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
      radius: isFirst || isLast ? 8 : 5,
      fillColor: isFirst ? '#059669' : (isLast ? '#2563eb' : markerColor),
      color: '#ffffff',
      weight: isFirst || isLast ? 2.5 : 1,
      fillOpacity: 0.9,
    });

    marker.bindPopup(`
      <div class="font-sans text-xs space-y-1">
        <div class="font-bold text-slate-800 text-sm">${p.vendedor_ruta}</div>
        <div class="text-[11px] font-mono text-slate-500">${p.recorded_at}</div>
        <div class="pt-1">
          <span class="px-1.5 py-0.5 rounded text-[10px] font-bold text-white" style="background-color: ${markerColor}">
            ${statusLabel}
          </span>
        </div>
        <div class="text-[11px] text-slate-600 pt-1 leading-relaxed">
          <strong>Velocidad:</strong> ${(p.speed * 3.6).toFixed(1)} km/h<br/>
          <strong>Varianza Inercial:</strong> ${parseFloat(p.motion_variance || 0).toFixed(4)}<br/>
          <strong>Pasos:</strong> ${p.step_count || 0}<br/>
          <strong>Batería:</strong> ${p.battery_level !== null ? p.battery_level + '%' : 'N/D'}
        </div>
      </div>
    `);

    trackingLayers.addLayer(marker);
    pointMarkersMap.set(p.id, marker);

    // Flechas direccionales cada 3 puntos si hay más de 1 punto y vendedor seleccionado
    if (selectedUser.value && index > 0 && index % 3 === 0) {
      const prevLat = parseFloat(props.puntos[index - 1].latitude);
      const prevLng = parseFloat(props.puntos[index - 1].longitude);
      const bearing = calculateBearing(prevLat, prevLng, lat, lng);

      const arrowIcon = L.divIcon({
        className: 'tracking-arrow',
        html: `<div style="transform: rotate(${bearing}deg); width: 12px; height: 12px; display: flex; align-items: center; justify-content: center; color: #0284c7; font-size: 10px; font-weight: bold;">▲</div>`,
        iconSize: [12, 12],
        iconAnchor: [6, 6],
      });

      trackingLayers.addLayer(L.marker([lat, lng], { icon: arrowIcon }));
    }
  });

  if (coords.length > 1) {
    const polyline = L.polyline(coords, {
      color: selectedUser.value ? '#0284c7' : '#94a3b8',
      weight: selectedUser.value ? 4 : 2,
      opacity: 0.8,
      lineJoin: 'round',
    });
    trackingLayers.addLayer(polyline);
    map.fitBounds(polyline.getBounds(), { padding: [40, 40], maxZoom: 16 });
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
  if (marker) {
    marker.openPopup();
  }
}

watch(
  () => props.puntos,
  () => {
    renderTracking();
  },
  { deep: true }
);

// Switch En Vivo: Sondeo cada 15 segundos
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