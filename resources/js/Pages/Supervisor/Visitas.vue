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
            <label class="text-[11px] font-bold text-gray-500 uppercase block mb-1">Auditoría Geocerca:</label>
            <select
              v-model="selectedAuditoria"
              @change="applyFilters"
              class="border border-gray-300 rounded-lg p-2 text-sm font-semibold bg-white outline-none focus:ring-2 focus:ring-amber-500"
            >
              <option value="TODAS">Todas las Visitas</option>
              <option value="DENTRO">En Rango (≤ 50m)</option>
              <option value="FUERA">Fuera de Rango (> 50m)</option>
              <option value="OPORTUNIDAD">Clientes Nuevos / Oportunidad</option>
            </select>
          </div>
        </div>

        <!-- Selector de Vista (Mapa vs Galería) -->
        <div class="flex items-center space-x-1 bg-slate-100 p-1 rounded-lg">
          <button
            @click="currentView = 'mapa'"
            class="px-3 py-1.5 rounded-md text-xs font-bold transition"
            :class="currentView === 'mapa' ? 'bg-white shadow text-slate-800' : 'text-slate-500 hover:text-slate-800'"
          >
            Mapa de Conciliación
          </button>
          <button
            @click="currentView = 'galeria'"
            class="px-3 py-1.5 rounded-md text-xs font-bold transition"
            :class="currentView === 'galeria' ? 'bg-white shadow text-slate-800' : 'text-slate-500 hover:text-slate-800'"
          >
            Galería de Evidencias ({{ visitas.length }})
          </button>
        </div>
      </div>

      <!-- Tarjetas KPI de Auditoría y Cumplimiento -->
      <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
        <div class="bg-white p-3.5 rounded-xl shadow-sm border border-gray-100">
          <p class="text-[11px] font-bold text-gray-400 uppercase">Plan del Día</p>
          <p class="text-xl font-black text-slate-800 mt-0.5">
            {{ metrics.total_visitados_plan }} <span class="text-xs font-semibold text-slate-400">/ {{ metrics.total_plan }}</span>
          </p>
          <span class="text-[11px] font-bold text-blue-600">{{ metrics.cobertura_pct }}% Cobertura</span>
        </div>

        <div class="bg-white p-3.5 rounded-xl shadow-sm border border-gray-100">
          <p class="text-[11px] font-bold text-gray-400 uppercase">En Rango (≤ 50m)</p>
          <p class="text-xl font-black text-emerald-600 mt-0.5">{{ metrics.en_rango }}</p>
          <span class="text-[11px] font-semibold text-slate-500">Dentro de geocerca</span>
        </div>

        <div class="bg-white p-3.5 rounded-xl shadow-sm border border-gray-100">
          <p class="text-[11px] font-bold text-gray-400 uppercase">Desvíos (> 50m)</p>
          <p class="text-xl font-black text-rose-600 mt-0.5">{{ metrics.fuera_rango }}</p>
          <span class="text-[11px] font-semibold text-rose-500">Alertas de distancia</span>
        </div>

        <div class="bg-white p-3.5 rounded-xl shadow-sm border border-gray-100">
          <p class="text-[11px] font-bold text-gray-400 uppercase">Oportunidades</p>
          <p class="text-xl font-black text-purple-600 mt-0.5">{{ metrics.oportunidades }}</p>
          <span class="text-[11px] font-semibold text-slate-500">Clientes no censados</span>
        </div>

        <div class="bg-white p-3.5 rounded-xl shadow-sm border border-gray-100">
          <p class="text-[11px] font-bold text-gray-400 uppercase">Faltantes</p>
          <p class="text-xl font-black text-amber-600 mt-0.5">{{ no_visitados.length }}</p>
          <span class="text-[11px] font-semibold text-amber-600">Por visitar hoy</span>
        </div>
      </div>

      <!-- VISTA 1: Mapa de Conciliación -->
      <div v-show="currentView === 'mapa'" class="grid grid-cols-1 lg:grid-cols-3 gap-4">
        <div class="lg:col-span-2 bg-white p-3 rounded-xl shadow-sm border border-gray-100">
          <div id="map-visitas" class="h-[620px] w-full rounded-lg"></div>
        </div>

        <!-- Panel Lateral de Puntos No Visitados / Desvíos -->
        <div class="bg-white p-4 rounded-xl shadow-sm border border-gray-100 flex flex-col h-[640px]">
          <div class="flex border-b border-gray-100 mb-3 pb-2 gap-3 text-xs font-bold">
            <button
              @click="sidebarTab = 'desvios'"
              class="pb-1 transition border-b-2"
              :class="sidebarTab === 'desvios' ? 'border-rose-600 text-rose-600' : 'border-transparent text-slate-400 hover:text-slate-600'"
            >
              Desvíos (>50m) [{{ desviosList.length }}]
            </button>
            <button
              @click="sidebarTab = 'faltantes'"
              class="pb-1 transition border-b-2"
              :class="sidebarTab === 'faltantes' ? 'border-amber-600 text-amber-600' : 'border-transparent text-slate-400 hover:text-slate-600'"
            >
              No Visitados [{{ no_visitados.length }}]
            </button>
          </div>

          <!-- Lista de Desvíos -->
          <div v-if="sidebarTab === 'desvios'" class="flex-1 overflow-y-auto space-y-2 pr-1">
            <div
              v-for="v in desviosList"
              :key="v.id"
              class="p-2.5 border border-rose-200 bg-rose-50/40 rounded-lg cursor-pointer hover:border-rose-400 transition text-xs"
              @click="focusVisita(v)"
            >
              <div class="flex justify-between items-start">
                <span class="font-mono font-bold text-slate-700">#{{ v.client_id }}</span>
                <span class="px-1.5 py-0.5 rounded font-black text-rose-700 bg-rose-100 text-[10px]">
                  {{ v.distancia_metros }}m desvío
                </span>
              </div>
              <p class="font-bold text-slate-900 mt-1">{{ v.client_name }}</p>
              <p class="text-slate-500 text-[11px] mt-0.5">Ruta: {{ v.route }} | {{ formatTime(v.visited_at) }}</p>
            </div>
            <div v-if="desviosList.length === 0" class="text-center py-14 text-slate-400 text-xs">
              No hay visitas fuera del radio de 50 metros.
            </div>
          </div>

          <!-- Lista de No Visitados -->
          <div v-if="sidebarTab === 'faltantes'" class="flex-1 overflow-y-auto space-y-2 pr-1">
            <div
              v-for="c in no_visitados"
              :key="c.client_id"
              class="p-2.5 border border-amber-200 bg-amber-50/40 rounded-lg cursor-pointer hover:border-amber-400 transition text-xs"
              @click="focusNoVisitado(c)"
            >
              <div class="flex justify-between items-start">
                <span class="font-mono font-bold text-slate-700">#{{ c.client_id }}</span>
                <span class="px-1.5 py-0.5 rounded font-bold text-amber-700 bg-amber-100 text-[10px]">
                  Pendiente
                </span>
              </div>
              <p class="font-bold text-slate-900 mt-1">{{ c.client_name }}</p>
              <p class="text-slate-500 text-[11px] mt-0.5">{{ c.address || 'Sin dirección' }}</p>
            </div>
            <div v-if="no_visitados.length === 0" class="text-center py-14 text-slate-400 text-xs">
              Todos los clientes del plan de hoy han sido visitados.
            </div>
          </div>
        </div>
      </div>

      <!-- VISTA 2: Galería de Evidencias -->
      <div v-show="currentView === 'galeria'" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
          <div
            v-for="v in visitas"
            :key="v.id"
            class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden flex flex-col hover:shadow-md transition"
          >
            <div class="relative bg-slate-900 h-48 cursor-pointer overflow-hidden group" @click="openModal(v)">
              <img
                v-if="v.photo_url"
                :src="v.photo_url"
                alt="Foto Visita"
                class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
              />
              <div v-else class="flex items-center justify-center h-full text-slate-500 text-xs">
                Sin Fotografía
              </div>

              <!-- Badge de Geocerca (50m) -->
              <div class="absolute top-2 right-2">
                <span v-if="v.is_opportunity" class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-purple-600 text-white shadow">
                  Oportunidad
                </span>
                <span v-else-if="v.en_rango" class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-emerald-600 text-white shadow">
                  {{ v.distancia_metros }}m (En Sitio)
                </span>
                <span v-else class="px-2 py-0.5 text-[11px] font-bold rounded-full bg-rose-600 text-white shadow">
                  {{ v.distancia_metros }}m (Desvío)
                </span>
              </div>
            </div>

            <div class="p-3.5 flex-1 flex flex-col justify-between text-xs">
              <div>
                <div class="flex justify-between items-center text-slate-400 font-mono text-[11px]">
                  <span>Ruta: {{ v.route }}</span>
                  <span>{{ formatTime(v.visited_at) }}</span>
                </div>
                <h4 class="font-bold text-slate-900 text-sm mt-1 leading-snug">{{ v.client_name }}</h4>
                <p v-if="v.address" class="text-slate-500 text-[11px] mt-0.5 truncate">{{ v.address }}</p>
                <p v-if="v.comments" class="text-slate-600 italic mt-2 bg-slate-50 p-2 rounded border border-slate-100 text-[11px]">
                  "{{ v.comments }}"
                </p>
              </div>

              <div class="mt-3 pt-2.5 border-t border-gray-100 flex justify-between items-center text-[11px]">
                <span class="font-bold text-slate-700">Estado: {{ v.status }}</span>
                <span class="text-slate-400 font-mono">±{{ Math.round(v.accuracy) }}m</span>
              </div>
            </div>
          </div>
        </div>

        <div v-if="visitas.length === 0" class="text-center py-20 text-slate-400 text-sm bg-white rounded-xl border border-gray-100">
          No se encontraron visitas registradas para este filtro.
        </div>
      </div>
    </div>

    <!-- Modal Fotografía Ampliada con Auditoría Técnica -->
    <div
      v-if="modalVisita"
      class="fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center z-50 p-4"
      @click.self="modalVisita = null"
    >
      <div class="bg-white rounded-xl overflow-hidden max-w-2xl w-full shadow-2xl">
        <div class="p-3.5 border-b border-gray-100 flex justify-between items-center bg-slate-50">
          <div>
            <h4 class="font-bold text-sm text-slate-900">{{ modalVisita.client_name }}</h4>
            <p class="text-xs text-slate-400">Ruta {{ modalVisita.route }} | Vendedor: {{ modalVisita.vendedor }}</p>
          </div>
          <button @click="modalVisita = null" class="text-slate-400 hover:text-slate-800 font-bold text-xl px-2">
            &times;
          </button>
        </div>

        <div class="max-h-[65vh] bg-black flex items-center justify-center">
          <img :src="modalVisita.photo_url" class="max-h-[65vh] object-contain" />
        </div>

        <div class="p-4 bg-white text-xs space-y-2 border-t border-gray-100">
          <div class="grid grid-cols-2 gap-3">
            <div>
              <p class="text-slate-400 font-semibold uppercase text-[10px]">Hora de Visita</p>
              <p class="font-bold text-slate-800">{{ modalVisita.visited_at }}</p>
            </div>
            <div>
              <p class="text-slate-400 font-semibold uppercase text-[10px]">Auditoría Geocerca</p>
              <p
                class="font-bold"
                :class="modalVisita.en_rango ? 'text-emerald-600' : 'text-rose-600'"
              >
                {{ modalVisita.distancia_metros !== null ? modalVisita.distancia_metros + ' metros' : 'Cliente Oportunidad' }}
              </p>
            </div>
            <div>
              <p class="text-slate-400 font-semibold uppercase text-[10px]">Coordenadas Fotografía</p>
              <p class="font-mono text-slate-700">{{ modalVisita.visita_lat }}, {{ modalVisita.visita_lon }} (±{{ Math.round(modalVisita.accuracy) }}m)</p>
            </div>
            <div v-if="modalVisita.official_lat">
              <p class="text-slate-400 font-semibold uppercase text-[10px]">Coordenadas Catastro</p>
              <p class="font-mono text-slate-700">{{ modalVisita.official_lat }}, {{ modalVisita.official_lon }}</p>
            </div>
          </div>
          <p v-if="modalVisita.comments" class="pt-2 border-t border-gray-100 text-slate-600">
            <strong>Observaciones del Vendedor:</strong> "{{ modalVisita.comments }}"
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
  visitas: Array,
  no_visitados: Array,
  metrics: Object,
});

