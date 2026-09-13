<template>
  <SupervisorLayout>
    <!-- BARRA LATERAL: Controles del Reproductor -->
    <aside class="w-[380px] border-r border-slate-800 bg-slate-900 flex flex-col flex-shrink-0 z-10 p-4 space-y-4">
      <!-- Filtros -->
      <div class="space-y-3 pb-3 border-b border-slate-800">
        <div class="grid grid-cols-2 gap-2">
          <div>
            <label class="block text-[10px] uppercase font-bold text-slate-400 mb-1">Vendedor</label>
            <select
              v-model="filters.user_id"
              @change="applyFilters"
              class="w-full bg-slate-950 border border-slate-700 text-xs text-white rounded p-2 focus:ring-1 focus:ring-blue-500"
            >
              <option v-for="s in sellers" :key="s.id" :value="s.id">{{ s.username }}</option>
            </select>
          </div>
          <div>
            <label class="block text-[10px] uppercase font-bold text-slate-400 mb-1">Fecha</label>
            <input
              v-model="filters.date"
              @change="applyFilters"
              type="date"
              class="w-full bg-slate-950 border border-slate-700 text-xs text-white rounded p-2 focus:ring-1 focus:ring-blue-500"
            />
          </div>
        </div>
      </div>

      <!-- Controles de Playback -->
      <div v-if="locations.length > 0" class="space-y-4">
        <div class="bg-slate-950 p-4 rounded-xl border border-slate-800 space-y-3">
          <div class="flex justify-between items-center text-xs">
            <span class="text-slate-400 font-semibold">Punto Temporal</span>
            <span class="font-mono text-blue-400 font-bold">{{ currentLocation?.time || '--:--:--' }}</span>
          </div>

          <input
            type="range"
            min="0"
            :max="locations.length - 1"
            v-model.number="currentIndex"
            @input="onScrub"
            class="w-full accent-blue-500 cursor-pointer"
          />

          <div class="flex items-center gap-2 pt-1">
            <button
              @click="togglePlay"
              class="flex-1 py-2 bg-blue-600 hover:bg-blue-500 font-bold text-xs rounded transition text-white"
            >
              {{ isPlaying ? 'Pausar' : 'Reproducir' }}
            </button>
            <button
              @click="resetPlay"
              class="px-3 py-2 bg-slate-800 hover:bg-slate-700 font-bold text-xs rounded transition text-slate-300"
            >
              Inicio
            </button>
            <select
              v-model.number="speed"
              class="bg-slate-900 border border-slate-700 text-xs rounded px-2 py-2 text-white"
            >
              <option :value="1">1x</option>
              <option :value="5">5x</option>
              <option :value="10">10x</option>
              <option :value="25">25x</option>
            </select>
          </div>
        </div>

        <!-- Telemetría Instantánea -->
        <div class="grid grid-cols-3 gap-2 text-center text-xs">
          <div class="bg-slate-950 p-2.5 rounded-lg border border-slate-800">
            <span class="block text-[9px] text-slate-400 uppercase font-semibold">Velocidad</span>
            <span class="font-bold text-white mt-1 block">{{ currentLocation?.speed || 0 }} km/h</span>
          </div>
          <div class="bg-slate-950 p-2.5 rounded-lg border border-slate-800">
            <span class="block text-[9px] text-slate-400 uppercase font-semibold">Batería</span>
            <span class="font-bold text-white mt-1 block">{{ currentLocation?.battery || 0 }}%</span>
          </div>
          <div class="bg-slate-950 p-2.5 rounded-lg border border-slate-800">
            <span class="block text-[9px] text-slate-400 uppercase font-semibold">Mock GPS</span>
            <span
              :class="currentLocation?.is_mock ? 'text-rose-400 font-black' : 'text-emerald-400 font-bold'"
              class="mt-1 block"
            >
              {{ currentLocation?.is_mock ? 'SÍ' : 'NO' }}
            </span>
          </div>
        </div>

        <!-- Eventos de Hardware -->
        <div v-if="events.length > 0" class="space-y-2 pt-2">
          <h3 class="text-xs font-bold text-slate-400 uppercase">Eventos del Dispositivo</h3>
          <div class="space-y-1.5 max-h-48 overflow-y-auto pr-1">
            <div
              v-for="e in events"
              :key="e.id"
              class="text-[11px] p-2 bg-slate-950 border border-slate-800 rounded flex justify-between items-center"
            >
              <span class="text-amber-400 font-semibold">{{ e.event_type }}</span>
              <span class="text-slate-500 font-mono text-[10px]">{{ e.recorded_at.substring(11, 19) }}</span>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="text-center text-xs text-slate-500 py-12">
        No se encontró telemetría de 15 segundos para este vendedor en la fecha seleccionada.
      </div>
    </aside>

    <!-- MAPA EN PANTALLA COMPLETA -->
    <section class="flex-1 relative">
      <div id="tracking-map" class="w-full h-full z-0"></div>
    </section>
  </SupervisorLayout>
</template>

<script setup>
import { ref, onMounted, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import SupervisorLayout from '@/Layouts/SupervisorLayout.vue';
import L from 'leaflet';

const props = defineProps({
  sellers: Array,
  filters: Object,
  locations: Array,
  events: Array,
});

const filters = ref({
  date: props.filters.date,
  user_id: props.filters.user_id,
});

const currentIndex = ref(0);
const isPlaying = ref(false);
const speed = ref(5);
let timer = null;

let map = null;
let pathPolyline = null;
let runnerMarker = null;

const currentLocation = computed(() => {
  return props.locations[currentIndex.value] || null;
});

onMounted(() => {
  initMap();
  renderTracking();
});

watch(() => props.locations, () => {
  resetPlay();
  renderTracking();
});

const initMap = () => {
  map = L.map('tracking-map', { zoomControl: false }).setView([-16.5000, -68.1500], 13);
  L.control.zoom({ position: 'bottomright' }).addTo(map);

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap',
    maxZoom: 19,
  }).addTo(map);
};

const renderTracking = () => {
  if (!map) return;
  if (pathPolyline) map.removeLayer(pathPolyline);
  if (runnerMarker) map.removeLayer(runnerMarker);

  if (props.locations.length === 0) return;

  const latLngs = props.locations.map(l => [l.lat, l.lng]);

  pathPolyline = L.polyline(latLngs, {
    color: '#3b82f6',
    weight: 4,
    opacity: 0.8,
  }).addTo(map);

  runnerMarker = L.circleMarker(latLngs[0], {
    radius: 9,
    fillColor: '#6366f1',
    color: '#ffffff',
    weight: 3,
    fillOpacity: 1,
  }).addTo(map);

  map.fitBounds(pathPolyline.getBounds(), { padding: [60, 60] });
};

const togglePlay = () => {
  if (isPlaying.value) {
    pausePlay();
  } else {
    startPlay();
  }
};

const startPlay = () => {
  if (props.locations.length === 0) return;
  isPlaying.value = true;
  clearInterval(timer);

  timer = setInterval(() => {
    if (currentIndex.value >= props.locations.length - 1) {
      pausePlay();
      return;
    }
    currentIndex.value++;
    updateRunner();
  }, 1000 / speed.value);
};

const pausePlay = () => {
  isPlaying.value = false;
  clearInterval(timer);
};

const resetPlay = () => {
  pausePlay();
  currentIndex.value = 0;
  updateRunner();
};

const onScrub = () => {
  updateRunner();
};

const updateRunner = () => {
  const loc = currentLocation.value;
  if (!loc || !runnerMarker) return;
  runnerMarker.setLatLng([loc.lat, loc.lng]);
  map.panTo([loc.lat, loc.lng]);
};

const applyFilters = () => {
  router.get('/supervisor/tracking', filters.value, { preserveState: true });
};
</script>