const selectedDate = ref(props.selected_date);
const selectedRoute = ref(props.selected_route);
const selectedAuditoria = ref(props.selected_auditoria);
const currentView = ref('mapa');
const sidebarTab = ref('desvios');
const modalVisita = ref(null);

let map = null;
let mapLayers = null;
const visitaMarkersMap = new Map();
const noVisitadoMarkersMap = new Map();

const desviosList = computed(() => {
  return props.visitas.filter((v) => !v.is_opportunity && v.en_rango === false);
});

function formatTime(dt) {
  if (!dt) return '--:--';
  const parts = dt.split(' ');
  return parts.length > 1 ? parts[1] : dt;
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
      only: ['visitas', 'no_visitados', 'metrics', 'selected_route', 'selected_date', 'selected_auditoria'],
    }
  );
}

function openModal(v) {
  modalVisita.value = v;
}

function renderMap() {
  if (!map || !mapLayers) return;

  mapLayers.clearLayers();
  visitaMarkersMap.clear();
  noVisitadoMarkersMap.clear();

  const bounds = [];

  // 1. Renderizar Visitas Reales
  props.visitas.forEach((v) => {
    const lat = v.visita_lat;
    const lon = v.visita_lon;
    if (!lat || !lon) return;

    bounds.push([lat, lon]);

    let markerColor = '#059669'; // Verde: En Rango (<= 50m)
    let badgeText = `${v.distancia_metros}m En Rango`;

    if (v.is_opportunity) {
      markerColor = '#9333ea'; // Púrpura: Oportunidad
      badgeText = 'Cliente Oportunidad';
    } else if (!v.en_rango) {
      markerColor = '#dc2626'; // Rojo: Desvío (> 50m)
      badgeText = `${v.distancia_metros}m Desvío`;
    }

    const marker = L.circleMarker([lat, lon], {
      radius: 6.5,
      fillColor: markerColor,
      color: '#ffffff',
      weight: 1.5,
      fillOpacity: 0.9,
    });

    marker.bindPopup(`
      <div class="font-sans text-xs space-y-1">
        <div class="font-bold text-slate-900">${v.client_name}</div>
        <div class="text-[10px] font-mono text-slate-500">${v.visited_at} | Ruta: ${v.route}</div>
        <div class="pt-1">
          <span class="px-1.5 py-0.5 rounded text-[10px] font-bold text-white" style="background-color: ${markerColor}">
            ${badgeText}
          </span>
        </div>
        ${v.comments ? `<div class="italic text-slate-600 text-[11px] pt-1">"${v.comments}"</div>` : ''}
      </div>
    `);

    mapLayers.addLayer(marker);
    visitaMarkersMap.set(v.id, marker);

    // Si tiene desvío y existe coordenada oficial, trazar línea entre posición oficial y foto
    if (!v.is_opportunity && !v.en_rango && v.official_lat && v.official_lon) {
      bounds.push([v.official_lat, v.official_lon]);

      // Marcador de catastro oficial
      const officialMarker = L.circleMarker([v.official_lat, v.official_lon], {
        radius: 4.5,
        fillColor: '#64748b',
        color: '#ffffff',
        weight: 1,
        fillOpacity: 0.8,
      }).bindPopup(`
        <div class="font-sans text-xs">
          <strong>Ubicación Oficial Catastro</strong><br/>
          <span>${v.client_name}</span>
        </div>
      `);
      mapLayers.addLayer(officialMarker);

      // Línea punteada de desvío
      const dashLine = L.polyline([[v.official_lat, v.official_lon], [lat, lon]], {
        color: '#dc2626',
        weight: 2,
        dashArray: '4, 6',
        opacity: 0.7,
      });
      mapLayers.addLayer(dashLine);
    }
  });

  // 2. Renderizar Clientes No Visitados (Amarillo / Gris)
  props.no_visitados.forEach((c) => {
    if (!c.latitude || !c.longitude) return;
    bounds.push([c.latitude, c.longitude]);

    const marker = L.circleMarker([c.latitude, c.longitude], {
      radius: 5,
      fillColor: '#f59e0b',
      color: '#ffffff',
      weight: 1.2,
      fillOpacity: 0.85,
    }).bindPopup(`
      <div class="font-sans text-xs space-y-1">
        <div class="font-bold text-amber-700">NO VISITADO</div>
        <div class="font-bold text-slate-900">#${c.client_id} - ${c.client_name}</div>
        <div class="text-slate-500">${c.address || 'Sin dirección'}</div>
        <div class="text-[10px] text-slate-400">Ruta: ${c.route}</div>
      </div>
    `);

    mapLayers.addLayer(marker);
    noVisitadoMarkersMap.set(c.client_id, marker);
  });

  if (bounds.length > 0) {
    map.fitBounds(bounds, { padding: [35, 35], maxZoom: 16 });
  }
}

function focusVisita(v) {
  if (!map || !v.visita_lat || !v.visita_lon) return;
  map.setView([v.visita_lat, v.visita_lon], 17, { animate: true });
  const marker = visitaMarkersMap.get(v.id);
  if (marker) marker.openPopup();
}

function focusNoVisitado(c) {
  if (!map || !c.latitude || !c.longitude) return;
  map.setView([c.latitude, c.longitude], 17, { animate: true });
  const marker = noVisitadoMarkersMap.get(c.client_id);
  if (marker) marker.openPopup();
}

watch(
  () => [props.visitas, props.no_visitados],
  () => {
    renderMap();
  },
  { deep: true }
);

watch(currentView, (view) => {
  if (view === 'mapa' && map) {
    setTimeout(() => {
      map.invalidateSize();
      renderMap();
    }, 150);
  }
});

onMounted(() => {
  map = L.map('map-visitas').setView([-16.5000, -68.1500], 13);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '© OpenStreetMap contributors',
  }).addTo(map);

  mapLayers = L.featureGroup().addTo(map);
  renderMap();
});
</script>